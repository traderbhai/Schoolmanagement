<?php

namespace App\Services;

use App\Models\AcademicTranscript;
use App\Models\Exam;
use App\Models\ExamAnomalyLog;
use App\Models\ExamRegistration;
use App\Models\ExamResult;
use App\Models\MarksAppeal;
use App\Models\Program;
use App\Models\Student;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class AcademicCoeOperatingService
{
    public function __construct(
        private AcademicHierarchyService $hierarchy,
        private AcademicScopeService $scopes
    ) {}

    public function dashboard(User $user): array
    {
        $exam = $this->examReadiness($user);
        $marks = $this->marksResults($user);
        $hall = $this->hallTicketReadiness($user);
        $transcripts = $this->transcripts($user);
        $appeals = $this->appealsAnomalies($user);

        return [
            'scopeSummary' => $this->scopeSummary($user),
            'kpis' => [
                'upcoming_exams' => $exam['metrics']['upcoming_exams'],
                'marks_pending' => $marks['metrics']['marks_pending'],
                'hall_ticket_blocks' => $hall['metrics']['blocked_registrations'],
                'appeals_anomalies' => $appeals['metrics']['open_appeals'] + $appeals['metrics']['open_anomalies'],
            ],
            'exam' => $exam,
            'marks' => $marks,
            'hall' => $hall,
            'transcripts' => $transcripts,
            'appeals' => $appeals,
            'reports' => $this->reports($user),
        ];
    }

    public function examReadiness(User $user): array
    {
        $programIds = $this->visibleProgramIds($user);
        $upcoming = $this->applyProgramScope(
            Exam::with(['program', 'subject', 'term', 'classroom'])->where('exam_date', '>=', now()->toDateString()),
            $programIds
        )->orderBy('exam_date')->limit(25)->get();

        $missingRoom = $upcoming->filter(fn (Exam $exam) => ! $exam->classroom_id);

        return [
            'title' => 'Exam Readiness',
            'description' => 'Exam schedule, room readiness, term mapping, and upcoming exam control.',
            'metrics' => [
                'upcoming_exams' => $upcoming->count(),
                'missing_room' => $missingRoom->count(),
                'scheduled_programs' => $upcoming->pluck('program_id')->filter()->unique()->count(),
                'past_exams' => $this->applyProgramScope(Exam::where('exam_date', '<', now()->toDateString()), $programIds)->count(),
            ],
            'items' => $upcoming->map(fn (Exam $exam) => [
                'title' => $exam->name,
                'subtitle' => ($exam->program?->code ?? 'Program') . ' - ' . ($exam->subject?->code ?? 'Subject') . ' - ' . $exam->exam_date?->toDateString(),
                'status' => $exam->classroom_id ? 'Ready' : 'Room pending',
                'action' => route('exam-cell.exams'),
            ])->values(),
        ];
    }

    public function marksResults(User $user): array
    {
        $programIds = $this->visibleProgramIds($user);
        $completed = $this->applyProgramScope(
            Exam::with(['program', 'subject'])->withCount('results')->where('exam_date', '<=', now()->toDateString()),
            $programIds
        )->orderByDesc('exam_date')->limit(50)->get();

        $pending = $completed->filter(fn (Exam $exam) => (int) $exam->results_count === 0);
        $published = ExamResult::where('remarks', 'Published')
            ->whereHas('exam', fn (Builder $query) => $this->applyProgramScope($query, $programIds))
            ->distinct('exam_id')
            ->count('exam_id');

        return [
            'title' => 'Marks And Results Control',
            'description' => 'Completed exams without marks, publication readiness, and pass-rate exceptions.',
            'metrics' => [
                'marks_pending' => $pending->count(),
                'completed_exams' => $completed->count(),
                'published_exams' => $published,
                'failed_results' => $this->failedResultsQuery($programIds)->count(),
            ],
            'items' => $pending->map(fn (Exam $exam) => [
                'title' => $exam->name,
                'subtitle' => ($exam->program?->code ?? 'Program') . ' - ' . ($exam->subject?->name ?? 'Subject'),
                'status' => 'Marks pending',
                'action' => route('exam-cell.grade-sheet', $exam),
            ])->merge($this->failedResultsQuery($programIds)->with(['student.user', 'exam.subject'])->limit(15)->get()->map(fn (ExamResult $result) => [
                'title' => $result->student?->user?->name ?? 'Student #' . $result->student_id,
                'subtitle' => ($result->exam?->subject?->code ?? 'Exam') . ' - ' . $result->marks_obtained . '/' . $result->exam?->passing_marks,
                'status' => 'Below pass mark',
                'action' => route('exam-cell.results'),
            ]))->values(),
        ];
    }

    public function hallTicketReadiness(User $user): array
    {
        $programIds = $this->visibleProgramIds($user);
        $blocked = ExamRegistration::with(['student.user', 'exam.program', 'exam.subject'])
            ->where(function (Builder $query) {
                $query->where('status', '!=', 'approved')
                    ->orWhere('attendance_eligible', false)
                    ->orWhere('fee_cleared', false);
            })
            ->whereHas('exam', fn (Builder $query) => $this->applyProgramScope($query->where('exam_date', '>=', now()->toDateString()), $programIds))
            ->limit(25)
            ->get();

        return [
            'title' => 'Hall Ticket Readiness',
            'description' => 'Eligibility, fee clearance, registration approval, and downloadable hall-ticket readiness.',
            'metrics' => [
                'blocked_registrations' => $blocked->count(),
                'approved_registrations' => ExamRegistration::where('status', 'approved')->whereHas('exam', fn (Builder $query) => $this->applyProgramScope($query, $programIds))->count(),
                'attendance_blocks' => ExamRegistration::where('attendance_eligible', false)->whereHas('exam', fn (Builder $query) => $this->applyProgramScope($query, $programIds))->count(),
                'fee_blocks' => ExamRegistration::where('fee_cleared', false)->whereHas('exam', fn (Builder $query) => $this->applyProgramScope($query, $programIds))->count(),
            ],
            'items' => $blocked->map(fn (ExamRegistration $registration) => [
                'title' => $registration->student?->user?->name ?? 'Student #' . $registration->student_id,
                'subtitle' => ($registration->exam?->name ?? 'Exam') . ' - ' . ($registration->exam?->program?->code ?? 'Program'),
                'status' => $this->registrationStatus($registration),
                'action' => route('exam-cell.hall-tickets', ['exam_id' => $registration->exam_id]),
            ])->values(),
        ];
    }

    public function transcripts(User $user): array
    {
        $programIds = $this->visibleProgramIds($user);
        $drafts = AcademicTranscript::with('student.user')
            ->where('status', 'draft')
            ->whereHas('student', fn (Builder $query) => $this->applyProgramScope($query, $programIds))
            ->limit(25)
            ->get();

        return [
            'title' => 'Transcript And Grade Records',
            'description' => 'Draft transcripts, issued records, and transcript blockers after result publication.',
            'metrics' => [
                'draft_transcripts' => $drafts->count(),
                'issued_transcripts' => AcademicTranscript::where('status', 'issued')->whereHas('student', fn (Builder $query) => $this->applyProgramScope($query, $programIds))->count(),
                'student_records' => $this->applyProgramScope(Student::where('status', 'active'), $programIds)->count(),
                'result_records' => ExamResult::whereHas('exam', fn (Builder $query) => $this->applyProgramScope($query, $programIds))->count(),
            ],
            'items' => $drafts->map(fn (AcademicTranscript $transcript) => [
                'title' => $transcript->student?->user?->name ?? 'Student #' . $transcript->student_id,
                'subtitle' => $transcript->academic_year . ' - CGPA ' . ($transcript->cgpa ?? 'pending'),
                'status' => ucfirst($transcript->status),
                'action' => route('academic.transcripts.index'),
            ])->values(),
        ];
    }

    public function appealsAnomalies(User $user): array
    {
        $programIds = $this->visibleProgramIds($user);
        $appeals = MarksAppeal::with(['student.user', 'examResult.exam.subject'])
            ->whereIn('status', ['pending', 'under_review'])
            ->whereHas('examResult.exam', fn (Builder $query) => $this->applyProgramScope($query, $programIds))
            ->limit(25)
            ->get();

        $anomalies = ExamAnomalyLog::with(['student.user', 'exam.subject'])
            ->whereNull('resolved_at')
            ->whereHas('exam', fn (Builder $query) => $this->applyProgramScope($query, $programIds))
            ->limit(25)
            ->get();

        return [
            'title' => 'Appeals And Anomalies',
            'description' => 'Marks appeals, malpractice/anomaly logs, and resolution queues before final publishing.',
            'metrics' => [
                'open_appeals' => $appeals->count(),
                'open_anomalies' => $anomalies->count(),
                'critical_anomalies' => $anomalies->where('severity', 'critical')->count(),
                'under_review_appeals' => $appeals->where('status', 'under_review')->count(),
            ],
            'items' => $appeals->map(fn (MarksAppeal $appeal) => [
                'title' => $appeal->student?->user?->name ?? 'Student #' . $appeal->student_id,
                'subtitle' => ($appeal->examResult?->exam?->subject?->code ?? 'Result') . ' - ' . $appeal->reason,
                'status' => ucfirst(str_replace('_', ' ', $appeal->status)),
                'action' => route('exam-cell.marks-appeals'),
            ])->merge($anomalies->map(fn (ExamAnomalyLog $anomaly) => [
                'title' => $anomaly->exam?->name ?? 'Exam anomaly',
                'subtitle' => ($anomaly->student?->user?->name ?? 'Student') . ' - ' . $anomaly->anomaly_type,
                'status' => ucfirst($anomaly->severity),
                'action' => route('exam-cell.anomalies.index'),
            ]))->values(),
        ];
    }

    public function reports(User $user): array
    {
        return [
            'exam_readiness' => ['label' => 'Exam readiness', 'count' => $this->examReadiness($user)['metrics']['upcoming_exams'], 'route' => route('academics.coe.exam-readiness')],
            'marks_results' => ['label' => 'Marks and results', 'count' => $this->marksResults($user)['metrics']['marks_pending'], 'route' => route('academics.coe.marks-results')],
            'hall_ticket_readiness' => ['label' => 'Hall ticket readiness', 'count' => $this->hallTicketReadiness($user)['metrics']['blocked_registrations'], 'route' => route('academics.coe.hall-ticket-readiness')],
            'transcripts' => ['label' => 'Transcripts', 'count' => $this->transcripts($user)['metrics']['draft_transcripts'], 'route' => route('academics.coe.transcripts')],
            'appeals_anomalies' => ['label' => 'Appeals and anomalies', 'count' => $this->appealsAnomalies($user)['metrics']['open_appeals'], 'route' => route('academics.coe.appeals-anomalies')],
        ];
    }

    public function section(User $user, string $section): array
    {
        return match ($section) {
            'exam-readiness' => $this->examReadiness($user),
            'marks-results' => $this->marksResults($user),
            'hall-ticket-readiness' => $this->hallTicketReadiness($user),
            'transcripts' => $this->transcripts($user),
            'appeals-anomalies' => $this->appealsAnomalies($user),
            default => abort(404),
        };
    }

    private function failedResultsQuery(?Collection $programIds): Builder
    {
        return ExamResult::query()
            ->whereHas('exam', fn (Builder $query) => $this->applyProgramScope($query, $programIds))
            ->whereExists(function ($query) {
                $query->selectRaw('1')
                    ->from('exams')
                    ->whereColumn('exams.id', 'exam_results.exam_id')
                    ->whereColumn('exam_results.marks_obtained', '<', 'exams.passing_marks');
            });
    }

    private function registrationStatus(ExamRegistration $registration): string
    {
        if ($registration->status !== 'approved') {
            return 'Approval pending';
        }
        if (! $registration->attendance_eligible) {
            return 'Attendance blocked';
        }
        if (! $registration->fee_cleared) {
            return 'Fee blocked';
        }

        return 'Ready';
    }

    private function visibleProgramIds(User $user): ?Collection
    {
        if ($this->hierarchy->canSeeAll($user)) {
            return null;
        }

        $ids = $this->scopes->scopeIdsFor($user, 'program');
        if ($ids->isEmpty()) {
            $ids = $this->scopes->scopeIdsFor($user, 'batch')
                ->map(fn ($batchId) => \App\Models\Batch::whereKey($batchId)->value('program_id'))
                ->filter()
                ->unique()
                ->values();
        }

        return $ids;
    }

    private function applyProgramScope(Builder $query, ?Collection $programIds, string $column = 'program_id'): Builder
    {
        if ($programIds === null) {
            return $query;
        }

        if ($programIds->isEmpty()) {
            return $query->whereRaw('1 = 0');
        }

        return $query->whereIn($column, $programIds);
    }

    private function scopeSummary(User $user): array
    {
        if ($this->hierarchy->canSeeAll($user)) {
            return ['label' => 'All CoE programs', 'detail' => 'Department-level examination visibility'];
        }

        $scopes = $this->scopes->scopesFor($user);

        return [
            'label' => $scopes->pluck('scope_type')->unique()->map(fn ($type) => ucfirst($type))->join(', ') ?: 'Assigned CoE work',
            'detail' => $scopes->take(4)->pluck('scope_name')->join(', ') ?: 'No explicit CoE scope assigned yet',
        ];
    }
}
