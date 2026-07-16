<?php

namespace App\Console\Commands;

use App\Models\AcademicYear;
use App\Models\Batch;
use App\Models\Semester;
use App\Models\Student;
use App\Models\Term;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class RepairDemoAcademicCalendar extends Command
{
    protected $signature = 'demo:repair-academic-calendar';

    protected $description = 'Roll local demo academic years, semesters, and PGDM terms forward for current-date testing.';

    public function handle(): int
    {
        $today = Carbon::today();
        $sessionStartYear = $today->month >= 7 ? $today->year : $today->year - 1;
        $sessionEndYear = $sessionStartYear + 1;
        $sessionLabel = $sessionStartYear . '-' . substr((string) $sessionEndYear, -2);
        $previousLabel = ($sessionStartYear - 1) . '-' . substr((string) $sessionStartYear, -2);

        DB::transaction(function () use ($sessionStartYear, $sessionEndYear, $sessionLabel, $previousLabel) {
            AcademicYear::where('is_current', true)->update(['is_current' => false]);
            Semester::where('is_current', true)->update(['is_current' => false]);

            AcademicYear::updateOrCreate(['name' => $previousLabel], [
                'start_year' => $sessionStartYear - 1,
                'end_year' => $sessionStartYear,
                'start_date' => ($sessionStartYear - 1) . '-06-01',
                'end_date' => $sessionStartYear . '-05-31',
                'is_current' => false,
            ]);

            $academicYear = AcademicYear::updateOrCreate(['name' => $sessionLabel], [
                'start_year' => $sessionStartYear,
                'end_year' => $sessionEndYear,
                'start_date' => $sessionStartYear . '-06-01',
                'end_date' => $sessionEndYear . '-05-31',
                'is_current' => true,
            ]);

            $semesterOne = Semester::updateOrCreate(['name' => "Semester I ({$sessionLabel})"], [
                'academic_year_id' => $academicYear->id,
                'number' => 1,
                'start_date' => $sessionStartYear . '-07-01',
                'end_date' => $sessionStartYear . '-11-30',
                'is_current' => true,
            ]);

            Semester::updateOrCreate(['name' => "Semester II ({$sessionLabel})"], [
                'academic_year_id' => $academicYear->id,
                'number' => 2,
                'start_date' => $sessionEndYear . '-01-01',
                'end_date' => $sessionEndYear . '-05-31',
                'is_current' => false,
            ]);

            $batch = Batch::where('code', 'PGDM-24')->first();
            if (! $batch) {
                return;
            }

            $batch->update([
                'academic_year_id' => $academicYear->id,
                'name' => "PGDM Batch {$sessionStartYear}-" . substr((string) ($sessionStartYear + 2), -2),
                'start_date' => $sessionStartYear . '-07-01',
                'end_date' => ($sessionStartYear + 2) . '-06-30',
                'status' => 'active',
            ]);

            Term::where('batch_id', $batch->id)->where('is_current', true)->update(['is_current' => false]);

            $termDates = [
                1 => [$sessionStartYear . '-07-01', $sessionStartYear . '-11-30', true],
                2 => [$sessionEndYear . '-01-01', $sessionEndYear . '-05-31', false],
                3 => [$sessionEndYear . '-07-01', $sessionEndYear . '-11-30', false],
                4 => [($sessionEndYear + 1) . '-01-01', ($sessionEndYear + 1) . '-05-31', false],
            ];

            $currentTerm = null;
            foreach ($termDates as $termNumber => [$startDate, $endDate, $isCurrent]) {
                $term = Term::updateOrCreate([
                    'batch_id' => $batch->id,
                    'term_number' => $termNumber,
                ], [
                    'program_id' => $batch->program_id,
                    'name' => 'Semester ' . ['I', 'II', 'III', 'IV'][$termNumber - 1],
                    'start_date' => $startDate,
                    'end_date' => $endDate,
                    'is_current' => $isCurrent,
                    'sort_order' => $termNumber,
                ]);

                if ($isCurrent) {
                    $currentTerm = $term;
                }
            }

            if ($currentTerm) {
                Student::where('batch_id', $batch->id)->update([
                    'current_semester' => $semesterOne->number,
                    'current_term' => $currentTerm->term_number,
                    'current_term_id' => $currentTerm->id,
                ]);
            }
        });

        $this->info("Demo academic calendar repaired for {$sessionLabel}.");

        return self::SUCCESS;
    }
}
