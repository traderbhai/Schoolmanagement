<?php
namespace App\Http\Controllers\Student;
use App\Http\Controllers\Controller;
use App\Models\StudentResume;
use App\Services\GradeService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ResumeController extends Controller {
    public function index() {
        $student = Auth::user()->student;
        abort_unless($student, 403);
        $student->load(['program','batch','user']);
        $resume = StudentResume::firstOrNew(['student_id'=>$student->id]);
        $cgpa = app(GradeService::class)->calculateCGPA($student->id);
        $canEditResume = $student->status === 'active';

        return view('student.resume.index', compact('student','resume','cgpa', 'canEditResume'));
    }

    public function save(Request $request) {
        $student = Auth::user()->student;
        abort_unless($student, 403);

        if ($student->status !== 'active') {
            return back()->with('error', 'Resume updates are available only for active students. Contact the placement office for archived records.');
        }

        $request->validate([
            'headline'    => 'nullable|string|max:150',
            'objective'   => 'nullable|string|max:1000',
            'linkedin_url'=> 'nullable|url|max:255',
            'github_url'  => 'nullable|url|max:255',
            'portfolio_url'=>'nullable|url|max:255',
            'skills'      => 'nullable|string',
            'languages'   => 'nullable|string',
        ]);

        // Parse comma-separated tags into arrays
        $skills    = $request->skills    ? array_values(array_filter(array_map('trim', explode(',', $request->skills)))) : [];
        $languages = $request->languages ? array_values(array_filter(array_map('trim', explode(',', $request->languages)))) : [];

        // Parse JSON blocks for structured sections
        $projects       = $this->parseJsonArray($request->projects_json);
        $certifications = $this->parseJsonArray($request->certifications_json);
        $experience     = $this->parseJsonArray($request->experience_json);

        $resume = StudentResume::updateOrCreate(
            ['student_id' => $student->id],
            [
                'headline'       => $request->headline,
                'objective'      => $request->objective,
                'skills'         => $skills,
                'languages'      => $languages,
                'projects'       => $projects,
                'certifications' => $certifications,
                'experience'     => $experience,
                'linkedin_url'   => $request->linkedin_url,
                'github_url'     => $request->github_url,
                'portfolio_url'  => $request->portfolio_url,
                'is_complete'    => !empty($skills) && $request->filled('headline'),
            ]
        );

        return back()->with('success', 'Resume saved successfully.');
    }

    private function parseJsonArray(?string $json): array {
        if (!$json) return [];
        $decoded = json_decode($json, true);
        return is_array($decoded) ? $decoded : [];
    }
}
