<?php

namespace App\Services;

use App\Models\AcademicPmcTimetableGenerationItem;
use App\Models\AcademicPmcTimetableGenerationRun;
use App\Models\AcademicPmcTimetableImpactRecord;
use App\Models\AcademicPmcTimetablePublishCheck;
use App\Models\AcademicPmcTimetableQualityScore;
use App\Models\AcademicPmcTimetableConstraint;
use App\Models\AcademicPmcTimetableChangeRequest;
use App\Models\AcademicPmcTimetableResolutionAction;
use App\Models\AcademicPmcTimetableSessionDemand;
use App\Models\AcademicPmcTimetableSolverAttempt;
use App\Models\AcademicPmcTimetableVersionWorkflow;
use App\Models\AcademicPmcSubstitutionRecommendation;
use App\Models\AcademicPmcTimetableNotification;
use App\Models\AcademicPmcRoomReadinessReview;
use App\Models\AcademicPmcCourseAllocationBatch;
use App\Models\AcademicPmcCourseGroup;
use App\Models\AcademicPmcCourseAllocationException;
use App\Models\AcademicPmcElectiveChoice;
use App\Models\AcademicPmcCourseGroupAdjustment;
use App\Models\AcademicPmcCourseGroupMember;
use App\Models\AcademicPmcFacultyAssignmentAcknowledgement;
use App\Models\AcademicPmcFacultyLoadReview;
use App\Models\AcademicPmcFacultyPreference;
use App\Models\AcademicPmcGroupBuildRun;
use App\Models\AcademicPmcGroupFacultyAssignment;
use App\Models\AcademicPmcLockedSlot;
use App\Models\AcademicPmcStudentCourseAllocation;
use App\Models\AcademicPmcWorkloadRule;
use App\Models\Batch;
use App\Models\Classroom;
use App\Models\Program;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\Term;
use App\Models\TimetableVersion;
use App\Models\TimetableSlot;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class PmcTimetableReadModelService
{
    public const RESPONSIBILITY = 'Official timetable, scoped audience views, dashboards, filters, and timetable report read models.';

    public function __construct(private AcademicPmcAccessPolicyService $policy) {}

    public function facultyScopedTimetable(User $user, array $filters = []): array
    {
        $teacher = $user->teacher;
        abort_unless($teacher, 403);

        $groupIds = AcademicPmcGroupFacultyAssignment::where('teacher_id', $teacher->id)->pluck('course_group_id');
        $items = $this->officialTimetableItemsQuery()
            ->with(['courseGroup.subject', 'teacher.user', 'classroom', 'slot', 'timetableVersion'])
            ->whereIn('course_group_id', $groupIds)
            ->where('teacher_id', $teacher->id)
            ->when($filters['day_of_week'] ?? null, fn ($q, $day) => $q->where('day_of_week', $day))
            ->orderBy('day_of_week')
            ->orderBy('timetable_slot_id')
            ->paginate(25)
            ->withQueryString();

        return [
            'title' => 'My PMC Teaching Timetable',
            'scopeLabel' => $teacher->user?->name ?? 'Faculty',
            'items' => $items,
            'groupCount' => $groupIds->count(),
            'filters' => $filters,
            'mode' => 'faculty',
            'selectorOptions' => $this->selectorOptions(),
        ];
    }

    public function officialTimetableAudience(User $user, array $filters = []): array
    {
        $this->policy->authorizeRead($user);
        $itemsQuery = $this->applyScope(
            $this->officialTimetableItemsQuery()
                ->with(['courseGroup.subject', 'teacher.user', 'classroom', 'slot', 'timetableVersion']),
            $user,
            [],
            [
                'courseGroup' => ['program_id' => 'program', 'batch_id' => 'batch', 'term_id' => 'term'],
                'timetableVersion' => ['program_id' => 'program', 'batch_id' => 'batch', 'term_id' => 'term'],
            ]
        )
            ->when($filters['program_id'] ?? null, fn ($q, $id) => $q->whereHas('courseGroup', fn ($group) => $group->where('program_id', $id)))
            ->when($filters['batch_id'] ?? null, fn ($q, $id) => $q->whereHas('courseGroup', fn ($group) => $group->where('batch_id', $id)))
            ->when($filters['term_id'] ?? null, fn ($q, $id) => $q->whereHas('courseGroup', fn ($group) => $group->where('term_id', $id)))
            ->when($filters['teacher_id'] ?? null, fn ($q, $id) => $q->where('teacher_id', $id))
            ->when($filters['classroom_id'] ?? null, fn ($q, $id) => $q->where('classroom_id', $id))
            ->when($filters['subject_id'] ?? null, fn ($q, $id) => $q->whereHas('courseGroup', fn ($group) => $group->where('subject_id', $id)))
            ->when($filters['course_group_id'] ?? null, fn ($q, $id) => $q->where('course_group_id', $id))
            ->when($filters['session_type'] ?? null, fn ($q, $type) => $q->where('session_type', $type))
            ->when($filters['day_of_week'] ?? null, fn ($q, $day) => $q->where('day_of_week', $day))
            ->orderBy('day_of_week')
            ->orderBy('timetable_slot_id');

        $allItems = (clone $itemsQuery)->get();
        $items = $itemsQuery->paginate(30)->withQueryString();

        return [
            'title' => 'PMC Master Official Timetable',
            'scopeLabel' => $this->policy->scopeLabel($user),
            'items' => $items,
            'parallelSlotGroups' => $this->parallelSlotGroups($allItems),
            'groupCount' => $this->applyScope(AcademicPmcCourseGroup::query(), $user, ['program_id' => 'program', 'batch_id' => 'batch', 'term_id' => 'term'])->count(),
            'filters' => $filters,
            'mode' => 'pmc',
            'selectorOptions' => $this->selectorOptions(),
        ];
    }

    public function lockedSlotSurface(User $user, array $filters, array $readiness, array $readinessInputDiagnostics): array
    {
        $lockedSlots = $this->applyScope(
            AcademicPmcLockedSlot::with(['slot', 'courseGroup']),
            $user,
            ['program_id' => 'program', 'batch_id' => 'batch', 'term_id' => 'term']
        )->latest();

        return [
            'title' => 'PMC Locked Slots And Timetable Readiness',
            'description' => 'Manual slot reservations and readiness checklist respected by timetable generation.',
            'lockedSlots' => $lockedSlots->paginate(15),
            'readiness' => $readiness,
            'readinessInputDiagnostics' => $readinessInputDiagnostics,
        ];
    }

    public function studentBasketSurface(User $user, array $filters, array $basketDiagnostics, array $allocationPressureDiagnostics, callable $filter): array
    {
        $allocations = $this->applyScope(
            $filter(AcademicPmcStudentCourseAllocation::with(['student.user', 'subject', 'term']), $filters),
            $user,
            [],
            ['term' => ['id' => 'term'], 'student' => ['program_id' => 'program', 'batch_id' => 'batch']]
        )->latest();
        $allocationExceptions = $this->applyScope(
            AcademicPmcCourseAllocationException::with(['student.user', 'subject', 'term', 'requester', 'decider']),
            $user,
            [],
            ['student' => ['program_id' => 'program', 'batch_id' => 'batch'], 'term' => ['id' => 'term']]
        )->latest();

        return [
            'title' => 'PMC Student Course Baskets',
            'description' => 'Student-wise allocated term course basket, approval state, validation flags, and group linkage.',
            'allocations' => $allocations->paginate(20),
            'allocationExceptions' => $allocationExceptions->paginate(15, ['*'], 'exceptions_page'),
            'basketDiagnostics' => $basketDiagnostics,
            'allocationPressureDiagnostics' => $allocationPressureDiagnostics,
        ];
    }

    public function allocationSurface(User $user, array $filters, array $allocationPressureDiagnostics, callable $filter): array
    {
        $batches = $this->applyScope(
            AcademicPmcCourseAllocationBatch::with(['program', 'batch', 'term', 'owner']),
            $user,
            ['program_id' => 'program', 'batch_id' => 'batch', 'term_id' => 'term']
        )->latest();
        $allocations = $this->applyScope(
            $filter(AcademicPmcStudentCourseAllocation::with(['student.user', 'subject', 'term']), $filters),
            $user,
            [],
            ['term' => ['id' => 'term'], 'student' => ['program_id' => 'program', 'batch_id' => 'batch']]
        )->latest();
        $electiveChoices = $this->applyScope(
            AcademicPmcElectiveChoice::with(['student.user', 'subject', 'term']),
            $user,
            ['program_id' => 'program', 'batch_id' => 'batch', 'term_id' => 'term']
        )->latest();
        $allocationExceptions = $this->applyScope(
            AcademicPmcCourseAllocationException::with(['student.user', 'subject', 'term', 'requester', 'decider']),
            $user,
            [],
            ['student' => ['program_id' => 'program', 'batch_id' => 'batch'], 'term' => ['id' => 'term']]
        )->latest();

        return [
            'title' => 'PMC Course Allocation',
            'description' => 'Term-wise student course allocation before timetable creation.',
            'batches' => $batches->paginate(10),
            'allocations' => $allocations->paginate(15),
            'electiveChoices' => $electiveChoices->paginate(15, ['*'], 'choices_page'),
            'allocationExceptions' => $allocationExceptions->paginate(15, ['*'], 'exceptions_page'),
            'allocationPressureDiagnostics' => $allocationPressureDiagnostics,
        ];
    }

    public function groupSurface(User $user, array $filters, array $groupDiagnostics, callable $filter): array
    {
        $groups = $this->applyScope(
            $filter(AcademicPmcCourseGroup::with(['program', 'subject', 'owner']), $filters),
            $user,
            ['program_id' => 'program', 'batch_id' => 'batch', 'term_id' => 'term']
        )->latest();
        $memberships = $this->applyScope(
            AcademicPmcCourseGroupMember::with(['courseGroup', 'student.user']),
            $user,
            [],
            ['courseGroup' => ['program_id' => 'program', 'batch_id' => 'batch', 'term_id' => 'term', 'subject_id' => 'subject']]
        )->latest();
        $buildRuns = $this->applyScope(
            AcademicPmcGroupBuildRun::with(['subject', 'creator']),
            $user,
            ['program_id' => 'program', 'batch_id' => 'batch', 'term_id' => 'term']
        )->latest();
        $groupAdjustments = $this->applyScope(
            AcademicPmcCourseGroupAdjustment::with(['courseGroup', 'targetCourseGroup', 'student.user', 'requester', 'decider']),
            $user,
            [],
            [
                'courseGroup' => ['program_id' => 'program', 'batch_id' => 'batch', 'term_id' => 'term', 'subject_id' => 'subject'],
                'targetCourseGroup' => ['program_id' => 'program', 'batch_id' => 'batch', 'term_id' => 'term', 'subject_id' => 'subject'],
                'student' => ['program_id' => 'program', 'batch_id' => 'batch'],
            ]
        )->latest();

        return [
            'title' => 'PMC Section And Group Builder',
            'description' => 'Core sections, elective groups, lab/tutorial/project groups, and student membership.',
            'groups' => $groups->paginate(15),
            'memberships' => $memberships->paginate(15),
            'buildRuns' => $buildRuns->paginate(10, ['*'], 'build_runs_page'),
            'groupAdjustments' => $groupAdjustments->paginate(15, ['*'], 'adjustments_page'),
            'groupDiagnostics' => $groupDiagnostics,
        ];
    }

    public function facultySurface(User $user, string $surface, array $filters, array $facultyDiagnostics, array $facultySuitabilityDiagnostics): array
    {
        $assignments = $this->applyScope(
            AcademicPmcGroupFacultyAssignment::with(['courseGroup.subject', 'teacher.user']),
            $user,
            [],
            ['courseGroup' => ['program_id' => 'program', 'batch_id' => 'batch', 'term_id' => 'term']]
        )->latest();
        $preferences = $this->applyScope(
            AcademicPmcFacultyPreference::with('teacher.user'),
            $user,
            ['term_id' => 'term']
        )->latest();
        $acknowledgements = $this->applyScope(
            AcademicPmcFacultyAssignmentAcknowledgement::with(['assignment.courseGroup.subject', 'teacher.user', 'requester', 'reviewer']),
            $user,
            [],
            ['assignment' => ['program_id' => 'program', 'batch_id' => 'batch', 'term_id' => 'term']]
        )->latest();
        $loadReviews = $this->applyScope(
            AcademicPmcFacultyLoadReview::with(['teacher.user', 'generationRun', 'reviewer']),
            $user,
            ['term_id' => 'term'],
            ['generationRun' => ['program_id' => 'program', 'batch_id' => 'batch', 'term_id' => 'term']]
        )->latest();
        $rules = $this->applyScope(
            AcademicPmcWorkloadRule::query(),
            $user,
            ['program_id' => 'program', 'term_id' => 'term']
        )->latest();

        return [
            'title' => 'PMC Section/Group Faculty And Load Planning',
            'description' => 'Faculty assignment to exact sections/groups, preferences, adjunct days, load rules, and shortage planning.',
            'assignments' => $assignments->paginate(15),
            'preferences' => $preferences->paginate(15),
            'acknowledgements' => $acknowledgements->paginate(15, ['*'], 'ack_page'),
            'loadReviews' => $loadReviews->paginate(15, ['*'], 'load_reviews_page'),
            'rules' => $rules->paginate(15),
            'facultyDiagnostics' => $facultyDiagnostics,
            'facultySuitabilityDiagnostics' => $facultySuitabilityDiagnostics,
            'surfaceKey' => $surface,
        ];
    }

    public function generatorSurface(User $user, string $surface, array $filters, array $generationDiagnostics): array
    {
        $generationRunIds = (clone $this->applyScope(
            AcademicPmcTimetableGenerationRun::query(),
            $user,
            ['program_id' => 'program', 'batch_id' => 'batch', 'term_id' => 'term']
        ))->pluck('id');
        $quality = AcademicPmcTimetableQualityScore::query()
            ->when(! $this->policy->canIgnorePmcScope($user), function (Builder $query) use ($generationRunIds) {
                if ($generationRunIds->isEmpty()) {
                    $query->whereRaw('1 = 0');
                } else {
                    $query->whereIn('generation_run_id', $generationRunIds);
                }
            });

        return [
            'title' => 'PMC Constraint-Based Timetable Generator',
            'description' => 'Deterministic generator, suggestions, unscheduled classes, hard conflicts, soft warnings, and quality score.',
            'runs' => $this->applyScope(
                AcademicPmcTimetableGenerationRun::query(),
                $user,
                ['program_id' => 'program', 'batch_id' => 'batch', 'term_id' => 'term']
            )->latest()->paginate(10),
            'items' => $this->applyScope(
                AcademicPmcTimetableGenerationItem::with(['courseGroup.subject', 'teacher.user', 'classroom', 'slot', 'operationalTimetableEntry']),
                $user,
                [],
                ['generationRun' => ['program_id' => 'program', 'batch_id' => 'batch', 'term_id' => 'term'], 'courseGroup' => ['program_id' => 'program', 'batch_id' => 'batch', 'term_id' => 'term']]
            )->latest()->paginate(20),
            'sessionDemands' => $this->applyScope(
                AcademicPmcTimetableSessionDemand::with('courseGroup.subject'),
                $user,
                ['program_id' => 'program', 'batch_id' => 'batch', 'term_id' => 'term'],
                ['courseGroup' => ['program_id' => 'program', 'batch_id' => 'batch', 'term_id' => 'term']]
            )->latest()->paginate(15, ['*'], 'session_demands_page'),
            'quality' => $quality->latest()->first(),
            'solverAttempts' => $this->applyScope(
                AcademicPmcTimetableSolverAttempt::query(),
                $user,
                [],
                ['generationRun' => ['program_id' => 'program', 'batch_id' => 'batch', 'term_id' => 'term']]
            )->latest()->paginate(10, ['*'], 'solver_attempts_page'),
            'publishChecks' => $this->applyScope(
                AcademicPmcTimetablePublishCheck::query(),
                $user,
                ['program_id' => 'program', 'batch_id' => 'batch', 'term_id' => 'term'],
                ['generationRun' => ['program_id' => 'program', 'batch_id' => 'batch', 'term_id' => 'term']]
            )->latest()->paginate(15, ['*'], 'publish_checks_page'),
            'impactPreview' => AcademicPmcTimetableImpactRecord::query()
                ->where('metadata->version', 'PMC OS v0.069')
                ->when(
                    ! $this->policy->canIgnorePmcScope($user),
                    function (Builder $query) use ($generationRunIds) {
                        if ($generationRunIds->isEmpty()) {
                            $query->whereRaw('1 = 0');
                        } else {
                            $query->whereIn('metadata->generation_run_id', $generationRunIds->map(fn ($id) => (string) $id)->all());
                        }
                    }
                )
                ->latest()
                ->paginate(15, ['*'], 'impact_preview_page'),
            'generationDiagnostics' => $generationDiagnostics,
            'surfaceKey' => $surface,
        ];
    }

    public function plannerSurface(User $user, array $filters, callable $applySort, callable $constraintsForUserScope): array
    {
        $items = $this->applyScope(
            AcademicPmcTimetableGenerationItem::with(['courseGroup.subject', 'teacher.user', 'classroom', 'slot']),
            $user,
            [],
            ['generationRun' => ['program_id' => 'program', 'batch_id' => 'batch', 'term_id' => 'term'], 'courseGroup' => ['program_id' => 'program', 'batch_id' => 'batch', 'term_id' => 'term']]
        )
            ->when($filters['status'] ?? null, fn (Builder $query, string $status) => $query->where('status', $status), fn (Builder $query) => $query->where('status', 'scheduled'))
            ->when($filters['subject_id'] ?? null, fn (Builder $query, string $subjectId) => $query->whereHas('courseGroup', fn (Builder $group) => $group->where('subject_id', $subjectId)))
            ->when($filters['search'] ?? null, function (Builder $query, string $search) {
                $query->where(function (Builder $inner) use ($search) {
                    $inner->whereHas('courseGroup', fn (Builder $group) => $group->where('name', 'like', "%{$search}%")
                        ->orWhereHas('subject', fn (Builder $subject) => $subject->where('name', 'like', "%{$search}%")->orWhere('code', 'like', "%{$search}%")))
                        ->orWhereHas('teacher.user', fn (Builder $teacher) => $teacher->where('name', 'like', "%{$search}%"))
                        ->orWhereHas('classroom', fn (Builder $room) => $room->where('name', 'like', "%{$search}%")->orWhere('room_number', 'like', "%{$search}%"));
                });
            });

        $applySort($items, $filters);

        return [
            'title' => 'PMC Timetable Planning Board',
            'description' => 'Batch, faculty, room, and group grid view with conflict and lock indicators.',
            'items' => $items->paginate(30),
            'constraints' => $constraintsForUserScope(
                AcademicPmcTimetableConstraint::query(),
                $user,
                AcademicPmcTimetableGenerationRun::query(),
                ['program_id' => 'program', 'batch_id' => 'batch', 'term_id' => 'term']
            )
                ->when($filters['severity'] ?? null, fn (Builder $query, string $severity) => $query->where('severity', $severity))
                ->latest()
                ->paginate(15)
                ->withQueryString(),
            'resolutionActions' => $this->applyScope(
                AcademicPmcTimetableResolutionAction::with(['constraint', 'owner']),
                $user,
                [],
                ['generationRun' => ['program_id' => 'program', 'batch_id' => 'batch', 'term_id' => 'term']]
            )->latest()->paginate(15, ['*'], 'resolution_page'),
        ];
    }

    public function versionSurface(User $user, array $filters, array $publishReadinessDiagnostics): array
    {
        $versionQuery = $this->applyScope(
            TimetableVersion::with(['program', 'batch', 'term', 'creator']),
            $user,
            ['program_id' => 'program', 'batch_id' => 'batch', 'term_id' => 'term']
        );
        $scopedVersionIds = (clone $versionQuery)->pluck('id');
        $scopedGenerationRunIds = AcademicPmcTimetableGenerationRun::query()
            ->when(! $this->policy->canIgnorePmcScope($user), function (Builder $query) use ($scopedVersionIds) {
                if ($scopedVersionIds->isEmpty()) {
                    $query->whereRaw('1 = 0');
                } else {
                    $query->whereIn('timetable_version_id', $scopedVersionIds);
                }
            })
            ->pluck('id');
        $scopedImpactChangeRequestIds = AcademicPmcTimetableChangeRequest::query()
            ->when(! $this->policy->canIgnorePmcScope($user), function (Builder $query) use ($scopedVersionIds) {
                if ($scopedVersionIds->isEmpty()) {
                    $query->whereRaw('1 = 0');
                } else {
                    $query->whereIn('timetable_version_id', $scopedVersionIds);
                }
            })
            ->pluck('id');

        return [
            'title' => 'PMC Timetable Version, Freeze And Impact',
            'description' => 'Generated, PMC review, Dean review, approved, published, frozen, revision, impact, compare, and rollback governance.',
            'versions' => $versionQuery->latest()->paginate(15),
            'workflows' => $this->applyScope(
                AcademicPmcTimetableVersionWorkflow::with(['timetableVersion.program', 'publisher', 'generationRun']),
                $user,
                [],
                ['timetableVersion' => ['program_id' => 'program', 'batch_id' => 'batch', 'term_id' => 'term'], 'generationRun' => ['program_id' => 'program', 'batch_id' => 'batch', 'term_id' => 'term']]
            )->latest()->paginate(15, ['*'], 'workflows_page'),
            'changes' => AcademicPmcTimetableChangeRequest::with(['pmcGenerationItem.courseGroup.subject', 'pmcGenerationItem.slot', 'pmcGenerationItem.teacher.user'])
                ->when(! $this->policy->canIgnorePmcScope($user), function (Builder $query) use ($scopedVersionIds) {
                    if ($scopedVersionIds->isEmpty()) {
                        $query->whereRaw('1 = 0');
                    } else {
                        $query->whereIn('timetable_version_id', $scopedVersionIds);
                    }
                })
                ->latest()
                ->paginate(15),
            'impacts' => $this->applyScope(
                AcademicPmcTimetableImpactRecord::query(),
                $user,
                []
            )->when(! $this->policy->canIgnorePmcScope($user), function (Builder $query) use ($scopedImpactChangeRequestIds, $scopedGenerationRunIds) {
                if ($scopedImpactChangeRequestIds->isEmpty() && $scopedGenerationRunIds->isEmpty()) {
                    $query->whereRaw('1 = 0');
                    return;
                }

                $query->where(function (Builder $query) use ($scopedImpactChangeRequestIds, $scopedGenerationRunIds) {
                    $hasFilter = false;
                    if ($scopedImpactChangeRequestIds->isNotEmpty()) {
                        $query->whereIn('change_request_id', $scopedImpactChangeRequestIds);
                        $hasFilter = true;
                    }

                    if ($scopedGenerationRunIds->isNotEmpty()) {
                        $runIds = $scopedGenerationRunIds->map(fn ($id) => (string) $id)->all();
                        if ($hasFilter) {
                            $query->orWhereIn('metadata->generation_run_id', $runIds);
                        } else {
                            $query->whereIn('metadata->generation_run_id', $runIds);
                        }
                    }
                });
            })->latest()->paginate(15),
            'publishChecks' => $this->applyScope(
                AcademicPmcTimetablePublishCheck::query(),
                $user,
                [],
                ['generationRun' => ['program_id' => 'program', 'batch_id' => 'batch', 'term_id' => 'term']]
            )->latest()->paginate(15, ['*'], 'publish_checks_page'),
            'publishReadinessDiagnostics' => $publishReadinessDiagnostics,
        ];
    }

    public function substitutionSurface(User $user, array $filters, array $substitutionEmergencyDiagnostics): array
    {
        $versionIds = (clone $this->applyScope(
            TimetableVersion::query(),
            $user,
            ['program_id' => 'program', 'batch_id' => 'batch', 'term_id' => 'term']
        ))->pluck('id');

        return [
            'title' => 'PMC Substitution And Change Intelligence',
            'description' => 'Substitute recommendation, uncovered class queue, repeated substitution risk, and notification readiness.',
            'recommendations' => $this->applyScope(
                AcademicPmcSubstitutionRecommendation::with(['courseGroup.subject', 'originalTeacher.user', 'substituteTeacher.user']),
                $user,
                [],
                ['courseGroup' => ['program_id' => 'program', 'batch_id' => 'batch', 'term_id' => 'term']]
            )->latest()->paginate(15),
            'changes' => AcademicPmcTimetableChangeRequest::with(['pmcGenerationItem.courseGroup.subject', 'pmcGenerationItem.slot', 'pmcGenerationItem.teacher.user'])
                ->when(! $this->policy->canIgnorePmcScope($user), function (Builder $query) use ($versionIds) {
                    if ($versionIds->isEmpty()) {
                        $query->whereRaw('1 = 0');
                    } else {
                        $query->whereIn('timetable_version_id', $versionIds);
                    }
                })
                ->latest()
                ->paginate(15),
            'notifications' => AcademicPmcTimetableNotification::latest()->paginate(15),
            'substitutionEmergencyDiagnostics' => $substitutionEmergencyDiagnostics,
        ];
    }

    public function reportsSurface(User $user, array $filters): array
    {
        $notificationQuery = AcademicPmcTimetableNotification::query()
            ->when($filters['notification_type'] ?? null, fn ($q, $type) => $q->where('notification_type', $type))
            ->when($filters['recipient_type'] ?? null, fn ($q, $type) => $q->where('recipient_type', $type))
            ->when($filters['status'] ?? null, fn ($q, $status) => $q->where('status', $status));
        $generationRunIds = (clone $this->applyScope(
            AcademicPmcTimetableGenerationRun::query(),
            $user,
            ['program_id' => 'program', 'batch_id' => 'batch', 'term_id' => 'term']
        ))->pluck('id');
        $qualityQuery = AcademicPmcTimetableQualityScore::query()
            ->when(! $this->policy->canIgnorePmcScope($user), function (Builder $query) use ($generationRunIds) {
                if ($generationRunIds->isEmpty()) {
                    $query->whereRaw('1 = 0');
                } else {
                    $query->whereIn('generation_run_id', $generationRunIds);
                }
            });

        return [
            'title' => 'PMC Timetable Reports And Notifications',
            'description' => 'Allocation completeness, group strength, faculty load, conflicts, quality score, room utilization, substitutions, and revision audit.',
            'notifications' => $notificationQuery->latest()->paginate(20)->withQueryString(),
            'notificationFilters' => $filters,
            'sessionDemands' => $this->applyScope(
                AcademicPmcTimetableSessionDemand::with('courseGroup.subject'),
                $user,
                ['program_id' => 'program', 'batch_id' => 'batch', 'term_id' => 'term'],
                ['courseGroup' => ['program_id' => 'program', 'batch_id' => 'batch', 'term_id' => 'term']]
            )->latest()->paginate(15, ['*'], 'session_demands_page'),
            'quality' => $qualityQuery->latest()->paginate(10),
            'constraints' => $this->applyScope(
                AcademicPmcTimetableConstraint::query(),
                $user,
                [],
                ['generationRun' => ['program_id' => 'program', 'batch_id' => 'batch', 'term_id' => 'term']]
            )->latest()->paginate(15),
            'publishChecks' => $this->applyScope(
                AcademicPmcTimetablePublishCheck::query(),
                $user,
                [],
                ['generationRun' => ['program_id' => 'program', 'batch_id' => 'batch', 'term_id' => 'term']]
            )->latest()->paginate(15, ['*'], 'publish_checks_page'),
            'solverAttempts' => $this->applyScope(
                AcademicPmcTimetableSolverAttempt::query(),
                $user,
                [],
                ['generationRun' => ['program_id' => 'program', 'batch_id' => 'batch', 'term_id' => 'term']]
            )->latest()->paginate(10, ['*'], 'solver_page'),
            'resolutionActions' => $this->applyScope(
                AcademicPmcTimetableResolutionAction::with(['constraint', 'owner']),
                $user,
                [],
                ['generationRun' => ['program_id' => 'program', 'batch_id' => 'batch', 'term_id' => 'term']]
            )->latest()->paginate(15, ['*'], 'resolution_page'),
            'roomReadinessReviews' => $this->applyScope(
                AcademicPmcRoomReadinessReview::with(['classroom', 'generationRun', 'reviewer']),
                $user,
                ['program_id' => 'program', 'batch_id' => 'batch', 'term_id' => 'term'],
                ['generationRun' => ['program_id' => 'program', 'batch_id' => 'batch', 'term_id' => 'term']]
            )->latest()->paginate(15, ['*'], 'room_readiness_page'),
        ];
    }

    public function selectorOptions(): array
    {
        return [
            'programs' => Program::orderBy('name')->limit(100)->get(['id', 'name', 'code']),
            'batches' => Batch::with('program')->orderByDesc('id')->limit(150)->get(['id', 'program_id', 'name', 'code']),
            'terms' => Term::with(['program', 'batch'])->orderByDesc('id')->limit(150)->get(['id', 'program_id', 'batch_id', 'name', 'term_number', 'is_current']),
            'subjects' => Subject::with('program')->where('is_active', true)->orderBy('name')->limit(200)->get(['id', 'program_id', 'name', 'code', 'credits', 'type']),
            'students' => Student::with('user')->orderByDesc('id')->limit(200)->get(['id', 'user_id', 'program_id', 'batch_id', 'student_id', 'roll_number']),
            'teachers' => Teacher::with('user')->where('status', 'active')->orderBy('employee_id')->limit(150)->get(['id', 'user_id', 'employee_id', 'designation', 'employment_type', 'status']),
            'courseGroups' => AcademicPmcCourseGroup::with(['subject', 'program'])->orderBy('name')->limit(200)->get(['id', 'name', 'group_type', 'program_id', 'subject_id', 'current_strength', 'max_capacity']),
            'classrooms' => Classroom::where('is_active', true)->orderBy('name')->limit(150)->get(['id', 'name', 'room_number', 'capacity', 'type']),
            'slots' => TimetableSlot::where('is_active', true)->orderBy('sort_order')->orderBy('start_time')->get(['id', 'name', 'start_time', 'end_time']),
            'timetableVersions' => TimetableVersion::with(['program', 'term'])->latest()->limit(100)->get(['id', 'program_id', 'term_id', 'batch_id', 'version_number', 'status']),
            'officialTimetableItems' => $this->officialTimetableItemsQuery()
                ->with(['courseGroup.subject', 'teacher.user', 'classroom', 'slot', 'timetableVersion'])
                ->where('status', 'scheduled')
                ->latest()
                ->limit(200)
                ->get(),
        ];
    }

    public function officialPublishedVersionIds(): array
    {
        return TimetableVersion::where('status', 'published')->pluck('id')->all();
    }

    public function officialPublishedGenerationRunIds(): array
    {
        return AcademicPmcTimetableGenerationRun::whereIn('timetable_version_id', $this->officialPublishedVersionIds())->pluck('id')->all();
    }

    public function officialTimetableItemsQuery(): Builder
    {
        return AcademicPmcTimetableGenerationItem::query()
            ->where('official_status', 'published')
            ->whereNotNull('timetable_version_id')
            ->whereHas('timetableVersion', fn (Builder $query) => $query->where('status', 'published'));
    }

    public function parallelSlotGroups(Collection $items): Collection
    {
        return $items
            ->sortBy([
                ['day_of_week', 'asc'],
                ['timetable_slot_id', 'asc'],
                ['course_group_id', 'asc'],
            ])
            ->groupBy(fn ($item) => (int) $item->day_of_week . '-' . (int) $item->timetable_slot_id)
            ->map(function (Collection $slotItems) {
                $first = $slotItems->first();

                return [
                    'day_of_week' => (int) $first->day_of_week,
                    'slot_id' => (int) $first->timetable_slot_id,
                    'slot' => $first->slot,
                    'sessions' => $slotItems->values(),
                    'session_count' => $slotItems->count(),
                    'rooms' => $slotItems->pluck('classroom_id')->filter()->unique()->count(),
                    'faculty' => $slotItems->pluck('teacher_id')->filter()->unique()->count(),
                ];
            })
            ->values();
    }

    private function applyScope(Builder $query, User $user, array $directMap = [], array $relationMap = []): Builder
    {
        if ($this->policy->canIgnorePmcScope($user)) {
            return $query;
        }

        $scopes = [
            'program' => $this->policy->scopedProgramIds($user),
            'batch' => $this->policy->scopedBatchIds($user),
            'term' => $this->policy->scopedTermIds($user),
            'subject' => $this->policy->scopedSubjectIds($user),
        ];

        foreach ($directMap as $column => $scopeType) {
            if (! array_key_exists($scopeType, $scopes)) {
                continue;
            }
            $ids = $scopes[$scopeType];
            if ($ids === null) {
                continue;
            }
            if (! is_array($ids) || empty($ids)) {
                return $query->whereRaw('1 = 0');
            }
            $query->whereIn($column, $ids);
        }

        foreach ($relationMap as $relation => $mapping) {
            $query->whereHas($relation, function (Builder $relatedQuery) use ($mapping, $scopes): void {
                foreach ($mapping as $column => $scopeType) {
                    if (! array_key_exists($scopeType, $scopes)) {
                        continue;
                    }
                    $ids = $scopes[$scopeType];
                    if ($ids === null) {
                        continue;
                    }
                    if (! is_array($ids) || empty($ids)) {
                        $relatedQuery->whereRaw('1 = 0');
                        continue;
                    }
                    $relatedQuery->whereIn($column, $ids);
                }
            });
        }

        return $query;
    }
}
