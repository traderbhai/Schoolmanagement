<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\TimetableCanonicalRepairService;
use Illuminate\Console\Command;

class RepairPmcCanonicalTimetable extends Command
{
    protected $signature = 'academics:pmc-repair-canonical-timetable {--run-id= : Limit repair to one generation run} {--title= : Limit repair to one generation run title}';

    protected $description = 'Backfill canonical PMC timetable identity for published generation runs without changing unscheduled diagnostics.';

    public function handle(TimetableCanonicalRepairService $service): int
    {
        $actor = $this->systemActor();
        $result = $service->repairPublishedRunItems($actor, [
            'run_id' => $this->option('run-id'),
            'title' => $this->option('title'),
        ]);

        $this->info("Canonical timetable repair inspected {$result['inspected']} item(s).");
        $this->line("Repaired: {$result['repaired']}");
        $this->line("Official published sessions: {$result['published']}");
        $this->line("Draft diagnostics: {$result['draft']}");

        return self::SUCCESS;
    }

    private function systemActor(): ?User
    {
        return User::where('email', 'admin@college.com')->first()
            ?: User::where('email', 'admin@demo.edu')->first()
            ?: User::whereHas('roles', fn ($query) => $query->whereIn('name', ['admin', 'dean_academics', 'academic_department_owner', 'pmc_head']))->first()
            ?: User::first();
    }
}
