<?php
namespace App\Services;
use App\Models\ApprovalWorkflow;

class ApprovalWorkflowService {
    public const ESCALATION_CHAIN = ['hod'=>'dean_academics','program_chair'=>'dean_academics','dean_academics'=>'admin'];
    public const DEFAULT_SLA = ['hod'=>3,'program_chair'=>3,'dean_academics'=>5,'admin'=>7];

    public function create(array $data): ApprovalWorkflow {
        $role = $data['approver_role'];
        $slaDays = self::DEFAULT_SLA[$role] ?? 3;
        return ApprovalWorkflow::create(array_merge($data, [
            'status'=>'pending', 'sla_days'=>$slaDays, 'due_at'=>now()->addDays($slaDays),
        ]));
    }

    public function escalateOverdue(): int {
        $count = 0;
        $overdue = ApprovalWorkflow::where('status','pending')
            ->whereNotNull('due_at')->where('due_at','<',now())
            ->whereNull('escalated_at')->get();
        foreach ($overdue as $approval) {
            $nextRole = self::ESCALATION_CHAIN[$approval->approver_role] ?? null;
            if ($nextRole) {
                $approval->update(['escalated_to_role'=>$nextRole,'escalated_at'=>now()]);
                ApprovalWorkflow::create([
                    'approvable_type'=>$approval->approvable_type,'approvable_id'=>$approval->approvable_id,
                    'approver_role'=>$nextRole,'status'=>'pending',
                    'sla_days'=>self::DEFAULT_SLA[$nextRole] ?? 5,
                    'due_at'=>now()->addDays(self::DEFAULT_SLA[$nextRole] ?? 5),
                    'remarks'=>"Escalated from {$approval->approver_role} (overdue)",
                ]);
                $count++;
            }
        }
        return $count;
    }

    public function getOverdue(): \Illuminate\Database\Eloquent\Collection {
        return ApprovalWorkflow::where('status','pending')
            ->whereNotNull('due_at')->where('due_at','<',now())
            ->with(['approvable'])->orderBy('due_at')->get();
    }
}
