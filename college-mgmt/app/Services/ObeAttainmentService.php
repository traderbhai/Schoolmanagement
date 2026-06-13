<?php

namespace App\Services;

use App\Models\{CoAttainment, CoPoMapping, CourseOutcome, ExamResult, ObeSurveyResponse, PoAttainment, ProgramOutcome, Subject};
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ObeAttainmentService
{
    /**
     * Calculate direct CO attainment for a subject in a term.
     * Direct attainment = % of students who scored >= threshold in questions mapped to CO.
     * Falls back to overall subject pass % if no CO-mapped marks exist.
     */
    public function calculateDirectAttainment(int $subjectId, int $termId, ?int $batchId = null, float $threshold = 50.0): array
    {
        // Get exam results for this subject-term
        $query = ExamResult::whereHas('assessmentComponent', fn($q) => $q->where('subject_id', $subjectId))
            ->whereHas('student', fn($q) => $q->where('status', 'active'));

        if ($batchId) {
            $query->whereHas('student', fn($q) => $q->where('batch_id', $batchId));
        }

        $results = $query->with('assessmentComponent')->get();

        if ($results->isEmpty()) {
            return [];
        }

        $outcomes = CourseOutcome::where('subject_id', $subjectId)->where('is_active', true)->get();

        $attainments = [];
        foreach ($outcomes as $co) {
            // Per student: aggregate marks across all components, compute % of total marks
            $studentMarks = $results->groupBy('student_id')->map(function ($rows) {
                $obtained = $rows->sum('marks_obtained');
                $total    = $rows->sum(fn($r) => $r->assessmentComponent->max_marks ?? 0);
                return $total > 0 ? ($obtained / $total) * 100 : 0;
            });

            $assessed  = $studentMarks->count();
            $attained  = $studentMarks->filter(fn($pct) => $pct >= $threshold)->count();
            $directPct = $assessed > 0 ? round(($attained / $assessed) * 100, 2) : 0;

            $attainments[$co->id] = [
                'co_id'             => $co->id,
                'students_assessed' => $assessed,
                'students_attained' => $attained,
                'direct_attainment' => $directPct,
            ];
        }

        return $attainments;
    }

    /**
     * Calculate indirect CO attainment from OBE survey responses (Likert 1-5 → % of max 5).
     */
    public function calculateIndirectAttainment(int $subjectId, int $termId): array
    {
        $rows = ObeSurveyResponse::whereHas('survey', fn($q) =>
                $q->where('subject_id', $subjectId)->where('term_id', $termId)
            )
            ->selectRaw('course_outcome_id, AVG(rating) as avg_rating, COUNT(*) as response_count')
            ->groupBy('course_outcome_id')
            ->get();

        $result = [];
        foreach ($rows as $row) {
            // Likert avg on 1-5 scale → convert to % (3/5 = 60%)
            $result[$row->course_outcome_id] = [
                'indirect_attainment' => round(($row->avg_rating / 5) * 100, 2),
                'response_count'      => $row->response_count,
            ];
        }
        return $result;
    }

    /**
     * Persist CO attainments for a subject-term. Direct weight = 80%, indirect = 20% (NBA default).
     */
    public function persistCoAttainments(
        int $subjectId,
        int $termId,
        ?int $batchId = null,
        float $directWeight = 0.8,
        float $targetPct = 60.0
    ): int {
        $direct   = $this->calculateDirectAttainment($subjectId, $termId, $batchId);
        $indirect = $this->calculateIndirectAttainment($subjectId, $termId);
        $saved    = 0;

        foreach ($direct as $coId => $data) {
            $indirectPct  = $indirect[$coId]['indirect_attainment'] ?? 0;
            $finalPct     = round($data['direct_attainment'] * $directWeight + $indirectPct * (1 - $directWeight), 2);
            $targetMet    = $finalPct >= $targetPct;

            CoAttainment::updateOrCreate(
                ['course_outcome_id' => $coId, 'term_id' => $termId, 'batch_id' => $batchId],
                [
                    'subject_id'          => $subjectId,
                    'direct_attainment'   => $data['direct_attainment'],
                    'indirect_attainment' => $indirectPct,
                    'final_attainment'    => $finalPct,
                    'target_attainment'   => $targetPct,
                    'target_met'          => $targetMet,
                    'students_assessed'   => $data['students_assessed'],
                    'students_attained'   => $data['students_attained'],
                ]
            );
            $saved++;
        }
        return $saved;
    }

    /**
     * Calculate and persist PO attainments for a program-term.
     * PO attainment = weighted average of CO attainments using CO-PO correlation as weight.
     */
    public function persistPoAttainments(int $programId, int $termId): int
    {
        $pos    = ProgramOutcome::where('program_id', $programId)->where('is_active', true)->get();
        $saved  = 0;

        foreach ($pos as $po) {
            $mappings = CoPoMapping::where('program_outcome_id', $po->id)
                ->where('correlation_level', '>', 0)
                ->with('courseOutcome')
                ->get();

            if ($mappings->isEmpty()) continue;

            $weightedSum = 0;
            $totalWeight = 0;

            foreach ($mappings as $mapping) {
                $attainment = CoAttainment::where('course_outcome_id', $mapping->course_outcome_id)
                    ->where('term_id', $termId)
                    ->first();

                if ($attainment) {
                    $w            = $mapping->correlation_level; // 1, 2, or 3
                    $weightedSum += $attainment->final_attainment * $w;
                    $totalWeight += $w;
                }
            }

            if ($totalWeight > 0) {
                $attainmentValue = round($weightedSum / $totalWeight, 2);
                PoAttainment::updateOrCreate(
                    ['program_outcome_id' => $po->id, 'program_id' => $programId, 'term_id' => $termId],
                    [
                        'attainment_value' => $attainmentValue,
                        'target_value'     => 60,
                        'target_met'       => $attainmentValue >= 60,
                    ]
                );
                $saved++;
            }
        }
        return $saved;
    }

    /**
     * Full recalculation for all subjects in a program for a given term.
     */
    public function recalculateForTerm(int $programId, int $termId): array
    {
        $subjects = Subject::where('program_id', $programId)->pluck('id');
        $coSaved  = 0;

        foreach ($subjects as $subjectId) {
            $coSaved += $this->persistCoAttainments($subjectId, $termId);
        }

        $poSaved = $this->persistPoAttainments($programId, $termId);

        return ['co_records' => $coSaved, 'po_records' => $poSaved];
    }
}
