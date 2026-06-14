<?php

namespace App\Services;

use App\Models\AcademicDeanSavedView;
use App\Models\User;

class AcademicDeanSavedViewService
{
    public function list(User $user, string $surface)
    {
        return AcademicDeanSavedView::where(fn ($q) => $q->where('user_id', $user->id)->orWhereNull('user_id'))
            ->where('surface', $surface)
            ->latest()
            ->get();
    }

    public function save(User $user, array $data): AcademicDeanSavedView
    {
        if (! empty($data['is_default'])) {
            AcademicDeanSavedView::where('user_id', $user->id)->where('surface', $data['surface'])->update(['is_default' => false]);
        }

        return AcademicDeanSavedView::updateOrCreate(
            ['user_id' => $user->id, 'surface' => $data['surface'], 'name' => $data['name']],
            ['filters' => $data['filters'] ?? [], 'is_default' => (bool) ($data['is_default'] ?? false)]
        );
    }
}
