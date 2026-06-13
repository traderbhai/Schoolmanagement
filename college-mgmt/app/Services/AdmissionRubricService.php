<?php

namespace App\Services;

use App\Models\AdmissionAssessmentRubric;
use App\Models\AdmissionAssessmentRubricCriterion;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class AdmissionRubricService
{
    public function activeForType(string $assessmentType, ?int $programId = null): ?AdmissionAssessmentRubric
    {
        return AdmissionAssessmentRubric::with('criteria')
            ->where('assessment_type', $assessmentType)
            ->where('is_active', true)
            ->when($programId, fn ($q) => $q->where(fn ($s) => $s->whereNull('program_id')->orWhere('program_id', $programId)))
            ->orderByDesc('program_id')
            ->orderByDesc('version')
            ->first();
    }

    public function calculate(AdmissionAssessmentRubric $rubric, array $criterionInputs): array
    {
        $rubric->loadMissing('criteria');
        $total = 0.0;
        $max = 0.0;
        $details = [];

        foreach ($rubric->criteria as $criterion) {
            $input = $criterionInputs[$criterion->id] ?? [];
            $score = min((float) ($input['score'] ?? 0), (float) $criterion->max_score);
            $comment = trim((string) ($input['comment'] ?? ''));

            if ($criterion->requires_comment && $comment === '') {
                throw ValidationException::withMessages([
                    "criteria.{$criterion->id}.comment" => "Comment is required for {$criterion->name}.",
                ]);
            }

            $weighted = $score * max(0.01, (float) $criterion->weight);
            $weightedMax = (float) $criterion->max_score * max(0.01, (float) $criterion->weight);
            $total += $weighted;
            $max += $weightedMax;
            $details[$criterion->id] = [
                'name' => $criterion->name,
                'score' => $score,
                'max_score' => (float) $criterion->max_score,
                'weight' => (float) $criterion->weight,
                'weighted_score' => round($weighted, 2),
                'comment' => $comment,
            ];
        }

        $percentage = $max > 0 ? round(($total / $max) * 100, 2) : 0.0;

        return [
            'total_score' => round($total, 2),
            'max_possible_score' => round($max, 2),
            'percentage' => $percentage,
            'passed_minimum' => $percentage >= (float) $rubric->minimum_score,
            'details' => $details,
        ];
    }

    public function variance(Collection $percentages): array
    {
        $values = $percentages->filter(fn ($value) => is_numeric($value))->map(fn ($value) => (float) $value)->values();
        if ($values->isEmpty()) {
            return ['average' => null, 'spread' => null, 'flag' => false];
        }

        $average = round($values->avg(), 2);
        $spread = round($values->max() - $values->min(), 2);

        return ['average' => $average, 'spread' => $spread, 'flag' => $spread >= 20];
    }
}
