<?php

namespace App\Services;

use App\Models\CoAttainment;
use App\Models\CoPoMapping;
use App\Models\CourseFeedback;
use App\Models\CourseOutcome;
use App\Models\DepartmentActivityLog;
use App\Models\ObeSurvey;
use App\Models\PoAttainment;
use App\Models\Program;
use App\Models\ProgramOutcome;
use App\Models\ProgramSpecificOutcome;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class AcademicIqacOperatingService
{
    public function __construct(
        private AcademicHierarchyService $hierarchy,
        private AcademicScopeService $scopes
    ) {}

    public function dashboard(User $user): array
    {
        $obe = $this->obeReadiness($user);
        $attainment = $this->attainmentMonitoring($user);
        $feedback = $this->feedbackQuality($user);
        $audit = $this->auditCompliance($user);

        return [
            'scopeSummary' => $this->scopeSummary($user),
            'kpis' => [
                'obe_gaps' => $obe['metrics']['subjects_without_co'] + $obe['metrics']['programs_without_po'],
                'mapping_gaps' => $obe['metrics']['co_without_mapping'],
                'target_misses' => $attainment['metrics']['co_target_missed'] + $attainment['metrics']['po_target_missed'],
                'feedback_gaps' => $feedback['metrics']['subjects_without_feedback'],
            ],
            'obe' => $obe,
            'attainment' => $attainment,
            'feedback' => $feedback,
            'audit' => $audit,
            'reports' => $this->reports($user),
        ];
    }

    public function obeReadiness(User $user): array
    {
        $programIds = $this->visibleProgramIds($user);
        $programsWithoutPo = $this->applyProgramScope(
            Program::where('is_active', true)->whereNotIn('id', ProgramOutcome::query()->pluck('program_id')),
            $programIds
        )->orderBy('name')->limit(25)->get();

        $subjectsWithoutCo = $this->applyProgramScope(
            Subject::with('program')->where('is_active', true)->whereNotIn('id', CourseOutcome::query()->pluck('subject_id')),
            $programIds
        )->orderBy('name')->limit(25)->get();

        $mappedCoIds = CoPoMapping::query()->where('correlation_level', '>', 0)->pluck('course_outcome_id');
        $cosWithoutMapping = CourseOutcome::with('subject.program')
            ->whereNotIn('id', $mappedCoIds)
            ->whereHas('subject', fn (Builder $query) => $this->applyProgramScope($query, $programIds))
            ->limit(25)
            ->get();

        return [
            'title' => 'OBE Readiness',
            'description' => 'Program outcomes, course outcomes, PSOs, and CO-PO mapping completeness.',
            'metrics' => [
                'programs_without_po' => $programsWithoutPo->count(),
                'subjects_without_co' => $subjectsWithoutCo->count(),
                'co_without_mapping' => $cosWithoutMapping->count(),
                'active_psos' => $this->applyProgramScope(ProgramSpecificOutcome::where('is_active', true), $programIds)->count(),
            ],
            'items' => collect($programsWithoutPo->map(fn (Program $program) => [
                'title' => $program->name,
                'subtitle' => $program->code . ' - program outcomes missing',
                'status' => 'PO gap',
                'action' => route('academic.obe.po.index', ['program_id' => $program->id]),
            ])->values())->concat($subjectsWithoutCo->map(fn (Subject $subject) => [
                'title' => $subject->name,
                'subtitle' => ($subject->program?->code ?? 'Program') . ' - course outcomes missing',
                'status' => 'CO gap',
                'action' => route('academic.obe.co.index', ['program_id' => $subject->program_id, 'subject_id' => $subject->id]),
            ])->values())->concat($cosWithoutMapping->map(fn (CourseOutcome $co) => [
                'title' => $co->code . ' - ' . ($co->subject?->name ?? 'Subject'),
                'subtitle' => ($co->subject?->program?->code ?? 'Program') . ' - CO-PO mapping missing',
                'status' => 'Mapping gap',
                'action' => route('academic.obe.matrix', ['program_id' => $co->subject?->program_id, 'subject_id' => $co->subject_id]),
            ])->values())->values(),
        ];
    }

    public function attainmentMonitoring(User $user): array
    {
        $programIds = $this->visibleProgramIds($user);
        $coMisses = CoAttainment::with(['courseOutcome.subject.program', 'subject', 'term'])
            ->where('target_met', false)
            ->whereHas('subject', fn (Builder $query) => $this->applyProgramScope($query, $programIds))
            ->limit(25)
            ->get();

        $poMisses = PoAttainment::with(['program', 'programOutcome', 'term'])
            ->where('target_met', false);
        $this->applyProgramScope($poMisses, $programIds);
        $poMisses = $poMisses->limit(25)->get();

        return [
            'title' => 'Attainment Monitoring',
            'description' => 'CO/PO target misses, attainment recalculation needs, and low-outcome action queues.',
            'metrics' => [
                'co_target_missed' => $coMisses->count(),
                'po_target_missed' => $poMisses->count(),
                'co_records' => CoAttainment::whereHas('subject', fn (Builder $query) => $this->applyProgramScope($query, $programIds))->count(),
                'po_records' => $this->applyProgramScope(PoAttainment::query(), $programIds)->count(),
            ],
            'items' => collect($coMisses->map(fn (CoAttainment $row) => [
                'title' => $row->courseOutcome?->code . ' - ' . ($row->subject?->name ?? 'Subject'),
                'subtitle' => ($row->subject?->program?->code ?? 'Program') . ' - final ' . $row->final_attainment . ' / target ' . $row->target_attainment,
                'status' => 'CO target missed',
                'action' => route('academic.obe.attainment', ['program_id' => $row->subject?->program_id, 'term_id' => $row->term_id]),
            ])->values())->concat($poMisses->map(fn (PoAttainment $row) => [
                'title' => $row->programOutcome?->code . ' - ' . ($row->program?->code ?? 'Program'),
                'subtitle' => 'Attainment ' . $row->attainment_value . ' / target ' . $row->target_value,
                'status' => 'PO target missed',
                'action' => route('academic.obe.attainment', ['program_id' => $row->program_id, 'term_id' => $row->term_id]),
            ])->values())->values(),
        ];
    }

    public function feedbackQuality(User $user): array
    {
        $programIds = $this->visibleProgramIds($user);
        $subjectsWithFeedback = CourseFeedback::query()->pluck('subject_id');
        $subjectsWithoutFeedback = $this->applyProgramScope(
            Subject::with('program')->where('is_active', true)->whereNotIn('id', $subjectsWithFeedback),
            $programIds
        )->orderBy('name')->limit(25)->get();

        $lowFeedbackSubjects = CourseFeedback::with('subject.program')
            ->selectRaw('subject_id, avg(overall_rating) as avg_rating, count(*) as response_count')
            ->whereHas('subject', fn (Builder $query) => $this->applyProgramScope($query, $programIds))
            ->groupBy('subject_id')
            ->having('avg_rating', '<', 3.5)
            ->limit(25)
            ->get();

        $publishedSurveys = ObeSurvey::with(['subject.program', 'term'])
            ->where('is_published', true)
            ->whereHas('subject', fn (Builder $query) => $this->applyProgramScope($query, $programIds))
            ->limit(25)
            ->get();

        return [
            'title' => 'Feedback And Survey Quality',
            'description' => 'Course feedback coverage, low-rating subjects, OBE survey status, and action-plan triggers.',
            'metrics' => [
                'subjects_without_feedback' => $subjectsWithoutFeedback->count(),
                'low_feedback_subjects' => $lowFeedbackSubjects->count(),
                'published_surveys' => $publishedSurveys->count(),
                'feedback_responses' => CourseFeedback::whereHas('subject', fn (Builder $query) => $this->applyProgramScope($query, $programIds))->count(),
            ],
            'items' => collect($subjectsWithoutFeedback->map(fn (Subject $subject) => [
                'title' => $subject->name,
                'subtitle' => ($subject->program?->code ?? 'Program') . ' - feedback missing',
                'status' => 'Feedback gap',
                'action' => route('chair.faculty.feedback'),
            ])->values())->concat($lowFeedbackSubjects->map(fn ($row) => [
                'title' => $row->subject?->name ?? 'Subject',
                'subtitle' => ($row->subject?->program?->code ?? 'Program') . ' - average rating ' . round($row->avg_rating, 1),
                'status' => 'Action plan due',
                'action' => route('chair.faculty.feedback'),
            ])->values())->concat($publishedSurveys->map(fn (ObeSurvey $survey) => [
                'title' => $survey->title,
                'subtitle' => ($survey->subject?->program?->code ?? 'Program') . ' - closes ' . ($survey->closes_at?->toDateString() ?? 'open'),
                'status' => 'Survey live',
                'action' => route('academic.obe.surveys.index'),
            ])->values())->values(),
        ];
    }

    public function auditCompliance(User $user): array
    {
        $department = $this->hierarchy->department();
        $activity = DepartmentActivityLog::with('actor')
            ->where('department_id', $department->id)
            ->whereIn('action', ['academic_scope_assigned', 'academics_os_v004_seeded', 'quality_audit_review'])
            ->latest()
            ->limit(25)
            ->get();

        return [
            'title' => 'Audit And Compliance',
            'description' => 'IQAC audit trail, evidence readiness, governance updates, and quality review activity.',
            'metrics' => [
                'audit_events' => $activity->count(),
                'scope_changes' => $activity->where('action', 'academic_scope_assigned')->count(),
                'quality_reviews' => $activity->where('action', 'quality_audit_review')->count(),
                'department_members' => $this->hierarchy->members()->count(),
            ],
            'items' => $activity->map(fn (DepartmentActivityLog $log) => [
                'title' => str($log->action)->replace('_', ' ')->title()->toString(),
                'subtitle' => $log->description,
                'status' => $log->created_at?->diffForHumans() ?? 'Recorded',
                'action' => route('academics.governance.index'),
            ])->values(),
        ];
    }

    public function reports(User $user): array
    {
        return [
            'obe_readiness' => ['label' => 'OBE readiness', 'count' => $this->obeReadiness($user)['metrics']['co_without_mapping'], 'route' => route('academics.iqac.obe-readiness')],
            'attainment_monitoring' => ['label' => 'Attainment monitoring', 'count' => $this->attainmentMonitoring($user)['metrics']['co_target_missed'], 'route' => route('academics.iqac.attainment-monitoring')],
            'feedback_quality' => ['label' => 'Feedback quality', 'count' => $this->feedbackQuality($user)['metrics']['subjects_without_feedback'], 'route' => route('academics.iqac.feedback-quality')],
            'audit_compliance' => ['label' => 'Audit compliance', 'count' => $this->auditCompliance($user)['metrics']['audit_events'], 'route' => route('academics.iqac.audit-compliance')],
        ];
    }

    public function section(User $user, string $section, array $filters = []): array
    {
        $data = match ($section) {
            'obe-readiness' => $this->obeReadiness($user),
            'attainment-monitoring' => $this->attainmentMonitoring($user),
            'feedback-quality' => $this->feedbackQuality($user),
            'audit-compliance' => $this->auditCompliance($user),
            default => abort(404),
        };

        $data['items'] = $this->filterItems($data['items'], $filters)->values();
        $data['filters'] = $filters;
        $data['filter_summary'] = $this->filterSummary($filters);

        return $data;
    }

    private function filterItems(Collection $items, array $filters): Collection
    {
        return $items
            ->when(! empty($filters['search']), function (Collection $collection) use ($filters) {
                $search = mb_strtolower((string) $filters['search']);

                return $collection->filter(fn (array $item) => str_contains(mb_strtolower($item['title'] ?? ''), $search)
                    || str_contains(mb_strtolower($item['subtitle'] ?? ''), $search)
                    || str_contains(mb_strtolower($item['status'] ?? ''), $search));
            })
            ->when(! empty($filters['status']), function (Collection $collection) use ($filters) {
                $status = mb_strtolower((string) $filters['status']);

                return $collection->filter(fn (array $item) => mb_strtolower($item['status'] ?? '') === $status);
            });
    }

    private function filterSummary(array $filters): string
    {
        $active = collect($filters)
            ->only(['search', 'status'])
            ->filter(fn ($value) => $value !== null && $value !== '')
            ->map(fn ($value, $key) => str($key)->headline() . ': ' . $value);

        return $active->isEmpty() ? 'Showing all scoped IQAC records.' : $active->join(' | ');
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
            return ['label' => 'All IQAC programs', 'detail' => 'Department-level quality visibility'];
        }

        $scopes = $this->scopes->scopesFor($user);

        return [
            'label' => $scopes->pluck('scope_type')->unique()->map(fn ($type) => ucfirst($type))->join(', ') ?: 'Assigned IQAC work',
            'detail' => $scopes->take(4)->pluck('scope_name')->join(', ') ?: 'No explicit IQAC scope assigned yet',
        ];
    }
}
