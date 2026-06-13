<?php
namespace App\Console\Commands;

use App\Services\LibraryFineService;
use Illuminate\Console\Command;

class ApplyLibraryFines extends Command
{
    protected $signature   = 'library:apply-fines';
    protected $description = 'Calculate and apply overdue fines to all overdue library books';

    public function handle(LibraryFineService $service): int
    {
        $count = $service->applyOverdueFines();
        $this->info("Applied fines to {$count} overdue issues.");
        return 0;
    }
}
