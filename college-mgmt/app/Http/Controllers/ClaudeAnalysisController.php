<?php

namespace App\Http\Controllers;

use App\Models\Applicant;
use App\Models\Student;
use App\Services\ClaudeService;
use Illuminate\Http\Request;

class ClaudeAnalysisController extends Controller
{
    public function __construct(private ClaudeService $claudeService)
    {
    }

    /**
     * Analyze a student's academic performance using Claude with cached context
     * First request: full tokens charged (system context + input)
     * Subsequent requests: ~90% reduction (system context cached)
     */
    public function analyzeStudent($studentId)
    {
        $student = Student::with([
            'user',
            'batch',
            'enrollments',
            'examResults',
            'attendance',
        ])->findOrFail($studentId);

        $studentData = [
            'name' => $student->user->name,
            'roll_number' => $student->roll_number,
            'cgpa' => $student->cgpa,
            'batch' => $student->batch->name,
            'attendance_percentage' => $student->attendance_percentage,
            'pending_arrears' => $student->pending_arrears,
            'recent_marks' => $student->examResults->take(5)->map(fn($r) => [
                'subject' => $r->subject->name,
                'marks' => $r->marks,
                'date' => $r->created_at->toDateString(),
            ])->toArray(),
        ];

        $analysis = $this->claudeService->analyzeStudentPerformance(
            $studentData,
            'Provide a comprehensive academic performance analysis and recommend interventions if needed.'
        );

        return response()->json([
            'student' => $studentData,
            'analysis' => $analysis,
        ]);
    }

    /**
     * Evaluate admission applications with cached evaluation rubric
     * Each subsequent application uses cached rubric: ~90% cost reduction per evaluation
     */
    public function evaluateAdmission($applicantId)
    {
        $applicant = Applicant::with([
            'program',
            'documents',
        ])->findOrFail($applicantId);

        $applicantData = [
            'name' => $applicant->name,
            'email' => $applicant->email,
            'program' => $applicant->program->name,
            'previous_institution' => $applicant->previous_institution,
            'gpa' => $applicant->gpa,
            'entrance_exam_score' => $applicant->entrance_exam_score ?? 'N/A',
            'documents' => $applicant->documents->pluck('document_type')->toArray(),
        ];

        $evaluation = $this->claudeService->evaluateAdmissionApplication($applicantData);

        return response()->json([
            'applicant' => $applicantData,
            'evaluation' => $evaluation['evaluation'],
            'cache_hit' => $evaluation['cache_hit'],
            'tokens_saved' => $evaluation['cache_read_tokens'] * 0.9,
        ]);
    }

    /**
     * Generate curriculum recommendations with cached program context
     * Useful for PMC planning: first request loads curriculum, subsequent uses cache
     */
    public function recommendCurriculum(Request $request, $programId)
    {
        $request->validate([
            'request' => 'required|string|max:1000',
        ]);

        $recommendation = $this->claudeService->generateCurriculumRecommendation(
            $programId,
            $request->input('request')
        );

        return response()->json([
            'recommendation' => $recommendation,
            'tip' => 'Tip: Multiple queries for the same program will reuse the cached curriculum context.',
        ]);
    }
}
