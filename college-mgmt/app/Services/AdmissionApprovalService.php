<?php

namespace App\Services;

use App\Models\AdmissionApproval;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class AdmissionApprovalService
{
    public function __construct(private AdmissionAccessPolicyService $accessPolicy) {}

    public function request(Model $subject, string $action, User $requester, array $after = [], ?string $reason = null): AdmissionApproval
    {
        return AdmissionApproval::create([
            'approvable_type' => get_class($subject),
            'approvable_id' => $subject->id,
            'action' => $action,
            'status' => 'pending',
            'before' => $subject->getAttributes(),
            'after' => $after,
            'reason' => $reason,
            'requested_by' => $requester->id,
            'metadata' => [
                'ip_address' => request()?->ip(),
                'user_agent' => request()?->userAgent(),
            ],
        ]);
    }

    public function approve(AdmissionApproval $approval, User $approver): AdmissionApproval
    {
        $this->accessPolicy->authorizeApproveAdmission($approver);
        abort_unless($approval->status === 'pending', 422, 'Approval is not pending.');

        $subject = $approval->approvable;
        if ($subject && is_array($approval->after)) {
            $subject->fill($approval->after)->save();
        }

        $approval->update(['status' => 'approved', 'approved_by' => $approver->id, 'approved_at' => now()]);

        return $approval->fresh();
    }

    public function reject(AdmissionApproval $approval, User $approver, ?string $reason = null): AdmissionApproval
    {
        $this->accessPolicy->authorizeApproveAdmission($approver);
        abort_unless($approval->status === 'pending', 422, 'Approval is not pending.');
        $approval->update([
            'status' => 'rejected',
            'approved_by' => $approver->id,
            'approved_at' => now(),
            'reason' => $reason ?: $approval->reason,
        ]);

        return $approval->fresh();
    }
}
