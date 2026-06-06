<?php
namespace App\Console\Commands;

use App\Models\ApplicationWindow;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Console\Command;

class SendApplicationDeadlineReminders extends Command
{
    protected $signature   = 'admission:deadline-reminders';
    protected $description = 'Notify admission team of application windows closing within 3 days';

    public function handle(): void
    {
        $windowsClosingSoon = ApplicationWindow::where('is_active', true)
            ->whereBetween('closes_at', [now(), now()->addDays(3)])
            ->with('program')
            ->get();

        if ($windowsClosingSoon->isEmpty()) {
            $this->info('No windows closing in the next 3 days.');
            return;
        }

        // Notify all admission officers and head
        $recipients = User::role(['admission_officer', 'admission_head'])->get();

        foreach ($windowsClosingSoon as $window) {
            $hoursLeft = (int) now()->diffInHours($window->closes_at);
            $message = "Application window for {$window->program->name}"
                . ($window->batch ? " ({$window->batch->name})" : '')
                . " closes in {$hoursLeft} hours."
                . ($window->capacity_limit
                    ? " {$window->current_applications}/{$window->capacity_limit} seats filled."
                    : '');

            foreach ($recipients as $user) {
                Notification::create([
                    'user_id' => $user->id,
                    'title'   => 'Application Window Closing Soon',
                    'message' => $message,
                    'type'    => 'deadline_reminder',
                    'read_at' => null,
                ]);
            }
        }

        $this->info("Sent deadline reminders for {$windowsClosingSoon->count()} window(s).");
    }
}
