<?php

namespace App\Services;

use App\Models\AcademicDeanActionEvidence;
use App\Models\AcademicDeanActionItem;
use App\Models\User;

class AcademicDeanActionGovernanceService
{
    public function dashboard(): array
    {
        return [
            'actions' => AcademicDeanActionItem::with('owner')->latest()->paginate(20),
            'overdue' => AcademicDeanActionItem::whereNotIn('status', ['done', 'cancelled'])->where('due_at', '<', now())->count(),
            'blocked' => AcademicDeanActionItem::where('status', 'blocked')->count(),
            'evidence_pending' => AcademicDeanActionEvidence::where('verification_status', 'pending')->count(),
        ];
    }

    public function addEvidence(User $actor, AcademicDeanActionItem $action, array $data): AcademicDeanActionEvidence
    {
        return AcademicDeanActionEvidence::create($data + ['action_item_id' => $action->id, 'uploaded_by' => $actor->id]);
    }
}
