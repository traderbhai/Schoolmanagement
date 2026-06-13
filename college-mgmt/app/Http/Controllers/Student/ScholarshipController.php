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

        $schemes = ScholarshipScheme::where('is_active', true)
            ->where(fn($q) => $q->whereNull('program_id')->orWhere('program_id',$student->program_id))
            ->get();

        $myApplications = StudentScholarshipApplication::where('student_id',$student->id)
            ->with('scheme')->get()->keyBy('scholarship_scheme_id');

        return view('student.scholarships.index', compact('schemes','myApplications'));
    }

    public function apply(Request $request, ScholarshipScheme $scheme) {
        $student = Auth::user()->student;
        abort_unless($student, 403);
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

        $request->validate(['reason'=>'required|string|min:50|max:2000']);

        $cgpa = app(GradeService::class)->calculateCGPA($student->id);

        StudentScholarshipApplication::create([
            'student_id'           => $student->id,
            'scholarship_scheme_id'=> $scheme->id,
            'cgpa_at_application'  => $cgpa ?: null,
            'reason'               => $request->reason,
        ]);

        return back()->with('success', 'Application for "' . $scheme->name . '" submitted successfully.');
    }
}
