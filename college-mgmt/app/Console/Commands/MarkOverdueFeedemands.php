<?php
namespace App\Console\Commands;

use App\Models\FeeDemand;
use App\Models\Notification;
use Illuminate\Console\Command;

class MarkOverdueFeedemands extends Command
{
    protected $signature   = 'accounts:mark-overdue-demands';
    protected $description = 'Mark pending fee demands as overdue and notify affected students';

    public function handle(): void
    {
        $overdue = FeeDemand::where('status', 'pending')
            ->where('due_date', '<', now()->toDateString())
            ->with('student.user')
            ->get();

        if ($overdue->isEmpty()) {
            $this->info('No overdue fee demands found.');
            return;
        }

        foreach ($overdue as $demand) {
            $demand->update(['status' => 'overdue']);

            if ($demand->student?->user_id) {
                Notification::create([
                    'user_id' => $demand->student->user_id,
                    'title'   => 'Fee Payment Overdue',
                    'message' => 'Your fee payment of ₹' . number_format($demand->final_amount, 2)
                        . ' for ' . ($demand->term->name ?? 'the current term')
                        . ' was due on ' . $demand->due_date->format('d M Y')
                        . '. Please pay immediately to avoid penalty.',
                    'type'    => 'warning',
                ]);
            }
        }

        $this->info("Marked {$overdue->count()} demands as overdue and notified students.");
    }
}
