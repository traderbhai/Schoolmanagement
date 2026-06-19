<?php

namespace App\Services;

use App\Models\AcademicDeanActionItem;
use App\Models\Attendance;
use App\Models\CourseFeedback;
use App\Models\Exam;
use App\Models\ExamResult;
use App\Models\Program;
use App\Models\Subject;
use App\Models\SubjectFacultyAssignment;
use App\Models\TimetableEntry;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class AcademicDeanRiskService
{
    public function programRisks(): Collection
    {
        $assignedSubjects = SubjectFacultyAssignment::query()->pluck('subject_id');
        $subjectsWithFeedback = CourseFeedback::query()->pluck('subject_id');

        return Program::where('is_active', true)
            ->withCount(['students' => fn ($q) => $q->where('status', 'active'), 'subjects'])
            ->orderBy('name')
            ->get()
            ->map(function (Program $program) use ($assignedSubjects, $subjectsWithFeedback) {
                $studentIds = $program->students()->pluck('id');
                $subjectIds = $program->subjects()->pluck('id');
                $attendanceExceptions = Attendance::whereIn('student_id', $studentIds)->whereIn('status', ['absent', 'late'])->count();
                $failedResults = ExamResult::whereIn('student_id', $studentIds)
                    ->whereHas('exam', fn ($q) => $q
                        ->whereNotNull('published_at')
                        ->whereColumn('exam_results.marks_obtained', '<', 'exams.passing_marks'))
                    ->count();
                $facultyGaps = Subject::whereIn('id', $subjectIds)->whereNotIn('id', $assignedSubjects)->count();
                $draftTimetable = TimetableEntry::where('program_id', $program->id)->where('is_active', true)->where('status', '!=', 'published')->count();
                $examBlocks = Exam::where('program_id', $program->id)->where('exam_date', '>=', now())->whereNull('classroom_id')->count();
                $feedbackGaps = Subject::whereIn('id', $subjectIds)->whereNotIn('id', $subjectsWithFeedback)->count();
                $handoffBlocks = DB::getSchemaBuilder()->hasTable('admission_handoff_records')
                    ? DB::table('admission_handoff_records')
                        ->join('applicants', 'applicants.id', '=', 'admission_handoff_records.applicant_id')
                        ->where('applicants.program_id', $program->id)
                        ->whereIn('admission_handoff_records.status', ['blocked', 'returned_for_correction', 'pending_admission_completion'])
                        ->count()
                    : 0;
                $openActions = AcademicDeanActionItem::whereNotIn('status', ['done', 'cancelled'])
                    ->whereJsonContains('metadata->program_id', $program->id)
                    ->count();

                $score = min(100, ($attendanceExceptions * 4) + ($failedResults * 8) + ($facultyGaps * 12) + ($draftTimetable * 8) + ($examBlocks * 12) + ($feedbackGaps * 5) + ($handoffBlocks * 15) + ($openActions * 6));
                $band = $score >= 70 ? 'critical' : ($score >= 40 ? 'high' : ($score >= 20 ? 'medium' : 'low'));
                $reasons = collect([
                    $attendanceExceptions ? "{$attendanceExceptions} attendance exceptions" : null,
                    $failedResults ? "{$failedResults} weak results" : null,
                    $facultyGaps ? "{$facultyGaps} faculty gaps" : null,
                    $draftTimetable ? "{$draftTimetable} timetable gaps" : null,
                    $examBlocks ? "{$examBlocks} exam blocks" : null,
                    $feedbackGaps ? "{$feedbackGaps} feedback gaps" : null,
                    $handoffBlocks ? "{$handoffBlocks} handoff blockers" : null,
                    $openActions ? "{$openActions} open Dean actions" : null,
                ])->filter()->values();

                return [
                    'program' => $program,
                    'score' => $score,
                    'band' => $band,
                    'reasons' => $reasons,
                    'metrics' => compact('attendanceExceptions', 'failedResults', 'facultyGaps', 'draftTimetable', 'examBlocks', 'feedbackGaps', 'handoffBlocks', 'openActions'),
                    'route' => route('academics.dean-os.program-risk', ['program_id' => $program->id]),
                ];
            });
    }
}
