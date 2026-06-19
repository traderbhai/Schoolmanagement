<?php
namespace App\Http\Controllers\Admin;

use App\Helpers\AccessControl;
use App\Http\Controllers\Controller;
use App\Models\Admission;
use App\Models\ActivityLog;
use App\Models\Course;
use App\Models\Department;
use App\Models\Student;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class AdmissionController extends Controller
{
    public function index(Request $request)
    {
        $this->authorizeLegacyAdmissionOperations($request);

        $admissions = Admission::with('course')
            ->when($request->status, fn($q, $v) => $q->where('status', $v))
            ->when($request->course_id, fn($q, $v) => $q->where('course_id', $v))
            ->when($request->search, fn($q, $v) =>
                $q->where(fn($sq) =>
                    $sq->where('applicant_name', 'like', "%$v%")
                       ->orWhere('email', 'like', "%$v%")
                       ->orWhere('phone', 'like', "%$v%")
                )
            )
            ->latest()->paginate(20)->withQueryString();

        $courses = Course::where('is_active', true)->get();

        $counts = Admission::selectRaw("status, count(*) as total")
            ->groupBy('status')
            ->pluck('total', 'status');

        $statuses = ['enquiry', 'applied', 'shortlisted', 'admitted', 'rejected', 'withdrawn'];
        $statusCounts = [];
        foreach ($statuses as $s) {
            $statusCounts[$s] = $counts[$s] ?? 0;
        }

        return view('admin.admissions.index', compact('admissions', 'courses', 'statusCounts'));
    }

    public function create()
    {
        $this->authorizeLegacyAdmissionOperations(request());

        $courses = Course::where('is_active', true)->get();
        return view('admin.admissions.create', compact('courses'));
    }

    public function store(Request $request)
    {
        $this->authorizeLegacyAdmissionOperations($request);

        $request->validate([
            'applicant_name'  => 'required|string|max:191',
            'email'           => 'required|email|max:191|unique:admissions,email',
            'phone'           => 'nullable|string|max:20',
            'course_id'       => ['required', Rule::exists('courses', 'id')->where('is_active', true)],
            'status'          => 'required|in:enquiry,applied,shortlisted,admitted,rejected,withdrawn',
            'date_of_birth'   => 'nullable|date',
            'last_percentage' => 'nullable|numeric|min:0|max:100',
        ]);

        if ($request->status === 'admitted') {
            return back()
                ->withErrors(['status' => $this->manualAdmittedStatusMessage()])
                ->withInput();
        }

        Admission::create($request->only([
            'applicant_name', 'email', 'phone', 'father_name', 'mother_name',
            'date_of_birth', 'gender', 'address', 'category', 'course_id',
            'last_qualification', 'last_institution', 'last_percentage',
            'application_date', 'status', 'remarks',
        ]));

        return redirect()->route('admin.admissions.index')->with('success', 'Enquiry saved successfully.');
    }

    public function show(Admission $admission)
    {
        $this->authorizeLegacyAdmissionOperations(request());

        $admission->load('course', 'student.user');
        return view('admin.admissions.show', compact('admission'));
    }

    public function edit(Admission $admission)
    {
        $this->authorizeLegacyAdmissionOperations(request());

        $courses = Course::where('is_active', true)->get();
        return view('admin.admissions.edit', compact('admission', 'courses'));
    }

    public function update(Request $request, Admission $admission)
    {
        $this->authorizeLegacyAdmissionOperations($request);

        $request->validate([
            'applicant_name'  => 'required|string|max:191',
            'email'           => 'required|email|max:191|unique:admissions,email,' . $admission->id,
            'phone'           => 'nullable|string|max:20',
            'course_id'       => ['required', Rule::exists('courses', 'id')->where('is_active', true)],
            'status'          => 'required|in:enquiry,applied,shortlisted,admitted,rejected,withdrawn',
            'date_of_birth'   => 'nullable|date',
            'last_percentage' => 'nullable|numeric|min:0|max:100',
        ]);

        if ($this->isFinalized($admission) && $this->changesFinalizedContract($request, $admission)) {
            return back()->with('error', 'Converted admissions are locked. Use the student lifecycle or audited correction workflow instead of rewriting admission history.');
        }

        if ($request->status === 'admitted' && ! $admission->converted_student_id) {
            return back()
                ->withErrors(['status' => $this->manualAdmittedStatusMessage()])
                ->withInput();
        }

        $admission->update($request->only([
            'applicant_name', 'email', 'phone', 'father_name', 'mother_name',
            'date_of_birth', 'gender', 'address', 'category', 'course_id',
            'last_qualification', 'last_institution', 'last_percentage',
            'application_date', 'status', 'remarks',
        ]));

        return redirect()->route('admin.admissions.show', $admission)->with('success', 'Admission updated successfully.');
    }

    public function destroy(Admission $admission)
    {
        $this->authorizeLegacyAdmissionOperations(request());

        if ($this->isFinalized($admission)) {
            return back()->with('error', 'Converted admissions cannot be deleted because linked student history depends on them.');
        }

        $admission->delete();
        return redirect()->route('admin.admissions.index')->with('success', 'Record deleted.');
    }

    public function updateStatus(Request $request, Admission $admission)
    {
        $this->authorizeLegacyAdmissionOperations($request);

        $request->validate([
            'status'  => 'required|in:enquiry,applied,shortlisted,admitted,rejected,withdrawn',
            'remarks' => 'nullable|string',
        ]);

        if ($this->isFinalized($admission) && $request->status !== $admission->status) {
            return back()->with('error', 'Converted admissions are locked. Use the student lifecycle or audited correction workflow instead of changing admission status.');
        }

        if ($request->status === 'admitted' && ! $admission->converted_student_id) {
            return back()->withErrors(['status' => $this->manualAdmittedStatusMessage()]);
        }

        $admission->update($request->only('status', 'remarks'));

        return redirect()->back()->with('success', 'Status updated.');
    }

    public function convertToStudent(Request $request, Admission $admission)
    {
        $this->authorizeLegacyAdmissionOperations($request);

        if ($this->isFinalized($admission)) {
            return back()->with('error', 'This admission has already been converted to a student.');
        }

        if (in_array($admission->status, ['rejected', 'withdrawn'], true)) {
            return back()->with('error', 'Rejected or withdrawn admissions cannot be converted to students. Reopen through an audited admission correction workflow first.');
        }

        $request->validate([
            'enrollment_number' => 'required|string|unique:students,enrollment_number',
            'department_id'     => ['required', Rule::exists('departments', 'id')->where('is_active', true)],
        ]);

        // Create user account
        $user = User::create([
            'name'     => $admission->applicant_name,
            'email'    => $admission->email,
            'password' => Hash::make('password'), // default password
        ]);
        $user->assignRole('student');

        // Create student record
        $student = Student::create([
            'user_id'           => $user->id,
            'department_id'     => $request->department_id,
            'course_id'         => $admission->course_id,
            'enrollment_number' => $request->enrollment_number,
            'phone'             => $admission->phone,
            'date_of_birth'     => $admission->date_of_birth,
            'gender'            => $admission->gender,
            'address'           => $admission->address,
            'admission_date'    => now()->toDateString(),
            'status'            => 'active',
        ]);

        // Update admission record
        $admission->update([
            'converted_student_id' => $student->id,
            'status'               => 'admitted',
        ]);

        // Dispatch welcome email if job exists
        if (class_exists(\App\Jobs\SendWelcomeEmail::class)) {
            $student->load('user');
            \App\Jobs\SendWelcomeEmail::dispatch($student);
        }

        ActivityLog::record('created', "Admission converted to student: {$user->name}", $student);

        return redirect()->route('admin.students.show', $student)->with('success', 'Student account created successfully.');
    }

    private function isFinalized(Admission $admission): bool
    {
        return (bool) $admission->converted_student_id || $admission->status === 'admitted';
    }

    private function authorizeLegacyAdmissionOperations(Request $request): void
    {
        abort_unless(
            $request->user() && AccessControl::canManageLegacyAdmissionOperations($request->user()),
            403
        );
    }

    private function changesFinalizedContract(Request $request, Admission $admission): bool
    {
        $protectedFields = [
            'applicant_name', 'email', 'phone', 'father_name', 'mother_name',
            'date_of_birth', 'gender', 'address', 'category', 'course_id',
            'last_qualification', 'last_institution', 'last_percentage',
            'application_date', 'status',
        ];

        foreach ($protectedFields as $field) {
            if (! $request->has($field)) {
                continue;
            }

            $incoming = $request->input($field);
            $current = $admission->{$field};

            if ($current instanceof \Carbon\CarbonInterface) {
                $current = $current->toDateString();
            }

            if ((string) $incoming !== (string) $current) {
                return true;
            }
        }

        return false;
    }

    private function manualAdmittedStatusMessage(): string
    {
        return 'Use Convert to Student to admit legacy admissions so a linked active student record is created.';
    }
}
