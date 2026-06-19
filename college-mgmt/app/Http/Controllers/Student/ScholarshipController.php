<?php
namespace App\Http\Controllers\Student;
use App\Http\Controllers\Controller;
use App\Models\{ScholarshipScheme, StudentScholarshipApplication};
use App\Services\GradeService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ScholarshipController extends Controller {
    public function index() {
        $student = Auth::user()->student;
        abort_unless($student, 403);
        $canApplyForScholarships = $student->status === 'active';
        $cgpa = app(GradeService::class)->calculateCGPA($student->id);
        $familyIncome = $this->familyIncome($student);

        $schemes = ScholarshipScheme::where('is_active', true)
            ->where(fn($q) => $q->whereNull('program_id')->orWhere('program_id',$student->program_id))
            ->get()
            ->map(function (ScholarshipScheme $scheme) use ($student, $cgpa, $familyIncome) {
                $scheme->student_eligibility = $this->eligibilityFor($student, $scheme, $cgpa, $familyIncome);
                return $scheme;
            });

        $myApplications = StudentScholarshipApplication::where('student_id',$student->id)
            ->with('scheme')->get()->keyBy('scholarship_scheme_id');

        return view('student.scholarships.index', compact('schemes','myApplications', 'cgpa', 'familyIncome', 'canApplyForScholarships'));
    }

    public function apply(Request $request, ScholarshipScheme $scheme) {
        $student = Auth::user()->student;
        abort_unless($student, 403);

        if ($student->status !== 'active') {
            return back()
                ->withErrors(['student' => 'Scholarship applications are available only for active students. Contact the office for archived records.'])
                ->withInput();
        }

        abort_unless($scheme->is_active, 422, 'This scholarship is not accepting applications.');
        abort_if(
            $scheme->program_id && $scheme->program_id !== $student->program_id,
            403,
            'This scholarship is not available for your program.'
        );

        abort_if(
            $scheme->available_seats !== null && $scheme->seatsRemaining() <= 0,
            422,
            'This scholarship has no seats remaining.'
        );

        abort_if(
            StudentScholarshipApplication::where('student_id',$student->id)
                ->where('scholarship_scheme_id',$scheme->id)->exists(),
            422, 'You have already applied for this scholarship.'
        );

        $cgpa = app(GradeService::class)->calculateCGPA($student->id);
        $familyIncome = $this->familyIncome($student);
        $eligibility = $this->eligibilityFor($student, $scheme, $cgpa, $familyIncome);

        if (! $eligibility['eligible']) {
            return back()->withErrors(['eligibility' => $eligibility['reason']])->withInput();
        }

        $data = $request->validate([
            'reason' => 'required|string|min:50|max:2000',
            'proof_document' => [$scheme->requires_document ? 'required' : 'nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
        ]);

        $path = null;
        if ($request->hasFile('proof_document')) {
            $path = $request->file('proof_document')->store('student-scholarships/'.$student->id, 'local');
        }

        StudentScholarshipApplication::create([
            'student_id'           => $student->id,
            'scholarship_scheme_id'=> $scheme->id,
            'cgpa_at_application'  => $cgpa ?: null,
            'reason'               => $data['reason'],
            'documents_path'       => $path,
        ]);

        return back()->with('success', 'Application for "' . $scheme->name . '" submitted successfully.');
    }

    private function eligibilityFor($student, ScholarshipScheme $scheme, float $cgpa, ?float $familyIncome): array
    {
        if ($scheme->program_id && (int) $scheme->program_id !== (int) $student->program_id) {
            return ['eligible' => false, 'reason' => 'This scholarship is not available for your program.'];
        }

        if ($scheme->min_cgpa !== null && $cgpa < (float) $scheme->min_cgpa) {
            return [
                'eligible' => false,
                'reason' => 'Minimum CGPA requirement not met. Required '.number_format((float) $scheme->min_cgpa, 2).'; current '.number_format($cgpa, 2).'.',
            ];
        }

        if ($scheme->max_family_income !== null && $familyIncome !== null && $familyIncome > (float) $scheme->max_family_income) {
            return [
                'eligible' => false,
                'reason' => 'Family income exceeds this scholarship limit of Rs. '.number_format((float) $scheme->max_family_income, 0).'.',
            ];
        }

        if ($scheme->max_family_income !== null && $familyIncome === null) {
            return [
                'eligible' => false,
                'reason' => 'Family income is required to apply for this scholarship. Ask admin to update guardian income first.',
            ];
        }

        return ['eligible' => true, 'reason' => null];
    }

    private function familyIncome($student): ?float
    {
        $raw = $student->parents()->pluck('annual_income')
            ->filter()
            ->first();

        if ($raw === null || $raw === '') {
            return null;
        }

        $numeric = preg_replace('/[^0-9.]/', '', (string) $raw);
        return $numeric === '' ? null : (float) $numeric;
    }
}
