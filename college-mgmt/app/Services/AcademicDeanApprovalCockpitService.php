<?php

namespace App\Services;

use App\Models\AcademicDeanApprovalItem;
use App\Models\User;

class AcademicDeanApprovalCockpitService
{
    public function dashboard(): array
    {
        return [
            'items' => AcademicDeanApprovalItem::with('owner')->latest()->paginate(20),
            'pending' => AcademicDeanApprovalItem::where('status', 'pending')->count(),
            'overdue' => AcademicDeanApprovalItem::where('status', 'pending')->where('due_at', '<', now())->count(),
            'high_risk' => AcademicDeanApprovalItem::whereIn('risk_level', ['high', 'critical'])->where('status', 'pending')->count(),
        ];
    }

    public function decide(User $actor, AcademicDeanApprovalItem $item, string $status, ?string $reason): AcademicDeanApprovalItem
    {
        $item->update(['status' => $status, 'decision_reason' => $reason ?: ucfirst($status) . ' by ' . $actor->name]);
        return $item->fresh();
    }
}
