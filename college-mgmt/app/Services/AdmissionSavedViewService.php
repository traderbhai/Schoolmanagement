<?php

namespace App\Services;

use App\Models\AdmissionSavedView;
use App\Models\User;

class AdmissionSavedViewService
{
    public function save(string $surface, string $name, array $filters, ?User $user = null): AdmissionSavedView
    {
        return AdmissionSavedView::updateOrCreate(
            ['surface' => $surface, 'name' => $name, 'user_id' => $user?->id],
            ['filters' => $filters, 'layout' => ['density' => 'compact'], 'role_name' => $user?->roles?->first()?->name, 'is_default' => false]
        );
    }

    public function forSurface(string $surface, ?User $user = null)
    {
        return AdmissionSavedView::where('surface', $surface)
            ->where(fn ($q) => $q->whereNull('user_id')->orWhere('user_id', $user?->id))
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->get();
    }
}
