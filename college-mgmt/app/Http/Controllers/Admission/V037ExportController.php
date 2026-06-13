<?php

namespace App\Http\Controllers\Admission;

use App\Http\Controllers\Controller;
use App\Models\AdmissionAssessmentNormalizedScore;
use App\Models\AdmissionCounsellorCoachingNote;
use App\Models\AdmissionRouteAccessAudit;
use App\Models\AdmissionAutomationConflictLog;
use App\Services\AdmissionExportService;
use Illuminate\Http\Request;

class V037ExportController extends Controller
{
    public function __invoke(string $type, Request $request, AdmissionExportService $service)
    {
        $rows = match ($type) {
            'normalization' => AdmissionAssessmentNormalizedScore::limit(500)->get()->map(fn ($s) => ['panel_id' => $s->panel_id, 'applicant_id' => $s->applicant_id, 'raw_score' => $s->raw_score, 'normalized_score' => $s->normalized_score, 'outlier' => $s->outlier_flag])->all(),
            'coaching' => AdmissionCounsellorCoachingNote::limit(500)->get()->map(fn ($n) => ['counsellor_id' => $n->counsellor_user_id, 'band' => $n->score_band, 'status' => $n->status])->all(),
            'route-access' => AdmissionRouteAccessAudit::limit(500)->get()->map(fn ($a) => ['route' => $a->route_name, 'method' => $a->method, 'risk' => $a->risk_level, 'scope' => $a->required_scope])->all(),
            default => AdmissionAutomationConflictLog::limit(500)->get()->map(fn ($c) => ['subject' => class_basename($c->subject_type), 'conflict' => $c->conflict_key, 'status' => $c->status])->all(),
        };

        return $service->csv('admission-v037', $type, $rows, $request->query(), $request->user());
    }
}
