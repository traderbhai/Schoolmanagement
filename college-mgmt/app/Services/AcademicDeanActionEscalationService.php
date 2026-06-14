<?php

namespace App\Services;

use App\Models\AcademicDeanActionItem;

class AcademicDeanActionEscalationService
{
    public function dueEscalations()
    {
        return AcademicDeanActionItem::with('owner')
            ->whereNotIn('status', ['done', 'cancelled'])
            ->where('due_at', '<', now())
            ->orderBy('due_at')
            ->get()
            ->map(fn ($action) => [
                'title' => $action->title,
                'owner' => $action->owner?->name,
                'priority' => $action->priority,
                'due_at' => $action->due_at,
                'route' => route('academics.dean-os.actions.index'),
            ]);
    }
}
