<?php
namespace App\Console\Commands;

use App\Models\LeadFollowUp;
use App\Models\Notification;
use Illuminate\Console\Command;

class SendFollowUpReminders extends Command
{
    protected $signature   = 'admission:followup-reminders';
    protected $description = 'Notify counsellors of lead follow-ups scheduled for today';

    public function handle(): void
    {
        $dueToday = LeadFollowUp::whereNull('completed_at')
            ->whereDate('scheduled_at', today())
            ->with(['lead', 'counsellor'])
            ->get();

        if ($dueToday->isEmpty()) {
            $this->info('No follow-ups due today.');
            return;
        }

        // Group by counsellor
        $byUser = $dueToday->groupBy('assigned_to');

        foreach ($byUser as $userId => $followUps) {
            if (!$userId) continue;
            $user = $followUps->first()->counsellor;
            if (!$user) continue;

            $count = $followUps->count();
            Notification::create([
                'user_id' => $userId,
                'title'   => "You have {$count} follow-up(s) due today",
                'message' => "Scheduled follow-ups for today: "
                    . $followUps->map(fn($f) => $f->lead->name . ' (' . $f->type_label . ')')->join(', '),
                'type'    => 'followup_reminder',
                'read_at' => null,
            ]);
        }

        $this->info("Sent follow-up reminders to {$byUser->count()} counsellor(s) for {$dueToday->count()} follow-up(s).");
    }
}
