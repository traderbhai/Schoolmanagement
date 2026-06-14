<?php

namespace App\Services;

use App\Models\AcademicDeanActionItem;
use App\Models\Attendance;
use App\Models\CourseFeedback;
use App\Models\CurriculumChange;
use App\Models\Exam;
use App\Models\ExamRegistration;
use App\Models\ExamResult;
use App\Models\MarksAppeal;
use App\Models\Program;
use App\Models\Student;
use App\Models\Subject;
use App\Models\SubjectFacultyAssignment;
use App\Models\TimetableEntry;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class AcademicDeanAttentionService
{
    public function queues(): array
    {
        $queues = [
            'overdue_dean_approvals' => $this->overdueDeanApprovals(),
            'pending_dean_approvals' => $this->pendingDeanApprovals(),
            'attendance_risk' => $this->attendanceRisk(),
            'weak_academic_performance' => $this->weakPerformance(),
            'curriculum_approval_delay' => $this->curriculumDelays(),
            'faculty_allocation_gaps' => $this->facultyGaps(),
            'timetable_publish_gaps' => $this->timetableGaps(),
            'exam_readiness_blocks' => $this->examReadinessBlocks(),
            'marks_result_pending' => $this->marksPending(),
            'hall_ticket_blocks' => $this->hallTicketBlocks(),
            'obe_mapping_gaps' => $this->obeGaps(),
            'low_feedback_subjects' => $this->lowFeedback(),
            'admission_handoff_blockers' => $this->handoffBlockers(),
            'action_items_overdue' => $this->overdueActions(),
        ];

        return collect($queues)->map(fn (Collection $items, string $key) => [
            'key' => $key,
            'label' => str($key)->replace('_', ' ')->title()->toString(),
            'count' => $items->count(),
            'items' => $items->values(),
            'route' => route('academics.dean-os.attention', $key),
        ])->all();
    }

    public function queue(string $key): array
    {
        $queues = $this->queues();
        abort_unless(isset($queues[$key]), 404);

        return $queues[$key];
    }

    public function criticalItems(int $limit = 8): Collection
    {
        return collect($this->queues())
            ->flatMap(fn ($queue) => $queue['items'])
            ->sortBy(fn ($item) => ['critical' => 0, 'high' => 1, 'medium' => 2, 'low' => 3][$item['severity']] ?? 4)
            ->take($limit)
            ->values();
    }

    private function item(string $title, string $subtitle, string $severity, string $sourceType, ?string $owner, ?string $due, string $route, string $action): array
    {
        return compact('title', 'subtitle', 'severity', 'sourceType', 'owner', 'due', 'route', 'action');
    }

    private function overdueDeanApprovals(): Collection
    {
        return DB::table('approval_workflows')
            ->where('approver_role', 'dean_academics')
            ->where('status', 'pending')
            ->whereNotNull('due_at')
            ->where('due_at', '<', now())
            ->latest('due_at')
            ->limit(25)
            ->get()
            ->map(fn ($row) => $this->item('Overdue Dean approval #' . $row->id, class_basename($row->approvable_type) . ' #' . $row->approvable_id, 'critical', 'approval', 'Dean Academics', $row->due_at, route('dean.approvals'), 'Review approval now'));
    }

    private function pendingDeanApprovals(): Collection
    {
        return DB::table('approval_workflows')
            ->where('approver_role', 'dean_academics')
            ->where('status', 'pending')
            ->latest()
            ->limit(25)
            ->get()
            ->map(fn ($row) => $this->item('Pending Dean approval #' . $row->id, class_basename($row->approvable_type) . ' #' . $row->approvable_id, 'high', 'approval', 'Dean Academics', $row->due_at, route('dean.approvals'), 'Approve or reject'));
    }

    private function attendanceRisk(): Collection
    {
        return Attendance::with('student.user')
            ->selectRaw('student_id, count(*) as exception_count')
            ->where('date', '>=', now()->subDays(30))
            ->whereIn('status', ['absent', 'late'])
            ->groupBy('student_id')
            ->having('exception_count', '>=', 2)
            ->limit(25)
            ->get()
            ->map(fn ($row) => $this->item($row->student?->user?->name ?? 'Student #' . $row->student_id, $row->exception_count . ' attendance exceptions in last 30 days', 'high', 'attendance', 'Program team', null, route('dean.attendance'), 'Assign intervention'));
    }

    private function weakPerformance(): Collection
    {
        return ExamResult::with(['student.user', 'exam.subject'])
            ->whereHas('exam', fn ($query) => $query->whereColumn('exam_results.marks_obtained', '<', 'exams.passing_marks'))
            ->limit(25)
            ->get()
            ->map(fn (ExamResult $result) => $this->item($result->student?->user?->name ?? 'Student #' . $result->student_id, ($result->exam?->subject?->code ?? 'Exam') . ' below pass mark', 'high', 'program', 'Program Director', null, route('dean.academics'), 'Review performance support'));
    }

    private function curriculumDelays(): Collection
    {
        return CurriculumChange::with('program')
            ->whereIn('status', ['submitted', 'under_review'])
            ->latest('submitted_at')
            ->limit(25)
            ->get()
            ->map(fn (CurriculumChange $change) => $this->item($change->title, ($change->program?->code ?? 'Program') . ' curriculum approval pending', $change->submitted_at && $change->submitted_at->lt(now()->subDays(3)) ? 'high' : 'medium', 'pmc', 'PMC Head', $change->submitted_at?->addDays(3)?->toDateString(), route('academic.curriculum-changes.index'), 'Review curriculum change'));
    }

    private function facultyGaps(): Collection
    {
        $assigned = SubjectFacultyAssignment::query()->pluck('subject_id');

        return Subject::with('program')
            ->where('is_active', true)
            ->whereNotIn('id', $assigned)
            ->limit(25)
            ->get()
            ->map(fn (Subject $subject) => $this->item($subject->name, ($subject->program?->code ?? 'Program') . ' has no faculty assigned', 'high', 'pmc', 'PMC Manager', null, route('academics.pmc.faculty-allocation'), 'Assign faculty'));
    }

    private function timetableGaps(): Collection
    {
        return TimetableEntry::with('subject.program')
            ->where('is_active', true)
            ->where('status', '!=', 'published')
            ->limit(25)
            ->get()
            ->map(fn (TimetableEntry $entry) => $this->item($entry->subject?->name ?? 'Timetable entry', ($entry->subject?->program?->code ?? 'Program') . ' timetable not published', 'medium', 'pmc', 'PMC Officer', null, route('academics.pmc.timetable-readiness'), 'Publish or fix timetable'));
    }

    private function examReadinessBlocks(): Collection
    {
        return Exam::with(['program', 'subject'])
            ->where('exam_date', '>=', now())
            ->whereNull('classroom_id')
            ->limit(25)
            ->get()
            ->map(fn (Exam $exam) => $this->item($exam->name, ($exam->program?->code ?? 'Program') . ' room/resource pending', 'high', 'coe', 'CoE', $exam->exam_date?->toDateString(), route('academics.coe.exam-readiness'), 'Resolve exam readiness'));
    }

    private function marksPending(): Collection
    {
        return Exam::with(['program', 'subject'])
            ->withCount('results')
            ->where('exam_date', '<=', now())
            ->get()
            ->filter(fn (Exam $exam) => (int) $exam->results_count === 0)
            ->take(25)
            ->map(fn (Exam $exam) => $this->item($exam->name, ($exam->subject?->code ?? 'Subject') . ' marks not entered', 'high', 'coe', 'Exam Manager', null, route('academics.coe.marks-results'), 'Follow up marks entry'));
    }

    private function hallTicketBlocks(): Collection
    {
        return ExamRegistration::with(['student.user', 'exam'])
            ->where(fn ($q) => $q->where('status', '!=', 'approved')->orWhere('attendance_eligible', false)->orWhere('fee_cleared', false))
            ->limit(25)
            ->get()
            ->map(fn (ExamRegistration $registration) => $this->item($registration->student?->user?->name ?? 'Student #' . $registration->student_id, ($registration->exam?->name ?? 'Exam') . ' hall ticket blocked', 'high', 'coe', 'Exam Officer', null, route('academics.coe.hall-ticket-readiness'), 'Clear hall ticket blocker'));
    }

    private function obeGaps(): Collection
    {
        $subjectsWithCo = \App\Models\CourseOutcome::query()->pluck('subject_id');

        return Subject::with('program')
            ->where('is_active', true)
            ->whereNotIn('id', $subjectsWithCo)
            ->limit(25)
            ->get()
            ->map(fn (Subject $subject) => $this->item($subject->name, ($subject->program?->code ?? 'Program') . ' course outcomes missing', 'medium', 'iqac', 'IQAC Manager', null, route('academics.iqac.obe-readiness'), 'Complete OBE mapping'));
    }

    private function lowFeedback(): Collection
    {
        return CourseFeedback::with('subject.program')
            ->selectRaw('subject_id, avg(overall_rating) as avg_rating, count(*) as response_count')
            ->groupBy('subject_id')
            ->having('avg_rating', '<', 3.5)
            ->limit(25)
            ->get()
            ->map(fn ($row) => $this->item($row->subject?->name ?? 'Subject #' . $row->subject_id, 'Average feedback ' . round($row->avg_rating, 1) . ' from ' . $row->response_count . ' responses', 'medium', 'course_delivery', 'Course Coordinator', null, route('academics.course-delivery.course-engagement'), 'Create feedback action plan'));
    }

    private function handoffBlockers(): Collection
    {
        if (! DB::getSchemaBuilder()->hasTable('admission_handoff_records')) {
            return collect();
        }

        return DB::table('admission_handoff_records')
            ->leftJoin('applicants', 'applicants.id', '=', 'admission_handoff_records.applicant_id')
            ->leftJoin('users', 'users.id', '=', 'applicants.user_id')
            ->whereIn('admission_handoff_records.status', ['blocked', 'pending_admission_completion', 'returned_for_correction'])
            ->select('admission_handoff_records.*', 'users.name as applicant_name', 'applicants.application_number')
            ->latest('admission_handoff_records.updated_at')
            ->limit(25)
            ->get()
            ->map(fn ($row) => $this->item($row->applicant_name ?? 'Applicant #' . $row->applicant_id, ($row->application_number ?? 'Application') . ' - ' . str_replace('_', ' ', $row->status), $row->status === 'blocked' ? 'critical' : 'high', 'handoff', 'Admission/Academics', null, route('academics.dean-os.handoff'), 'Clear handoff blocker'));
    }

    private function overdueActions(): Collection
    {
        return AcademicDeanActionItem::with('owner')
            ->whereNotIn('status', ['done', 'cancelled'])
            ->whereNotNull('due_at')
            ->where('due_at', '<', now())
            ->limit(25)
            ->get()
            ->map(fn (AcademicDeanActionItem $action) => $this->item($action->title, $action->description ?? 'Dean action item overdue', 'critical', $action->source_type, $action->owner?->name, $action->due_at?->toDateString(), route('academics.dean-os.reviews'), 'Close or escalate action'));
    }
}
