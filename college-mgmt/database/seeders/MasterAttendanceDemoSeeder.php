<?php

namespace Database\Seeders;

use App\Models\Attendance;
use App\Models\Program;
use App\Models\Student;
use App\Models\TimetableEntry;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class MasterAttendanceDemoSeeder extends Seeder
{
    public function run(): void
    {
        try {
            $this->command?->info('Seeding Section 2: Attendance...');

            $students = Student::with('user')->where('status', 'active')->orderBy('id')->get();
            $pgdm = Program::where('code', 'PGDM')->first();
            $timetableEntries = TimetableEntry::with(['subject', 'teacher'])->where('program_id', $pgdm?->id)->get();

            $entryTeacherMap = [];
            foreach ($timetableEntries as $entry) {
                $entryTeacherMap[$entry->id] = $entry->teacher_id;
            }

            $existingKeys = Attendance::select('student_id', 'timetable_entry_id', 'date')
                ->get()
                ->map(fn ($a) => $a->student_id . '-' . $a->timetable_entry_id . '-' . Carbon::parse($a->date)->toDateString())
                ->flip()
                ->toArray();

            $newRecords = [];
            $now = now()->toDateTimeString();

            foreach ($students as $student) {
                foreach ($entryTeacherMap as $entryId => $teacherId) {
                    for ($daysAgo = 45; $daysAgo >= 1; $daysAgo--) {
                        $date = Carbon::today()->subDays($daysAgo);
                        if ($date->isWeekend()) {
                            continue;
                        }

                        $key = $student->id . '-' . $entryId . '-' . $date->toDateString();
                        if (isset($existingKeys[$key])) {
                            continue;
                        }

                        $seed = crc32($key) & 0x7FFFFFFF;
                        $val = $seed % 100;
                        $status = $val < 80 ? 'present' : ($val < 95 ? 'absent' : 'late');

                        $newRecords[] = [
                            'student_id' => $student->id,
                            'timetable_entry_id' => $entryId,
                            'date' => $date->toDateString(),
                            'status' => $status,
                            'marked_by' => $teacherId,
                            'remarks' => $status === 'late' ? 'Arrived 10 mins late' : null,
                            'created_at' => $now,
                            'updated_at' => $now,
                        ];
                        $existingKeys[$key] = true;

                        if (count($newRecords) >= 500) {
                            Attendance::insert($newRecords);
                            $newRecords = [];
                        }
                    }
                }
            }

            if (! empty($newRecords)) {
                Attendance::insert($newRecords);
            }

            $this->command?->info('✓ Section 2 (Attendance) done');
        } catch (\Throwable $e) {
            $this->command?->warn('Section 2 failed: ' . $e->getMessage());
        }
    }
}
