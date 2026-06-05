<?php

namespace App\Console\Commands;

use App\Mail\FollowUpReminder;
use App\Models\CounsellingLog;
use App\Services\NotificationService;
use Illuminate\Console\Command;

class SendFollowUpReminders extends Command
{
    protected $signature   = 'notifications:followup-reminders';
    protected $description = 'Send follow-up reminder emails to counsellors for today\'s scheduled follow-ups';

    public function handle(): int
    {
        $today = today()->toDateString();

        $logs = CounsellingLog::with(['applicant.user', 'applicant.program', 'loggedBy'])
            ->whereDate('next_followup_date', $today)
            ->whereNotIn('outcome', ['enrolled', 'lost'])
            ->get();

        $sent = 0;

        foreach ($logs as $log) {
            $counsellor = $log->loggedBy;
            if (! $counsellor) {
                continue;
            }

            NotificationService::send(FollowUpReminder::class, $counsellor, [
                'applicant'  => $log->applicant,
                'log'        => $log,
                'counsellor' => $counsellor,
            ]);

            $sent++;
        }

        $this->info("Sent {$sent} follow-up reminder(s).");

        return self::SUCCESS;
    }
}
