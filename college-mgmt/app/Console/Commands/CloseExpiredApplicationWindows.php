<?php
namespace App\Console\Commands;

use App\Models\ApplicationWindow;
use Illuminate\Console\Command;

class CloseExpiredApplicationWindows extends Command
{
    protected $signature   = 'admission:close-expired-windows';
    protected $description = 'Automatically deactivate application windows past their closes_at date';

    public function handle(): void
    {
        $expired = ApplicationWindow::where('is_active', true)
            ->where('closes_at', '<', now())
            ->get();

        if ($expired->isEmpty()) {
            $this->info('No expired windows to close.');
            return;
        }

        $count = $expired->count();
        $expired->each(fn($w) => $w->update(['is_active' => false]));

        $this->info("Closed {$count} expired application window(s).");
    }
}
