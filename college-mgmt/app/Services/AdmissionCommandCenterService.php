<?php

namespace App\Services;

use App\Models\AdmissionForecastSnapshot;
use App\Models\AdmissionSavedView;
use App\Models\User;

class AdmissionCommandCenterService
{
    public function __construct(
        private AdmissionAttentionService $attention,
        private AdmissionKpiService $kpis,
        private AdmissionCallService $calls,
    ) {}

    public function dashboard(User $user, array $filters = []): array
    {
        return [
            'attentionQueues' => $this->attention->queuesFor($user, $filters),
            'kpiSummary' => $this->kpis->summaryFor($user, $filters),
            'kpiRollup' => $this->kpis->rollupByUser($user, $filters)->take(10),
            'callProductivity' => $this->calls->productivityFor($user),
            'callQueue' => $this->calls->queueFor($user, $filters)->take(15),
            'savedViews' => AdmissionSavedView::where('surface', 'command_center')
                ->where(function ($query) use ($user) {
                    $query->whereNull('user_id')->orWhere('user_id', $user->id);
                })
                ->orderByDesc('is_default')
                ->orderBy('name')
                ->get(),
            'forecast' => AdmissionForecastSnapshot::latest()->first(),
        ];
    }
}
