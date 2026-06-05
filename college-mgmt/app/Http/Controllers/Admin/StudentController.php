<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\SendWelcomeEmail;
use App\Models\{Student, User, Department, Course, ActivityLog};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class StudentController extends Controller
{
    public function index(Request $request)
    {
        $students = Student::with(['user','department','course'])
            ->when($request->department_id, fn($q,$v) => $q->where('department_id',$v))
            ->when($request->course_id,     fn($q,$v) => $q->where('course_id',$v))
            ->when($request->status,        fn($q,$v) => $q->where('status',$v))
            ->when($request->search, fn($q,$v) =>
                $q->whereHas('user', fn($u) => $u->where('name','like',"%$v%")->orWhere('email','like',"%$v%"))
                  ->orWhere('enrollment_number','like',"%$v%")
            )
            ->latest()->paginate(20)->withQueryString();

        $departments = Department::where('is_active',true)->get();
        $courses = Course::where('is_active',true)->get();
        return view('admin.students.index', compact('students','departments','courses'));
    }

    public function create()
    {
        $departments = Department::where('is_active',true)->get();
        $courses = Course::where('is_active',true)->get();
        return view('admin.students.create', compact('departments','courses'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'name'              => 'required|string|max:255',
            'email'             => 'required|email|unique:users',
            'password'          => 'required|min:8',
            'department_id'     => 'required|exists:departments,id',
            'course_id'         => 'required|exists:courses,id',
            'enrollment_number' => 'required|unique:students',
            'date_of_birth'     => 'nullable|date',
            'gender'            => 'nullable|in:male,female,other',
            'phone'             => 'nullable|string|max:20',
            'admission_date'    => 'nullable|date',
        ]);

        $user = User::create([
            'name'     => $request->name,
            'email'    => $request->email,
            'password' => Hash::make($request->password),
        ]);
        $user->assignRole('student');

        $student = Student::create([...$request->only([
            'department_id','course_id','enrollment_number','roll_number',
            'date_of_birth','gender','phone','address','guardian_name',
            'guardian_phone','admission_date','current_semester',
        ]), 'user_id' => $user->id]);

        $student->load('user');
        SendWelcomeEmail::dispatch($student);
        ActivityLog::record('created', "Student {$student->user->name} registered", $student);

        return redirect()->route('admin.students.index')->with('success', 'Student registered successfully.');
    }

    public function show(Student $student)
    {
        $student->load(['user', 'department', 'course', 'enrollments.subject', 'examResults.exam', 'feePayments.feeStructure']);
        return view('admin.students.show', compact('student'));
    }

    public function edit(Student $student)
    {
        $departments = Department::where('is_active',true)->get();
        $courses = Course::where('is_active',true)->get();
        return view('admin.students.edit', compact('student','departments','courses'));
    }

    public function update(Request $request, Student $student)
    {
        $request->validate([
            'name'          => 'required|string|max:255',
            'email'         => 'required|email|unique:users,email,'.$student->user_id,
            'department_id' => 'required|exists:departments,id',
            'course_id'     => 'required|exists:courses,id',
            'status'        => 'required|in:active,inactive,graduated,dropped',
        ]);

        $student->user->update(['name' => $request->name, 'email' => $request->email]);
        $student->update($request->only([
            'department_id','course_id','roll_number','date_of_birth','gender',
            'phone','address','guardian_name','guardian_phone','current_semester','status',
        ]));

        return redirect()->route('admin.students.show', $student)->with('success', 'Student updated.');
    }

    public function destroy(Student $student)
    {
        $name = $student->user->name;
        ActivityLog::record('deleted', "Student deleted: {$name}", $student);
        $student->user->delete();
        return redirect()->route('admin.students.index')->with('success', 'Student deleted.');
    }

    public function export(Request $r)
    {
        $students = Student::with('user', 'course', 'department')
            ->when($r->course_id, fn($q) => $q->where('course_id', $r->course_id))
            ->when($r->status, fn($q) => $q->where('status', $r->status))
            ->get();

        $filename = 'students_' . now()->format('Ymd_His') . '.csv';

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ];

        $callback = function () use ($students) {
            $file = fopen('php://output', 'w');
            fputcsv($file, ['#','Name','Email','Enrollment No','Roll No','Course','Department','Semester','Status','Admission Date']);
            foreach ($students as $i => $s) {
                fputcsv($file, [
                    $i + 1,
                    $s->user->name,
                    $s->user->email,
                    $s->enrollment_number,
                    $s->roll_number,
                    $s->course->name ?? '',
                    $s->department->name ?? '',
                    $s->current_semester,
                    $s->status,
                    $s->admission_date?->format('d/m/Y') ?? '',
                ]);
            }
            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }
}
