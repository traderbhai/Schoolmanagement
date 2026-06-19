<?php

namespace App\Services;

use App\Models\AcademicDeanApprovalItem;
use App\Models\User;

class AcademicDeanApprovalCockpitService
{
    private const FINAL_STATUSES = ['approved', 'rejected', 'returned', 'escalated', 'cancelled'];

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
        abort_if(in_array($item->status, self::FINAL_STATUSES, true), 422, 'Finalized Dean approval items cannot be changed. Create a new approval request or escalation.');
        abort_unless(in_array($status, ['approved', 'rejected', 'returned', 'requested_evidence', 'escalated'], true), 422, 'Unsupported Dean approval decision.');
        abort_if(in_array($status, ['rejected', 'returned', 'escalated'], true) && blank($reason), 422, 'A decision reason is required for rejected, returned, or escalated approval items.');

        $item->update(['status' => $status, 'decision_reason' => $reason ?: ucfirst($status) . ' by ' . $actor->name]);
        return $item->fresh();
    }
}
