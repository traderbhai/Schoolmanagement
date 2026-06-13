<?php

namespace App\Services;

use App\Models\AdmissionCommunicationTemplate;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class AdmissionTemplateApprovalService
{
    public function request(AdmissionCommunicationTemplate $template, ?User $actor = null): int
    {
        $version = (int) DB::table('admission_template_approvals')->where('template_id', $template->id)->max('version') + 1;

        return DB::table('admission_template_approvals')->insertGetId([
            'template_id' => $template->id,
            'version' => $version,
            'status' => 'draft',
            'requested_by' => $actor?->id,
            'snapshot' => json_encode($template->only(['name', 'channel', 'purpose', 'subject', 'body', 'variables'])),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function approve(int $approvalId, User $actor): void
    {
        DB::table('admission_template_approvals')->where('id', $approvalId)->update([
            'status' => 'approved',
            'reviewed_by' => $actor->id,
            'reviewed_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function isApproved(AdmissionCommunicationTemplate $template): bool
    {
        return DB::table('admission_template_approvals')
            ->where('template_id', $template->id)
            ->where('status', 'approved')
            ->exists();
    }
}
