<?php

namespace Database\Seeders;

use App\Models\Enrollment;
use App\Models\Exam;
use App\Models\ExamResult;
use App\Models\Program;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Term;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class MasterExamResultDemoSeeder extends Seeder
{
    public function run(): void
    {
        try {
            $this->command?->info('Seeding Section 3: Exams & Results...');

            $students = Student::with('user')->where('status', 'active')->orderBy('id')->get();
            $subjects = Subject::orderBy('id')->get();
            $pgdm = Program::where('code', 'PGDM')->first();
            $currentTerm = Term::where('is_current', true)->first();

            if (! $currentTerm || $students->isEmpty()) {
                return;
            }

            $examSubjects = Subject::whereIn('id', Enrollment::where('term_id', $currentTerm->id)->distinct()->pluck('subject_id'))->get();
            if ($examSubjects->isEmpty()) {
                $examSubjects = $subjects->take(4);
            }

            $gradeFromMarks = function (int $marks, int $max): string {
                $pct = ($marks / $max) * 100;
                if ($pct >= 80) {
                    return 'A';
                }
                if ($pct >= 60) {
                    return 'B';
                }
                if ($pct >= 50) {
                    return 'C';
                }
                if ($pct >= 40) {
                    return 'D';
                }

                return 'F';
            };

            foreach ($examSubjects as $subject) {
                $examDefs = [
                    ['name' => 'IA1 - ' . $subject->code, 'type' => 'internal', 'total_marks' => 30, 'passing_marks' => 12, 'exam_date' => Carbon::today()->subDays(60)->toDateString(), 'range_min' => 15, 'range_max' => 28, 'fail_count' => 0],
                    ['name' => 'IA2 - ' . $subject->code, 'type' => 'internal', 'total_marks' => 30, 'passing_marks' => 12, 'exam_date' => Carbon::today()->subDays(30)->toDateString(), 'range_min' => 14, 'range_max' => 28, 'fail_count' => 0],
                    ['name' => 'End-Sem - ' . $subject->code, 'type' => 'final', 'total_marks' => 100, 'passing_marks' => 40, 'exam_date' => Carbon::today()->subDays(7)->toDateString(), 'range_min' => 40, 'range_max' => 90, 'fail_count' => 2],
                ];

                foreach ($examDefs as $def) {
                    static $firstSemId = null;
                    if (! $firstSemId) {
                        $firstSemId = \App\Models\Semester::orderBy('id')->value('id') ?? 1;
                    }

                    $exam = Exam::firstOrCreate(
                        ['name' => $def['name'], 'subject_id' => $subject->id],
                        [
                            'semester_id' => $firstSemId,
                            'program_id' => $pgdm?->id,
                            'term_id' => $currentTerm->id,
                            'type' => $def['type'],
                            'total_marks' => $def['total_marks'],
                            'passing_marks' => $def['passing_marks'],
                            'exam_date' => $def['exam_date'],
                        ]
                    );

                    $failsGiven = 0;
                    foreach ($students as $idx => $student) {
                        $marks = rand($def['range_min'], $def['range_max']);
                        if ($def['fail_count'] > 0 && $failsGiven < $def['fail_count'] && $idx < 3) {
                            $marks = rand(20, 38);
                            $failsGiven++;
                        }

                        ExamResult::firstOrCreate(
                            ['exam_id' => $exam->id, 'student_id' => $student->id],
                            [
                                'marks_obtained' => $marks,
                                'grade' => $gradeFromMarks($marks, $def['total_marks']),
                                'is_absent' => false,
                                'remarks' => null,
                            ]
                        );
                    }
                }
            }

            $this->command?->info('✓ Section 3 (Exams & Results) done');
        } catch (\Throwable $e) {
            $this->command?->warn('Section 3 failed: ' . $e->getMessage());
        }
    }
}
