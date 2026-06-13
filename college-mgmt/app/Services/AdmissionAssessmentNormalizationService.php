<?php

namespace App\Services;

use App\Models\AdmissionAssessmentNormalizedScore;
use App\Models\AdmissionAssessmentPanel;
use App\Models\AdmissionAssessmentPanelAssignment;
use Illuminate\Support\Collection;

class AdmissionAssessmentNormalizationService
{
    public function normalizePanel(AdmissionAssessmentPanel $panel): Collection
    {
        $assignments = AdmissionAssessmentPanelAssignment::where('panel_id', $panel->id)
            ->whereNotNull('aggregate_score')
            ->get();
        $panelMean = round($assignments->avg('aggregate_score') ?? 0, 2);

        return $assignments->map(function (AdmissionAssessmentPanelAssignment $assignment) use ($panelMean) {
            $evaluatorMean = round(AdmissionAssessmentPanelAssignment::where('panel_id', $assignment->panel_id)
                ->where('evaluator_user_id', $assignment->evaluator_user_id)
                ->whereNotNull('aggregate_score')
                ->avg('aggregate_score') ?? $assignment->aggregate_score, 2);
            $normalized = round(($assignment->aggregate_score - $evaluatorMean) + $panelMean, 2);
            $outlier = abs($assignment->aggregate_score - $panelMean) >= 20 || (bool) $assignment->variance_flag;

            return AdmissionAssessmentNormalizedScore::updateOrCreate(
                ['assignment_id' => $assignment->id],
                [
                    'panel_id' => $assignment->panel_id,
                    'applicant_id' => $assignment->applicant_id,
                    'evaluator_user_id' => $assignment->evaluator_user_id,
                    'raw_score' => $assignment->aggregate_score,
                    'normalized_score' => $normalized,
                    'evaluator_mean' => $evaluatorMean,
                    'panel_mean' => $panelMean,
                    'outlier_flag' => $outlier,
                    'review_status' => $outlier ? 'chair_review' : 'ready',
                    'metadata' => ['v' => '0.037'],
                ]
            );
        });
    }

    public function dashboard(): array
    {
        AdmissionAssessmentPanel::latest()->limit(10)->get()->each(fn ($panel) => $this->normalizePanel($panel));

        return [
            'scores' => AdmissionAssessmentNormalizedScore::with(['panel', 'applicant.user', 'evaluator'])->latest()->paginate(25)->withQueryString(),
            'stats' => [
                'normalized_scores' => AdmissionAssessmentNormalizedScore::count(),
                'outliers' => AdmissionAssessmentNormalizedScore::where('outlier_flag', true)->count(),
                'chair_review' => AdmissionAssessmentNormalizedScore::where('review_status', 'chair_review')->count(),
            ],
        ];
    }
}
