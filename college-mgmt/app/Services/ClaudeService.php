<?php

namespace App\Services;

use Anthropic\Anthropic;

class ClaudeService
{
    private Anthropic $client;
    private string $model = 'claude-3-5-sonnet-20241022';

    public function __construct()
    {
        $this->client = new Anthropic([
            'apiKey' => env('ANTHROPIC_API_KEY'),
        ]);
    }

    /**
     * Analyze student academic data with prompt caching
     * Caches the system context (curriculum guidelines, evaluation rubrics, etc.)
     */
    public function analyzeStudentPerformance(array $studentData, string $query): string
    {
        $systemContext = $this->getSystemContext();

        $response = $this->client->messages->create([
            'model' => $this->model,
            'max_tokens' => 1024,
            'system' => [
                [
                    'type' => 'text',
                    'text' => $systemContext,
                    'cache_control' => ['type' => 'ephemeral'],
                ],
            ],
            'messages' => [
                [
                    'role' => 'user',
                    'content' => [
                        [
                            'type' => 'text',
                            'text' => "Student Data: " . json_encode($studentData),
                        ],
                        [
                            'type' => 'text',
                            'text' => $query,
                        ],
                    ],
                ],
            ],
        ]);

        return $response->content[0]->text;
    }

    /**
     * Generate curriculum recommendations with cached context
     * Large curriculum documents are cached to reduce token usage
     */
    public function generateCurriculumRecommendation(string $programId, string $request): string
    {
        $curriculumContext = $this->getCurriculumContext($programId);

        $response = $this->client->messages->create([
            'model' => $this->model,
            'max_tokens' => 2048,
            'system' => [
                [
                    'type' => 'text',
                    'text' => 'You are an academic advisor helping optimize curriculum design for college programs.',
                    'cache_control' => ['type' => 'ephemeral'],
                ],
            ],
            'messages' => [
                [
                    'role' => 'user',
                    'content' => [
                        [
                            'type' => 'text',
                            'text' => "Program Curriculum:\n" . $curriculumContext,
                            'cache_control' => ['type' => 'ephemeral'],
                        ],
                        [
                            'type' => 'text',
                            'text' => $request,
                        ],
                    ],
                ],
            ],
        ]);

        return $response->content[0]->text;
    }

    /**
     * Analyze admission applications with cached rubric
     * The evaluation rubric is large and reused across multiple applications
     */
    public function evaluateAdmissionApplication(array $applicantData): array
    {
        $rubric = $this->getAdmissionEvaluationRubric();

        $response = $this->client->messages->create([
            'model' => $this->model,
            'max_tokens' => 1024,
            'system' => [
                [
                    'type' => 'text',
                    'text' => 'You are an admission evaluator for a college.',
                    'cache_control' => ['type' => 'ephemeral'],
                ],
            ],
            'messages' => [
                [
                    'role' => 'user',
                    'content' => [
                        [
                            'type' => 'text',
                            'text' => "Evaluation Rubric:\n" . $rubric,
                            'cache_control' => ['type' => 'ephemeral'],
                        ],
                        [
                            'type' => 'text',
                            'text' => "Evaluate this applicant:\n" . json_encode($applicantData),
                        ],
                    ],
                ],
            ],
        ]);

        return [
            'evaluation' => $response->content[0]->text,
            'cache_hit' => $response->usage->cache_read_input_tokens > 0,
            'input_tokens' => $response->usage->input_tokens,
            'cache_read_tokens' => $response->usage->cache_read_input_tokens,
        ];
    }

    private function getSystemContext(): string
    {
        return <<<'EOT'
You are an academic performance analyst for a college system.
Provide insights on student academic standing, identify at-risk students,
and suggest interventions. Base your analysis on provided academic data including:
- GPA and marks
- Attendance records
- Assignment submissions
- Exam performance
- Course engagement metrics

Use these guidelines:
- CGPA < 5.0 indicates academic risk
- Attendance < 75% indicates attendance risk
- Missing assignments indicate engagement issues
- Consistent performance is a positive indicator
EOT;
    }

    private function getCurriculumContext(string $programId): string
    {
        $program = \App\Models\Program::find($programId);
        $subjects = \App\Models\ProgramSubject::where('program_id', $programId)
            ->with('subject')
            ->get();

        $context = "Program: " . ($program->name ?? 'Unknown') . "\n\n";
        $context .= "Subjects by Term:\n";

        $subjects->groupBy('term_id')->each(function ($termSubjects, $termId) use (&$context) {
            $context .= "\nTerm {$termId}:\n";
            $termSubjects->each(function ($ps) use (&$context) {
                $context .= "- " . ($ps->subject->name ?? 'Unknown') .
                    " ({$ps->subject->credits} credits, {$ps->type})\n";
            });
        });

        return $context;
    }

    private function getAdmissionEvaluationRubric(): string
    {
        return <<<'EOT'
ADMISSION EVALUATION RUBRIC:

Academic Excellence (40%):
- 10th Grade: 80-100 marks = 10 points, 70-79 = 8 points, <70 = 5 points
- 12th Grade: 80-100 marks = 10 points, 70-79 = 8 points, <70 = 5 points
- Entrance Exam (if applicable): >90 percentile = 20 points, >70 = 15 points, <70 = 10 points

Extracurricular Activities (30%):
- National level awards: 10 points
- State level awards: 7 points
- School level awards: 4 points
- Sports participation: 2 points per activity
- Cultural participation: 2 points per activity

Interview Performance (20%):
- Communication: Clear and articulate = 7 points, Good = 5 points, Fair = 3 points
- Motivation: Strong interest in program = 7 points, Moderate = 5 points, Unclear = 3 points
- Problem-solving: Can analyze issues = 6 points, Basic understanding = 4 points

Essay Quality (10%):
- Grammar and structure: Excellent = 4 points, Good = 3 points, Fair = 2 points
- Content relevance: Highly relevant = 3 points, Somewhat relevant = 2 points, Off-topic = 1 point
- Clarity of purpose: Very clear = 3 points, Somewhat clear = 2 points, Unclear = 1 point

Total Score: 100 points
Cutoff for admission: 65 points
EOT;
    }
}
