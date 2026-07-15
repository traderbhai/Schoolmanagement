<?php

namespace App\Services;

use App\Models\AcademicPmcCourseAllocationBatch;
use App\Models\AcademicPmcCourseAllocationException;
use App\Models\AcademicPmcCourseGroup;
use App\Models\AcademicPmcCourseGroupAdjustment;
use App\Models\AcademicPmcCourseGroupMember;
use App\Models\AcademicPmcDataReconciliationCheck;
use App\Models\AcademicPmcDataReconciliationRun;
use App\Models\AcademicPmcElectiveChoice;
use App\Models\AcademicPmcExportLog;
use App\Models\AcademicPmcFacultyAvailabilityRequest;
use App\Models\AcademicPmcFacultyAssignmentAcknowledgement;
use App\Models\AcademicPmcFacultyLoadReview;
use App\Models\AcademicPmcFacultyPreference;
use App\Models\AcademicPmcGroupBuildRun;
use App\Models\AcademicPmcGroupFacultyAssignment;
use App\Models\AcademicPmcLockedSlot;
use App\Models\AcademicPmcRoomReadinessReview;
use App\Models\AcademicPmcStudentBasketAcknowledgement;
use App\Models\AcademicPmcStudentCourseAllocation;
use App\Models\AcademicPmcSubstitutionRecommendation;
use App\Models\AcademicPmcTimetableChangeRequest;
use App\Models\AcademicPmcTimetableConstraint;
use App\Models\AcademicPmcTimetableGenerationItem;
use App\Models\AcademicPmcTimetableGenerationRun;
use App\Models\AcademicPmcTimetableImpactRecord;
use App\Models\AcademicPmcTimetableNotification;
use App\Models\AcademicPmcTimetablePublishCheck;
use App\Models\AcademicPmcTimetableQualityScore;
use App\Models\AcademicPmcTimetableResolutionAction;
use App\Models\AcademicPmcTimetableSessionDemand;
use App\Models\AcademicPmcTimetableSolverAttempt;
use App\Models\AcademicPmcTimetableVersionWorkflow;
use App\Models\AcademicPmcWorkloadRule;
use App\Models\AcademicYear;
use App\Models\Batch;
use App\Models\Classroom;
use App\Models\Course;
use App\Models\Department;
use App\Models\DepartmentActivityLog;
use App\Models\ElectiveRegistrationWindow;
use App\Models\Program;
use App\Models\Semester;
use App\Models\Student;
use App\Models\StudentSubjectEnrollment;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\Term;
use App\Models\TimetableEntry;
use App\Models\TimetableSlot;
use App\Models\TimetableVersion;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AcademicPmcTimetableV041Service
{
    public function __construct(private AcademicPmcAccessPolicyService $policy) {}

    public function dashboard(User $user): array
    {
        $scopedAllocationBatches = $this->applyScope(AcademicPmcCourseAllocationBatch::query(), $user, ['program_id' => 'program', 'batch_id' => 'batch', 'term_id' => 'term']);
        $scopedAllocations = $this->applyScope(AcademicPmcStudentCourseAllocation::query(), $user, [], [
            'term' => ['id' => 'term'],
            'student' => ['program_id' => 'program', 'batch_id' => 'batch'],
        ]);
        $scopedGroups = $this->applyScope(AcademicPmcCourseGroup::query(), $user, ['program_id' => 'program', 'batch_id' => 'batch', 'term_id' => 'term']);
        $scopedFacultyAssignments = $this->applyScope(AcademicPmcGroupFacultyAssignment::query(), $user, [], ['courseGroup' => ['program_id' => 'program', 'batch_id' => 'batch', 'term_id' => 'term']]);
        $scopedLocks = $this->applyScope(AcademicPmcLockedSlot::query(), $user, ['program_id' => 'program', 'batch_id' => 'batch', 'term_id' => 'term']);
        $scopedRuns = $this->applyScope(AcademicPmcTimetableGenerationRun::query(), $user, ['program_id' => 'program', 'batch_id' => 'batch', 'term_id' => 'term']);
        $scopedGenerationRunIds = (clone $scopedRuns)->pluck('id');
        $scopedConstraints = AcademicPmcTimetableConstraint::query()
            ->when(! $this->policy->canIgnorePmcScope($user), function (Builder $query) use ($scopedGenerationRunIds) {
                if ($scopedGenerationRunIds->isEmpty()) {
                    $query->whereRaw('1 = 0');
                } else {
                    $query->whereIn('generation_run_id', $scopedGenerationRunIds);
                }
            });
        $scopedQuality = AcademicPmcTimetableQualityScore::query()
            ->when(! $this->policy->canIgnorePmcScope($user), function (Builder $query) use ($scopedGenerationRunIds) {
                if ($scopedGenerationRunIds->isEmpty()) {
                    $query->whereRaw('1 = 0');
                } else {
                    $query->whereIn('generation_run_id', $scopedGenerationRunIds);
                }
            });

        return [
            'scopeLabel' => $this->policy->scopeLabel($user),
            'kpis' => [
                'allocation_batches' => $scopedAllocationBatches->count(),
                'student_allocations' => $scopedAllocations->count(),
                'course_groups' => $scopedGroups->count(),
                'faculty_assignments' => $scopedFacultyAssignments->count(),
                'locked_slots' => $scopedLocks->where('status', 'active')->count(),
                'hard_conflicts' => (clone $scopedConstraints)->where('severity', 'hard')->count(),
                'soft_warnings' => (clone $scopedConstraints)->where('severity', 'soft')->count(),
                'quality_score' => (int) round((clone $scopedQuality)->avg('overall_score') ?: 0),
            ],
            'readiness' => $this->readinessChecklist($user),
            'launchControl' => $this->launchControl($user),
            'basketDiagnostics' => $this->courseBasketDiagnostics($user),
            'allocationPressureDiagnostics' => $this->allocationPressureDiagnostics($user),
            'groupDiagnostics' => $this->courseGroupDiagnostics($user),
            'facultyDiagnostics' => $this->facultyAllocationDiagnostics($user),
            'facultySuitabilityDiagnostics' => $this->facultySuitabilityDiagnostics(null, $user),
            'readinessInputDiagnostics' => $this->readinessInputDiagnostics($user),
            'generationDiagnostics' => $this->generationValidationDiagnostics($user),
            'publishReadinessDiagnostics' => $this->publishFreezeReadinessDiagnostics($user),
            'substitutionEmergencyDiagnostics' => $this->substitutionEmergencyDiagnostics($user),
            'latestRun' => $scopedRuns->latest()->first(),
            'constraints' => $scopedConstraints->latest()->limit(8)->get(),
            'notifications' => AcademicPmcTimetableNotification::latest()->limit(8)->get(),
        ];
    }

    public function surface(User $user, string $surface, array $filters = []): array
    {
        return match ($surface) {
            'course-allocation' => $this->allocationSurface($user, $filters),
            'elective-allocation' => $this->allocationSurface($user, $filters + ['allocation_type' => 'elective']),
            'student-course-baskets' => $this->studentBasketSurface($user, $filters),
            'sections', 'course-groups', 'group-memberships' => $this->groupSurface($user, $filters),
            'section-faculty-allocation', 'faculty-preferences', 'load-planning', 'area-chair-recommendations' => $this->facultySurface($user, $surface, $filters),
            'locked-slots', 'timetable-readiness-v041' => $this->lockedSlotSurface($user, $filters),
            'timetable-generator', 'timetable-suggestions', 'timetable-quality' => $this->generatorSurface($user, $surface, $filters),
            'timetable-planner' => $this->plannerSurface($user, $filters),
            'timetable-versions-v041', 'timetable-impact', 'timetable-freeze' => $this->versionSurface($user, $filters),
            'substitution-intelligence', 'timetable-change-requests' => $this->substitutionSurface($user, $filters),
            default => $this->reportsSurface($user, $filters),
        } + [
            'surface' => $surface,
            'savedViews' => \App\Models\AcademicPmcSavedView::where('surface', $surface)->where('user_id', $user->id)->latest()->get(),
            'selectorOptions' => $this->selectorOptions(),
        ];
    }

    public function exportSurface(User $actor, string $surface, array $filters = []): StreamedResponse
    {
        $this->policy->authorizeRead($actor);

        [$headers, $rows] = match ($surface) {
            'course-allocation', 'elective-allocation', 'student-course-baskets' => $this->courseAllocationExportRows($actor, $filters),
            'sections', 'course-groups', 'group-memberships' => $this->courseGroupExportRows($actor, $filters),
            'timetable-planner' => $this->timetablePlannerExportRows($actor, $filters),
            default => [['surface', 'message'], collect([[$surface, 'Export is not configured for this v0.041 surface yet.']])],
        };

        AcademicPmcExportLog::create([
            'user_id' => $actor->id,
            'report_key' => 'pmc_v041_' . $surface,
            'filters' => $filters,
            'row_count' => $rows->count(),
            'exported_at' => now(),
            'metadata' => ['version' => 'PMC OS v0.041', 'surface' => $surface],
        ]);

        return response()->streamDownload(function () use ($headers, $rows) {
            $out = fopen('php://output', 'w');
            fputcsv($out, $headers);
            foreach ($rows as $row) {
                fputcsv($out, $row);
            }
            fclose($out);
        }, 'pmc-v041-' . $surface . '-' . now()->format('YmdHis') . '.csv', ['Content-Type' => 'text/csv']);
    }

    public function studentScopedTimetable(User $user, array $filters = []): array
    {
        $student = $user->student;
        abort_unless($student, 403);

        $groupIds = AcademicPmcCourseGroupMember::where('student_id', $student->id)->where('status', 'active')->pluck('course_group_id');
        $items = $this->officialTimetableItemsQuery()
            ->with(['courseGroup.subject', 'teacher.user', 'classroom', 'slot', 'timetableVersion'])
            ->whereIn('course_group_id', $groupIds)
            ->when($filters['day_of_week'] ?? null, fn ($q, $day) => $q->where('day_of_week', $day))
            ->orderBy('day_of_week')
            ->orderBy('timetable_slot_id')
            ->paginate(25)
            ->withQueryString();

        return [
            'title' => 'My PMC Group Timetable',
            'scopeLabel' => $student->user?->name ?? 'Student',
            'items' => $items,
            'groupCount' => $groupIds->count(),
            'filters' => $filters,
            'mode' => 'student',
            'selectorOptions' => $this->selectorOptions(),
        ];
    }

    public function studentCourseBasketSelfService(User $user, array $filters = []): array
    {
        $student = $user->student;
        abort_unless($student, 403);

        $allocations = AcademicPmcStudentCourseAllocation::with([
            'subject',
            'term',
            'allocationBatch',
            'groupMemberships.courseGroup.subject',
        ])
            ->where('student_id', $student->id)
            ->when($filters['status'] ?? null, fn ($q, $status) => $q->where('basket_status', $status))
            ->when($filters['type'] ?? null, fn ($q, $type) => $q->where('allocation_type', $type))
            ->orderByRaw('case when waitlisted = 1 then 1 else 0 end')
            ->orderBy('allocation_type')
            ->orderBy('priority_rank')
            ->paginate(20)
            ->withQueryString();

        $allocationIds = AcademicPmcStudentCourseAllocation::where('student_id', $student->id)->pluck('id');
        $groupIds = AcademicPmcCourseGroupMember::where('student_id', $student->id)
            ->where('status', 'active')
            ->pluck('course_group_id');

        $timetableItems = $this->officialTimetableItemsQuery()
            ->with(['courseGroup.subject', 'teacher.user', 'classroom', 'slot', 'timetableVersion'])
            ->whereIn('course_group_id', $groupIds)
            ->orderBy('day_of_week')
            ->orderBy('timetable_slot_id')
            ->limit(12)
            ->get();

        $acknowledgements = AcademicPmcStudentBasketAcknowledgement::with(['allocation.subject', 'decider'])
            ->where('student_id', $student->id)
            ->latest()
            ->paginate(10, ['*'], 'requests_page')
            ->withQueryString();

        $openRequests = AcademicPmcStudentBasketAcknowledgement::where('student_id', $student->id)
            ->whereIn('status', ['submitted', 'objection_submitted', 'under_review'])
            ->count();

        return [
            'title' => 'My Course Basket',
            'scopeLabel' => $student->user?->name ?? 'Student',
            'student' => $student,
            'allocations' => $allocations,
            'timetableItems' => $timetableItems,
            'acknowledgements' => $acknowledgements,
            'allocationOptions' => AcademicPmcStudentCourseAllocation::with('subject')
                ->whereIn('id', $allocationIds)
                ->orderBy('allocation_type')
                ->get(),
            'metrics' => [
                'allocated' => AcademicPmcStudentCourseAllocation::where('student_id', $student->id)->where('waitlisted', false)->count(),
                'waitlisted' => AcademicPmcStudentCourseAllocation::where('student_id', $student->id)->where('waitlisted', true)->count(),
                'grouped' => $groupIds->count(),
                'classes' => $timetableItems->count(),
                'open_requests' => $openRequests,
            ],
            'filters' => $filters,
        ];
    }

    public function submitStudentBasketAcknowledgement(User $user, array $data): AcademicPmcStudentBasketAcknowledgement
    {
        $student = $user->student;
        abort_unless($student, 403);

        $allocation = null;
        if (! empty($data['student_course_allocation_id'])) {
            $allocation = AcademicPmcStudentCourseAllocation::where('student_id', $student->id)
                ->findOrFail($data['student_course_allocation_id']);
        }

        $ack = AcademicPmcStudentBasketAcknowledgement::create([
            'student_id' => $student->id,
            'student_course_allocation_id' => $allocation?->id,
            'timetable_version_id' => $data['timetable_version_id'] ?? null,
            'generation_run_id' => $data['generation_run_id'] ?? null,
            'acknowledgement_type' => $data['acknowledgement_type'],
            'status' => in_array($data['acknowledgement_type'], ['objection', 'add_drop_request'], true)
                ? 'objection_submitted'
                : 'acknowledged',
            'reason' => $data['reason'] ?? null,
            'student_note' => $data['student_note'] ?? null,
            'submitted_at' => now(),
            'metadata' => [
                'source' => 'student_course_basket_v090',
                'student_user_id' => $user->id,
            ],
        ]);

        if ($allocation && in_array($data['acknowledgement_type'], ['objection', 'add_drop_request'], true)) {
            AcademicPmcCourseAllocationException::create([
                'student_course_allocation_id' => $allocation->id,
                'student_id' => $student->id,
                'subject_id' => $allocation->subject_id,
                'term_id' => $allocation->term_id,
                'exception_type' => $data['acknowledgement_type'] === 'add_drop_request' ? 'add_drop' : 'student_objection',
                'status' => 'pending',
                'credit_delta' => 0,
                'requires_dean_approval' => false,
                'reason' => $data['reason'] ?: ($data['student_note'] ?? 'Student-submitted basket request.'),
                'requested_by' => $user->id,
                'requested_at' => now(),
                'metadata' => [
                    'student_basket_acknowledgement_id' => $ack->id,
                    'source' => 'student_course_basket_v090',
                ],
            ]);
        }

        $this->audit($user, 'student_basket_acknowledgement_submitted', 'Student submitted PMC course basket acknowledgement/request.', $ack, [
            'student_id' => $student->id,
            'allocation_id' => $allocation?->id,
            'type' => $ack->acknowledgement_type,
        ]);

        return $ack;
    }

    public function studentElectiveChoicePortal(User $user, array $filters = []): array
    {
        $student = $user->student;
        abort_unless($student, 403);

        $termId = $filters['term_id'] ?? $student->current_term_id ?? Term::where('program_id', $student->program_id)->where('is_current', true)->value('id');
        $window = ElectiveRegistrationWindow::where('program_id', $student->program_id)
            ->where('term_id', $termId)
            ->where('status', 'open')
            ->orderByDesc('closes_at')
            ->first();

        $subjects = Subject::where('is_active', true)
            ->where(function ($query) use ($student) {
                $query->where('program_id', $student->program_id)->orWhereNull('program_id');
            })
            ->where(function ($query) {
                $query->where('type', 'elective')
                    ->orWhere('code', 'like', '%ELEC%')
                    ->orWhere('name', 'like', '%Elective%')
                    ->orWhere('name', 'like', '%Analytics%')
                    ->orWhere('name', 'like', '%Lab%');
            })
            ->orderBy('name')
            ->limit(50)
            ->get();

        $choices = AcademicPmcElectiveChoice::with(['subject', 'term'])
            ->where('student_id', $student->id)
            ->when($termId, fn ($q, $id) => $q->where('term_id', $id))
            ->orderBy('preference_rank')
            ->paginate(15)
            ->withQueryString();

        return [
            'title' => 'My Elective Choices',
            'scopeLabel' => $student->user?->name ?? 'Student',
            'student' => $student,
            'window' => $window,
            'isOpen' => $window?->isOpen() ?? false,
            'termId' => $termId,
            'subjects' => $subjects,
            'choices' => $choices,
            'terms' => Term::where('program_id', $student->program_id)->orderByDesc('id')->limit(20)->get(),
            'metrics' => [
                'submitted' => AcademicPmcElectiveChoice::where('student_id', $student->id)->when($termId, fn ($q, $id) => $q->where('term_id', $id))->count(),
                'allocated' => AcademicPmcElectiveChoice::where('student_id', $student->id)->when($termId, fn ($q, $id) => $q->where('term_id', $id))->where('status', 'allocated')->count(),
                'waitlisted' => AcademicPmcElectiveChoice::where('student_id', $student->id)->when($termId, fn ($q, $id) => $q->where('term_id', $id))->where('status', 'waitlisted')->count(),
                'max_selections' => $window?->max_selections ?? 0,
            ],
            'filters' => $filters,
        ];
    }

    public function submitStudentElectiveChoices(User $user, array $data): void
    {
        $student = $user->student;
        abort_unless($student, 403);

        $termId = $data['term_id'] ?? $student->current_term_id;
        $window = ElectiveRegistrationWindow::where('program_id', $student->program_id)
            ->where('term_id', $termId)
            ->where('status', 'open')
            ->orderByDesc('closes_at')
            ->first();
        abort_unless($window && $window->isOpen(), 422, 'The elective choice window is not open.');

        $subjectIds = array_values(array_filter(array_map('intval', $data['subject_ids'] ?? [])));
        if (count($subjectIds) !== count(array_unique($subjectIds))) {
            abort(422, 'Each elective preference must be unique.');
        }
        if (empty($subjectIds) || count($subjectIds) > (int) $window->max_selections) {
            abort(422, 'Submit between 1 and ' . $window->max_selections . ' elective choices.');
        }

        $validCount = Subject::whereIn('id', $subjectIds)
            ->where('is_active', true)
            ->where(function ($query) use ($student) {
                $query->where('program_id', $student->program_id)->orWhereNull('program_id');
            })
            ->count();
        abort_unless($validCount === count($subjectIds), 422, 'One or more selected electives are not valid for your program.');

        foreach ($subjectIds as $index => $subjectId) {
            AcademicPmcElectiveChoice::updateOrCreate(
                ['student_id' => $student->id, 'term_id' => $termId, 'subject_id' => $subjectId],
                [
                    'program_id' => $student->program_id,
                    'batch_id' => $student->batch_id,
                    'preference_rank' => $index + 1,
                    'priority_score' => max(0, 100 - ($index * 10)),
                    'status' => 'submitted',
                    'choice_source' => 'student_self_service',
                    'decision_reason' => null,
                    'metadata' => [
                        'window_id' => $window->id,
                        'submitted_by_user_id' => $user->id,
                        'version' => 'PMC OS v0.091',
                    ],
                ]
            );
        }

        AcademicPmcElectiveChoice::where('student_id', $student->id)
            ->where('term_id', $termId)
            ->whereNotIn('subject_id', $subjectIds)
            ->whereIn('status', ['submitted', 'waitlisted'])
            ->update(['status' => 'withdrawn', 'decision_reason' => 'Withdrawn by updated student preference submission.']);

        $this->audit($user, 'student_elective_choices_submitted', 'Student submitted ranked elective choices.', $student, [
            'term_id' => $termId,
            'subject_ids' => $subjectIds,
            'window_id' => $window->id,
        ]);
    }

    public function reviewStudentBasketAcknowledgement(User $actor, AcademicPmcStudentBasketAcknowledgement $ack, string $status, ?string $note): AcademicPmcStudentBasketAcknowledgement
    {
        $this->policy->authorizeWriteScope($actor, [
            'program_id' => $ack->student?->program_id,
            'batch_id' => $ack->student?->batch_id,
            'term_id' => $ack->allocation?->term_id,
            'subject_id' => $ack->allocation?->subject_id,
        ]);

        $ack->update([
            'status' => $status,
            'pmc_note' => $note,
            'decided_by' => $actor->id,
            'decided_at' => now(),
        ]);

        $this->audit($actor, 'student_basket_acknowledgement_reviewed', 'PMC reviewed a student course basket acknowledgement/request.', $ack, [
            'student_id' => $ack->student_id,
            'status' => $status,
        ]);

        return $ack;
    }

    public function refreshDataReconciliation(User $actor): array
    {
        $this->policy->authorizeRead($actor);

        $checks = [
            $this->reconcileGeneratedOperationalSync($actor),
            $this->reconcileAllocationEnrollmentLinks($actor),
            $this->reconcileGroupMembershipAllocations($actor),
            $this->reconcileDeliveryTrackers($actor),
            $this->reconcilePublishedNotifications($actor),
        ];

        $this->audit($actor, 'academic_pmc_v092_data_reconciliation_refreshed', 'PMC data reconciliation checks refreshed.', null, [
            'checks' => count($checks),
            'mismatches' => collect($checks)->sum('mismatch_count'),
        ]);

        return [
            'checks' => count($checks),
            'mismatches' => collect($checks)->sum('mismatch_count'),
            'critical' => collect($checks)->where('severity', 'critical')->count(),
        ];
    }

    public function dataReconciliationSurface(User $user, array $filters = []): array
    {
        $this->policy->authorizeRead($user);

        $auditActorIds = DepartmentActivityLog::query()
            ->whereIn('action', [
                'academic_pmc_v092_data_reconciliation_refreshed',
                'academic_pmc_v093_data_reconciliation_repaired',
                'academic_pmc_v105_reconciliation_stale_run_closed',
            ])
            ->whereNotNull('actor_user_id')
            ->distinct()
            ->pluck('actor_user_id');
        $auditActors = User::whereIn('id', $auditActorIds)
            ->orderBy('name')
            ->get(['id', 'name']);

        $checks = $this->dataReconciliationQuery($filters)
            ->orderByRaw("case severity when 'critical' then 1 when 'high' then 2 when 'medium' then 3 else 4 end")
            ->latest('checked_at')
            ->paginate(20)
            ->withQueryString();

        $runsQuery = AcademicPmcDataReconciliationRun::with('starter')
            ->when($filters['run_status'] ?? null, fn ($q, $status) => $q->where('status', $status));
        $lastCompletedRun = AcademicPmcDataReconciliationRun::where('status', 'completed')->latest('finished_at')->first();
        $lastFailedRun = AcademicPmcDataReconciliationRun::where('status', 'failed')->latest('finished_at')->first();
        $staleRunningRuns = AcademicPmcDataReconciliationRun::where('status', 'running')
            ->where('started_at', '<', now()->subMinutes(30))
            ->count();

        return [
            'title' => 'PMC Data Reconciliation',
            'scopeLabel' => $this->policy->scopeLabel($user),
            'checks' => $checks,
            'filters' => $filters,
            'runs' => (clone $runsQuery)
                ->latest('started_at')
                ->limit(8)
                ->get(),
            'auditTrail' => $this->reconciliationAuditTrailQuery($filters)
                ->latest()
                ->limit(8)
                ->get(),
            'runSummary' => [
                'total' => AcademicPmcDataReconciliationRun::count(),
                'completed' => AcademicPmcDataReconciliationRun::where('status', 'completed')->count(),
                'failed' => AcademicPmcDataReconciliationRun::where('status', 'failed')->count(),
                'running' => AcademicPmcDataReconciliationRun::where('status', 'running')->count(),
                'manual_repairs' => AcademicPmcDataReconciliationRun::where('source', 'manual_ui_repair')->count(),
            ],
            'schedulerHealth' => [
                'status' => $staleRunningRuns > 0 ? 'warning' : ($lastCompletedRun ? 'healthy' : 'no_runs'),
                'label' => $staleRunningRuns > 0 ? 'Attention Needed' : ($lastCompletedRun ? 'Healthy' : 'No Runs'),
                'last_completed_at' => $lastCompletedRun?->finished_at,
                'last_failed_at' => $lastFailedRun?->finished_at,
                'stale_running' => $staleRunningRuns,
                'recommendation' => $staleRunningRuns > 0
                    ? 'Review stale running reconciliation jobs and rerun after confirming no process is active.'
                    : ($lastCompletedRun ? 'Scheduler has at least one completed reconciliation run.' : 'Run reconciliation once to establish scheduler baseline.'),
            ],
            'auditActors' => $auditActors,
            'summary' => [
                'total' => AcademicPmcDataReconciliationCheck::count(),
                'ok' => AcademicPmcDataReconciliationCheck::where('status', 'ok')->count(),
                'warn' => AcademicPmcDataReconciliationCheck::where('status', 'warn')->count(),
                'block' => AcademicPmcDataReconciliationCheck::where('status', 'block')->count(),
                'mismatches' => AcademicPmcDataReconciliationCheck::sum('mismatch_count'),
            ],
        ];
    }

    public function repairDataReconciliation(User $actor, AcademicPmcDataReconciliationCheck $check): array
    {
        $this->policy->authorizeWrite($actor);

        $result = match ($check->check_key) {
            'generated_operational_sync' => $this->repairGeneratedOperationalSync($actor),
            'allocation_enrollment_links' => $this->repairAllocationEnrollmentLinks($actor),
            'group_membership_allocation_links' => $this->repairGroupMembershipAllocationLinks($actor),
            'scheduled_groups_delivery_trackers' => $this->repairScheduledGroupDeliveryTrackers($actor),
            'published_version_notifications' => $this->repairPublishedVersionNotifications($actor),
            default => ['repaired' => 0, 'message' => 'No automated repair is available for this check.'],
        };

        $this->refreshDataReconciliation($actor);

        $this->audit($actor, 'academic_pmc_v093_data_reconciliation_repaired', 'PMC reconciliation repair executed.', $check, [
            'check_key' => $check->check_key,
            'repaired' => $result['repaired'],
            'message' => $result['message'],
        ]);

        return $result;
    }

    public function exportDataReconciliation(User $actor, array $filters = []): StreamedResponse
    {
        $this->policy->authorizeRead($actor);

        $rows = $this->dataReconciliationQuery($filters)
            ->latest('checked_at')
            ->limit(1000)
            ->get();

        AcademicPmcExportLog::create([
            'user_id' => $actor->id,
            'report_key' => 'data_reconciliation',
            'filters' => $filters,
            'row_count' => $rows->count(),
            'exported_at' => now(),
            'metadata' => ['version' => 'PMC OS v0.095', 'surface' => 'data_reconciliation'],
        ]);

        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['check', 'group', 'status', 'severity', 'expected', 'actual', 'mismatch', 'recommended_action', 'sample_mismatches', 'checked_at']);
            foreach ($rows as $row) {
                fputcsv($out, [
                    $row->title,
                    $row->check_group,
                    $row->status,
                    $row->severity,
                    $row->expected_count,
                    $row->actual_count,
                    $row->mismatch_count,
                    $row->recommended_action,
                    collect(data_get($row->details, 'sample_mismatches', []))->pluck('label')->filter()->join(' | '),
                    optional($row->checked_at)->toDateTimeString(),
                ]);
            }
            fclose($out);
        }, 'pmc-data-reconciliation-' . now()->format('YmdHis') . '.csv', ['Content-Type' => 'text/csv']);
    }

    public function exportDataReconciliationRuns(User $actor, array $filters = []): StreamedResponse
    {
        $this->policy->authorizeRead($actor);

        $rows = AcademicPmcDataReconciliationRun::with('starter')
            ->when($filters['run_status'] ?? null, fn ($q, $status) => $q->where('status', $status))
            ->latest('started_at')
            ->limit(1000)
            ->get();

        AcademicPmcExportLog::create([
            'user_id' => $actor->id,
            'report_key' => 'data_reconciliation_runs',
            'filters' => ['run_status' => $filters['run_status'] ?? null],
            'row_count' => $rows->count(),
            'exported_at' => now(),
            'metadata' => ['version' => 'PMC OS v0.102', 'surface' => 'data_reconciliation_run_history'],
        ]);

        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['started_at', 'finished_at', 'source', 'status', 'repair_requested', 'checks', 'mismatches', 'critical', 'repaired', 'actor', 'failure_reason']);
            foreach ($rows as $row) {
                fputcsv($out, [
                    optional($row->started_at)->toDateTimeString(),
                    optional($row->finished_at)->toDateTimeString(),
                    $row->source,
                    $row->status,
                    $row->repair_requested ? 'yes' : 'no',
                    $row->checks_count,
                    $row->mismatch_count,
                    $row->critical_count,
                    $row->repaired_count,
                    $row->starter?->name,
                    $row->failure_reason,
                ]);
            }
            fclose($out);
        }, 'pmc-data-reconciliation-runs-' . now()->format('YmdHis') . '.csv', ['Content-Type' => 'text/csv']);
    }

    public function exportDataReconciliationAudit(User $actor, array $filters = []): StreamedResponse
    {
        $this->policy->authorizeRead($actor);

        $rows = $this->reconciliationAuditTrailQuery($filters)
            ->latest()
            ->limit(1000)
            ->get();

        AcademicPmcExportLog::create([
            'user_id' => $actor->id,
            'report_key' => 'data_reconciliation_audit',
            'filters' => [
                'action' => $filters['audit_action'] ?? null,
                'actor_user_id' => $filters['audit_actor_id'] ?? null,
                'from' => $filters['audit_from'] ?? null,
                'to' => $filters['audit_to'] ?? null,
            ],
            'row_count' => $rows->count(),
            'exported_at' => now(),
            'metadata' => ['version' => 'PMC OS v0.110', 'surface' => 'data_reconciliation_audit_trail'],
        ]);

        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['created_at', 'actor', 'action', 'description', 'details', 'subject_type', 'subject_id']);
            foreach ($rows as $row) {
                fputcsv($out, [
                    optional($row->created_at)->toDateTimeString(),
                    $row->actor?->name ?: 'System',
                    $row->action,
                    $row->description,
                    data_get($row->metadata, 'reason') ?: data_get($row->metadata, 'message') ?: data_get($row->metadata, 'check_key') ?: '',
                    $row->subject_type ? class_basename($row->subject_type) : '',
                    $row->subject_id,
                ]);
            }
            fclose($out);
        }, 'pmc-data-reconciliation-audit-' . now()->format('YmdHis') . '.csv', ['Content-Type' => 'text/csv']);
    }

    private function dataReconciliationQuery(array $filters): Builder
    {
        return AcademicPmcDataReconciliationCheck::with('checker')
            ->when($filters['status'] ?? null, fn ($q, $status) => $q->where('status', $status))
            ->when($filters['group'] ?? null, fn ($q, $group) => $q->where('check_group', $group));
    }

    private function reconciliationAuditTrailQuery(array $filters = []): Builder
    {
        return DepartmentActivityLog::with('actor')
            ->where(function ($query) {
                $query->whereIn('action', [
                    'academic_pmc_v092_data_reconciliation_refreshed',
                    'academic_pmc_v093_data_reconciliation_repaired',
                    'academic_pmc_v105_reconciliation_stale_run_closed',
                ])
                    ->orWhere('subject_type', AcademicPmcDataReconciliationCheck::class)
                    ->orWhere('subject_type', AcademicPmcDataReconciliationRun::class);
            })
            ->when($filters['audit_action'] ?? null, fn ($q, $action) => $q->where('action', $action))
            ->when($filters['audit_actor_id'] ?? null, fn ($q, $actorId) => $q->where('actor_user_id', $actorId))
            ->when($filters['audit_from'] ?? null, fn ($q, $date) => $q->whereDate('created_at', '>=', $date))
            ->when($filters['audit_to'] ?? null, fn ($q, $date) => $q->whereDate('created_at', '<=', $date));
    }

    private function repairGeneratedOperationalSync(User $actor): array
    {
        $canonicalRepair = app(TimetableCanonicalRepairService::class)->repairPublishedRunItems($actor);
        $runs = AcademicPmcTimetableGenerationRun::whereIn('timetable_version_id', $this->officialPublishedVersionIds())
            ->with('items')
            ->get();
        $repaired = 0;

        foreach ($runs as $run) {
            $version = TimetableVersion::find($run->timetable_version_id);
            if (! $version) {
                continue;
            }
            $repaired += $this->syncRunToOperationalTimetable($run, $version, $actor);
        }

        return [
            'repaired' => $repaired + (int) $canonicalRepair['repaired'],
            'message' => "{$canonicalRepair['repaired']} canonical item(s) repaired and {$repaired} generated timetable item(s) synced to operational timetable entries.",
        ];
    }

    private function repairAllocationEnrollmentLinks(User $actor): array
    {
        $repaired = 0;
        AcademicPmcStudentCourseAllocation::where('waitlisted', false)
            ->whereIn('basket_status', ['allocated', 'approved', 'locked'])
            ->whereNull('student_subject_enrollment_id')
            ->whereNotNull('student_id')
            ->whereNotNull('subject_id')
            ->chunkById(100, function ($allocations) use (&$repaired) {
                foreach ($allocations as $allocation) {
                    $enrollment = StudentSubjectEnrollment::firstOrCreate(
                        [
                            'student_id' => $allocation->student_id,
                            'subject_id' => $allocation->subject_id,
                            'term_id' => $allocation->term_id,
                        ],
                        [
                            'enrollment_type' => $allocation->allocation_type === 'elective' ? 'elective' : 'compulsory',
                            'status' => 'active',
                        ]
                    );
                    $allocation->update([
                        'student_subject_enrollment_id' => $enrollment->id,
                        'metadata' => array_merge($allocation->metadata ?: [], [
                            'reconciliation_repair' => 'student_subject_enrollment_linked',
                            'repaired_at' => now()->toDateTimeString(),
                        ]),
                    ]);
                    $repaired++;
                }
            });

        return ['repaired' => $repaired, 'message' => "{$repaired} course allocations were linked to student subject enrollments."];
    }

    private function repairGroupMembershipAllocationLinks(User $actor): array
    {
        $repaired = 0;
        AcademicPmcCourseGroupMember::with('courseGroup')
            ->where('status', 'active')
            ->whereNull('student_course_allocation_id')
            ->chunkById(100, function ($memberships) use (&$repaired, $actor) {
                foreach ($memberships as $membership) {
                    $group = $membership->courseGroup;
                    if (! $group) {
                        continue;
                    }
                    $allocation = AcademicPmcStudentCourseAllocation::where('student_id', $membership->student_id)
                        ->where('subject_id', $group->subject_id)
                        ->where('term_id', $group->term_id)
                        ->where('waitlisted', false)
                        ->whereIn('basket_status', ['allocated', 'approved', 'locked'])
                        ->first();
                    if (! $allocation) {
                        continue;
                    }
                    $membership->update([
                        'student_course_allocation_id' => $allocation->id,
                        'moved_by' => $membership->moved_by ?: $actor->id,
                        'metadata' => array_merge($membership->metadata ?: [], [
                            'reconciliation_repair' => 'allocation_linked',
                            'repaired_at' => now()->toDateTimeString(),
                        ]),
                    ]);
                    $repaired++;
                }
            });

        return ['repaired' => $repaired, 'message' => "{$repaired} active group memberships were linked to matching allocations."];
    }

    private function repairScheduledGroupDeliveryTrackers(User $actor): array
    {
        $groupIds = AcademicPmcTimetableGenerationItem::where('status', 'scheduled')
            ->whereIn('generation_run_id', $this->officialPublishedGenerationRunIds())
            ->whereNotNull('course_group_id')
            ->distinct()
            ->pluck('course_group_id');
        $groups = AcademicPmcCourseGroup::whereIn('id', $groupIds)->get();
        $repaired = 0;

        foreach ($groups as $group) {
            $primaryAssignment = AcademicPmcGroupFacultyAssignment::where('course_group_id', $group->id)
                ->where('assignment_role', 'primary')
                ->first();
            $tracker = \App\Models\AcademicPmcGroupDeliveryTracker::firstOrCreate(
                ['course_group_id' => $group->id],
                [
                    'program_id' => $group->program_id,
                    'batch_id' => $group->batch_id,
                    'term_id' => $group->term_id,
                    'subject_id' => $group->subject_id,
                    'teacher_id' => $primaryAssignment?->teacher_id,
                    'owner_user_id' => $primaryAssignment?->teacher?->user_id ?: $actor->id,
                    'planned_sessions' => AcademicPmcTimetableGenerationItem::where('course_group_id', $group->id)->where('status', 'scheduled')->whereIn('generation_run_id', $this->officialPublishedGenerationRunIds())->count(),
                    'status' => 'monitoring',
                    'risk_band' => 'low',
                    'risk_reasons' => ['Created by PMC data reconciliation repair.'],
                    'recommended_actions' => ['Review delivery plan and assign session logs.'],
                    'metadata' => ['reconciliation_repair' => 'delivery_tracker_created', 'repaired_at' => now()->toDateTimeString()],
                ]
            );
            if ($tracker->wasRecentlyCreated) {
                $repaired++;
            }
        }

        return ['repaired' => $repaired, 'message' => "{$repaired} missing group delivery trackers were created."];
    }

    private function repairPublishedVersionNotifications(User $actor): array
    {
        $repaired = 0;
        TimetableVersion::where('status', 'published')->chunkById(100, function ($versions) use (&$repaired, $actor) {
            foreach ($versions as $version) {
                $notification = AcademicPmcTimetableNotification::firstOrCreate(
                    [
                        'notification_type' => 'publish',
                        'recipient_type' => 'audience',
                        'source_type' => 'timetable_version',
                        'source_key' => (string) $version->id,
                    ],
                    [
                        'recipient_user_id' => null,
                        'title' => 'Timetable publish notification audit repaired',
                        'message' => 'Recreated missing publish notification audit row for timetable version #' . $version->version_number . '.',
                        'status' => 'queued',
                        'metadata' => [
                            'reconciliation_repair' => 'publish_notification_created',
                            'repaired_by' => $actor->id,
                            'repaired_at' => now()->toDateTimeString(),
                        ],
                    ]
                );
                if ($notification->wasRecentlyCreated) {
                    $repaired++;
                }
            }
        });

        return ['repaired' => $repaired, 'message' => "{$repaired} missing publish notification audit rows were queued."];
    }

    private function reconcileGeneratedOperationalSync(User $actor): array
    {
        $publishedRuns = $this->officialPublishedGenerationRunIds();
        $scheduled = AcademicPmcTimetableGenerationItem::whereIn('generation_run_id', $publishedRuns)->whereIn('status', ['scheduled', 'published', 'locked'])->count();
        $synced = AcademicPmcTimetableGenerationItem::whereIn('generation_run_id', $publishedRuns)->whereIn('status', ['scheduled', 'published', 'locked'])->whereNotNull('operational_timetable_entry_id')->count();
        $samples = AcademicPmcTimetableGenerationItem::with(['generationRun', 'courseGroup.subject', 'teacher.user', 'classroom', 'slot'])
            ->whereIn('generation_run_id', $publishedRuns)
            ->whereIn('status', ['scheduled', 'published', 'locked'])
            ->whereNull('operational_timetable_entry_id')
            ->limit(5)
            ->get()
            ->map(fn ($item) => [
                'id' => $item->id,
                'label' => trim(($item->courseGroup?->name ?: 'Course group') . ' / ' . ($item->courseGroup?->subject?->code ?: 'subject') . ' / day ' . $item->day_of_week),
                'status' => $item->status,
                'source' => 'generation_run:' . $item->generation_run_id,
            ])
            ->values()
            ->all();

        return $this->storeReconciliationCheck($actor, [
            'check_key' => 'generated_operational_sync',
            'check_group' => 'timetable',
            'title' => 'Published generated classes synced to operational timetable',
            'description' => 'Every published PMC generation item should point to a legacy operational timetable entry.',
            'expected_count' => $scheduled,
            'actual_count' => $synced,
            'mismatch_count' => max(0, $scheduled - $synced),
            'recommended_action' => 'Republish or repair operational timetable sync for affected generation items.',
            'details' => ['sample_mismatches' => $samples],
        ]);
    }

    private function reconcileAllocationEnrollmentLinks(User $actor): array
    {
        $allocations = AcademicPmcStudentCourseAllocation::where('waitlisted', false)
            ->whereIn('basket_status', ['allocated', 'approved', 'locked'])
            ->count();
        $linked = AcademicPmcStudentCourseAllocation::where('waitlisted', false)
            ->whereIn('basket_status', ['allocated', 'approved', 'locked'])
            ->whereNotNull('student_subject_enrollment_id')
            ->count();
        $samples = AcademicPmcStudentCourseAllocation::with(['student.user', 'subject', 'term'])
            ->where('waitlisted', false)
            ->whereIn('basket_status', ['allocated', 'approved', 'locked'])
            ->whereNull('student_subject_enrollment_id')
            ->limit(5)
            ->get()
            ->map(fn ($allocation) => [
                'id' => $allocation->id,
                'label' => trim(($allocation->student?->user?->name ?: 'Student') . ' / ' . ($allocation->subject?->code ?: 'subject') . ' / ' . ($allocation->term?->name ?: 'term')),
                'status' => $allocation->basket_status,
                'source' => 'allocation:' . $allocation->id,
            ])
            ->values()
            ->all();

        return $this->storeReconciliationCheck($actor, [
            'check_key' => 'allocation_enrollment_links',
            'check_group' => 'course_basket',
            'title' => 'Approved allocations linked to student subject enrollments',
            'description' => 'Approved/non-waitlisted course basket allocations should have matching student subject enrollment links.',
            'expected_count' => $allocations,
            'actual_count' => $linked,
            'mismatch_count' => max(0, $allocations - $linked),
            'recommended_action' => 'Refresh course basket enrollment links or review exception-created allocations.',
            'details' => ['sample_mismatches' => $samples],
        ]);
    }

    private function reconcileGroupMembershipAllocations(User $actor): array
    {
        $memberships = AcademicPmcCourseGroupMember::where('status', 'active')->count();
        $linked = AcademicPmcCourseGroupMember::where('status', 'active')
            ->whereNotNull('student_course_allocation_id')
            ->whereHas('courseGroup')
            ->count();
        $samples = AcademicPmcCourseGroupMember::with(['student.user', 'courseGroup.subject'])
            ->where('status', 'active')
            ->where(function ($query) {
                $query->whereNull('student_course_allocation_id')
                    ->orWhereDoesntHave('courseGroup');
            })
            ->limit(5)
            ->get()
            ->map(fn ($member) => [
                'id' => $member->id,
                'label' => trim(($member->student?->user?->name ?: 'Student') . ' / ' . ($member->courseGroup?->name ?: 'missing group') . ' / ' . ($member->courseGroup?->subject?->code ?: 'subject')),
                'status' => $member->status,
                'source' => 'group_member:' . $member->id,
            ])
            ->values()
            ->all();

        return $this->storeReconciliationCheck($actor, [
            'check_key' => 'group_membership_allocation_links',
            'check_group' => 'sections_groups',
            'title' => 'Active group memberships linked to course basket allocations',
            'description' => 'Every active course group membership should point back to an allocation so student-specific timetable visibility remains correct.',
            'expected_count' => $memberships,
            'actual_count' => $linked,
            'mismatch_count' => max(0, $memberships - $linked),
            'recommended_action' => 'Repair unlinked group memberships or re-run group builder from approved allocations.',
            'details' => ['sample_mismatches' => $samples],
        ]);
    }

    private function reconcileDeliveryTrackers(User $actor): array
    {
        $publishedRuns = $this->officialPublishedGenerationRunIds();
        $scheduledGroups = AcademicPmcTimetableGenerationItem::whereIn('generation_run_id', $publishedRuns)->where('status', 'scheduled')->whereNotNull('course_group_id')->distinct('course_group_id')->count('course_group_id');
        $trackedGroups = \App\Models\AcademicPmcGroupDeliveryTracker::whereIn('course_group_id', function ($query) {
            $query->select('course_group_id')
                ->from('academic_pmc_timetable_generation_items')
                ->whereIn('generation_run_id', $this->officialPublishedGenerationRunIds())
                ->where('status', 'scheduled')
                ->whereNotNull('course_group_id');
        })->distinct('course_group_id')->count('course_group_id');
        $trackedGroupIds = \App\Models\AcademicPmcGroupDeliveryTracker::pluck('course_group_id');
        $samples = AcademicPmcCourseGroup::with(['subject', 'program', 'term'])
            ->whereIn('id', function ($query) {
                $query->select('course_group_id')
                    ->from('academic_pmc_timetable_generation_items')
                    ->whereIn('generation_run_id', $this->officialPublishedGenerationRunIds())
                    ->where('status', 'scheduled')
                    ->whereNotNull('course_group_id');
            })
            ->whereNotIn('id', $trackedGroupIds)
            ->limit(5)
            ->get()
            ->map(fn ($group) => [
                'id' => $group->id,
                'label' => trim($group->name . ' / ' . ($group->subject?->code ?: 'subject') . ' / ' . ($group->term?->name ?: 'term')),
                'status' => $group->status,
                'source' => 'course_group:' . $group->id,
            ])
            ->values()
            ->all();

        return $this->storeReconciliationCheck($actor, [
            'check_key' => 'scheduled_groups_delivery_trackers',
            'check_group' => 'course_delivery',
            'title' => 'Scheduled course groups have delivery trackers',
            'description' => 'Course delivery monitoring should cover every scheduled course group.',
            'expected_count' => $scheduledGroups,
            'actual_count' => $trackedGroups,
            'mismatch_count' => max(0, $scheduledGroups - $trackedGroups),
            'recommended_action' => 'Refresh PMC course delivery checkpoints and group delivery trackers.',
            'details' => ['sample_mismatches' => $samples],
        ]);
    }

    private function reconcilePublishedNotifications(User $actor): array
    {
        $publishedVersions = TimetableVersion::where('status', 'published')->count();
        $versionNotifications = AcademicPmcTimetableNotification::where('source_type', 'timetable_version')
            ->where('notification_type', 'publish')
            ->distinct('source_key')
            ->count('source_key');
        $notifiedVersionIds = AcademicPmcTimetableNotification::where('source_type', 'timetable_version')
            ->where('notification_type', 'publish')
            ->pluck('source_key')
            ->map(fn ($id) => (int) $id)
            ->all();
        $samples = TimetableVersion::where('status', 'published')
            ->whereNotIn('id', $notifiedVersionIds)
            ->limit(5)
            ->get()
            ->map(fn ($version) => [
                'id' => $version->id,
                'label' => 'Timetable version #' . $version->version_number . ' / ' . ($version->name ?: 'Published timetable'),
                'status' => $version->status,
                'source' => 'timetable_version:' . $version->id,
            ])
            ->values()
            ->all();

        return $this->storeReconciliationCheck($actor, [
            'check_key' => 'published_version_notifications',
            'check_group' => 'notifications',
            'title' => 'Published timetable versions have notification records',
            'description' => 'Every published timetable version should have at least one publish notification audit record.',
            'expected_count' => $publishedVersions,
            'actual_count' => $versionNotifications,
            'mismatch_count' => max(0, $publishedVersions - $versionNotifications),
            'recommended_action' => 'Requeue publish notifications or review missing notification audit rows.',
            'details' => ['sample_mismatches' => $samples],
        ]);
    }

    private function storeReconciliationCheck(User $actor, array $data): array
    {
        $mismatch = (int) ($data['mismatch_count'] ?? 0);
        $status = $mismatch === 0 ? 'ok' : ($mismatch >= 5 ? 'block' : 'warn');
        $severity = $mismatch === 0 ? 'low' : ($mismatch >= 5 ? 'critical' : 'medium');

        $record = AcademicPmcDataReconciliationCheck::updateOrCreate(
            [
                'check_key' => $data['check_key'],
                'source_type' => $data['source_type'] ?? 'global',
                'source_key' => $data['source_key'] ?? 'all',
            ],
            [
                'check_group' => $data['check_group'],
                'status' => $status,
                'severity' => $severity,
                'title' => $data['title'],
                'description' => $data['description'] ?? null,
                'expected_count' => $data['expected_count'] ?? 0,
                'actual_count' => $data['actual_count'] ?? 0,
                'mismatch_count' => $mismatch,
                'recommended_action' => $data['recommended_action'] ?? null,
                'details' => ['version' => 'PMC OS v0.092'] + ($data['details'] ?? []),
                'checked_by' => $actor->id,
                'checked_at' => now(),
            ]
        );

        return $record->only(['check_key', 'status', 'severity', 'mismatch_count']);
    }

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

    public function facultyAvailabilitySurface(User $user, array $filters = []): array
    {
        $this->policy->authorizeRead($user);
        $termIds = $this->policy->scopedTermIds($user);

        return [
            'title' => 'PMC Faculty Availability Requests',
            'scopeLabel' => $this->policy->scopeLabel($user),
            'requests' => AcademicPmcFacultyAvailabilityRequest::with(['teacher.user', 'term', 'submitter', 'decider'])
                ->when($filters['status'] ?? null, fn ($q, $status) => $q->where('status', $status))
                ->when($termIds !== null, fn ($q) => is_array($termIds) ? $q->whereIn('term_id', $termIds) : $q)
                ->latest()
                ->paginate(20)
                ->withQueryString(),
            'preferences' => AcademicPmcFacultyPreference::with('teacher.user')
                ->when($termIds !== null, fn ($q) => is_array($termIds) ? $q->whereIn('term_id', $termIds) : $q)
                ->latest()
                ->paginate(15, ['*'], 'preferences_page'),
            'selectorOptions' => $this->selectorOptions(),
        ];
    }

    public function facultyOwnAvailabilitySurface(User $user): array
    {
        $teacher = $user->teacher;
        abort_unless($teacher, 403);

        return [
            'title' => 'My PMC Availability',
            'scopeLabel' => $teacher->user?->name ?? 'Faculty',
            'teacher' => $teacher,
            'requests' => AcademicPmcFacultyAvailabilityRequest::with('term')->where('teacher_id', $teacher->id)->latest()->paginate(15),
            'preferences' => AcademicPmcFacultyPreference::where('teacher_id', $teacher->id)->latest()->get(),
            'selectorOptions' => $this->selectorOptions(),
        ];
    }

    public function selectorOptionsForFilters(): array
    {
        return $this->selectorOptions();
    }

    private function selectorOptions(): array
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

    public function submitFacultyAvailability(User $actor, array $data): AcademicPmcFacultyAvailabilityRequest
    {
        $teacher = $actor->teacher;
        if ($teacher) {
            abort_if(! empty($data['teacher_id']) && (int) $data['teacher_id'] !== (int) $teacher->id, 403, 'Teacher users cannot submit availability for another teacher.');
        } elseif (! $this->policy->canWrite($actor)) {
            abort(403);
        }

        $requestedTeacherId = (int) ($data['teacher_id'] ?? 0);
        if (! $teacher) {
            abort_unless($requestedTeacherId > 0, 422, 'teacher_id is required.');
            $teacher = Teacher::findOrFail($requestedTeacherId);
        }

        if (! $actor->teacher) {
            $termIds = $this->policy->scopedTermIds($actor);
            $requestedTermId = (int) ($data['term_id'] ?? 0);
            if (is_array($termIds) && $requestedTermId > 0 && ! in_array($requestedTermId, $termIds, true)) {
                abort(403, 'The selected term is outside your scope.');
            }
        }

        $request = AcademicPmcFacultyAvailabilityRequest::create([
            'teacher_id' => $teacher->id,
            'term_id' => $data['term_id'] ?? null,
            'request_type' => $data['request_type'] ?? 'availability_update',
            'status' => 'submitted',
            'available_days' => $this->csvInts($data['available_days'] ?? []),
            'preferred_slots' => $this->csvInts($data['preferred_slots'] ?? []),
            'unavailable_slots' => $this->slotPairs($data['unavailable_slots'] ?? []),
            'max_classes_per_day' => $data['max_classes_per_day'] ?? null,
            'max_consecutive_classes' => $data['max_consecutive_classes'] ?? null,
            'max_weekly_load' => $data['max_weekly_load'] ?? null,
            'reason' => $data['reason'] ?? null,
            'submitted_by' => $actor->id,
            'submitted_at' => now(),
            'metadata' => ['version' => 'PMC OS v0.046'],
        ]);

        $this->audit($actor, 'academic_pmc_v046_faculty_availability_submitted', 'Faculty availability request submitted', $request);
        return $request;
    }

    public function decideFacultyAvailability(User $actor, AcademicPmcFacultyAvailabilityRequest $request, string $status, ?string $note): AcademicPmcFacultyAvailabilityRequest
    {
        $this->policy->authorizeWrite($actor);
        if (in_array($status, ['rejected', 'returned'], true) && ! $note) {
            abort(422, 'Decision note is required.');
        }

        $request->update([
            'status' => $status,
            'decided_by' => $actor->id,
            'decided_at' => now(),
            'decision_note' => $note,
        ]);

        if ($status === 'approved') {
            AcademicPmcFacultyPreference::updateOrCreate(
                ['teacher_id' => $request->teacher_id, 'term_id' => $request->term_id],
                [
                    'faculty_type' => $request->teacher?->employment_type === 'visiting' ? 'adjunct' : 'regular',
                    'available_days' => $request->available_days,
                    'preferred_slots' => $request->preferred_slots,
                    'unavailable_slots' => $request->unavailable_slots,
                    'max_classes_per_day' => $request->max_classes_per_day ?: 4,
                    'max_consecutive_classes' => $request->max_consecutive_classes ?: 3,
                    'max_weekly_load' => $request->max_weekly_load ?: 18,
                    'metadata' => ['source_request_id' => $request->id, 'approved_by' => $actor->id, 'version' => 'PMC OS v0.046'],
                ]
            );
        }

        $this->audit($actor, 'academic_pmc_v046_faculty_availability_decided', 'Faculty availability request ' . $status, $request);
        return $request->fresh();
    }

    public function refreshFacultyLoadReviews(User $actor, array $data = []): array
    {
        $this->policy->authorizeWrite($actor);
        $run = ! empty($data['generation_run_id'])
            ? AcademicPmcTimetableGenerationRun::findOrFail($data['generation_run_id'])
            : AcademicPmcTimetableGenerationRun::latest()->first();
        abort_unless($run, 422, 'No timetable generation run is available for load review.');

        $teachers = AcademicPmcGroupFacultyAssignment::query()
            ->whereHas('courseGroup', fn ($q) => $q->when($run->term_id, fn ($termQuery) => $termQuery->where('term_id', $run->term_id)))
            ->pluck('teacher_id')
            ->merge(AcademicPmcTimetableGenerationItem::where('generation_run_id', $run->id)->whereNotNull('teacher_id')->pluck('teacher_id'))
            ->unique()
            ->values();

        $created = 0;
        foreach ($teachers as $teacherId) {
            $assignments = AcademicPmcGroupFacultyAssignment::where('teacher_id', $teacherId)
                ->whereHas('courseGroup', fn ($q) => $q->when($run->term_id, fn ($termQuery) => $termQuery->where('term_id', $run->term_id)))
                ->get();
            $items = AcademicPmcTimetableGenerationItem::where('generation_run_id', $run->id)->where('teacher_id', $teacherId)->where('status', 'scheduled')->get();
            $preference = AcademicPmcFacultyPreference::where('teacher_id', $teacherId)->where(fn ($q) => $q->where('term_id', $run->term_id)->orWhereNull('term_id'))->first();
            $daily = $items->groupBy('day_of_week')->map->count()->all();
            $assignedHours = (int) $assignments->sum('weekly_hours');
            $weeklyLimit = (int) ($preference?->max_weekly_load ?: 18);
            $dailyLimit = (int) ($preference?->max_classes_per_day ?: 4);
            $maxDay = (int) (empty($daily) ? 0 : max($daily));
            $maxConsecutive = $this->maxConsecutiveForItems($items);
            $reasons = [];
            $band = 'normal';

            if ($assignedHours > $weeklyLimit || $maxDay > $dailyLimit) {
                $band = 'overload';
                $reasons[] = 'load_exceeds_configured_limit';
            }
            if ($assignedHours > ($weeklyLimit + 4) || $maxDay > ($dailyLimit + 2)) {
                $band = 'critical';
                $reasons[] = 'critical_overload';
            }
            if ($assignedHours > 0 && $assignedHours < 8) {
                $band = 'underload';
                $reasons[] = 'underload';
            }
            if ($preference && $preference->faculty_type === 'adjunct' && $maxDay > $dailyLimit) {
                $band = 'critical';
                $reasons[] = 'adjunct_daily_limit_breach';
            }

            AcademicPmcFacultyLoadReview::updateOrCreate(
                ['teacher_id' => $teacherId, 'term_id' => $run->term_id, 'generation_run_id' => $run->id],
                [
                    'assigned_weekly_hours' => $assignedHours,
                    'scheduled_classes' => $items->count(),
                    'max_classes_in_day' => $maxDay,
                    'max_consecutive_classes' => $maxConsecutive,
                    'configured_weekly_limit' => $weeklyLimit,
                    'configured_daily_limit' => $dailyLimit,
                    'load_band' => $band,
                    'status' => in_array($band, ['overload', 'critical'], true) ? 'approval_required' : 'review_required',
                    'risk_reasons' => $reasons,
                    'daily_distribution' => $daily,
                    'metadata' => ['version' => 'PMC OS v0.047', 'assignment_count' => $assignments->count()],
                ]
            );
            $created++;
        }

        $this->audit($actor, 'academic_pmc_v047_faculty_load_reviews_refreshed', 'Faculty load reviews refreshed', $run, ['reviews' => $created]);
        return ['run' => $run, 'reviews' => $created];
    }

    public function decideFacultyLoadReview(User $actor, AcademicPmcFacultyLoadReview $review, string $status, ?string $note): AcademicPmcFacultyLoadReview
    {
        $this->policy->authorizeWrite($actor);
        if (in_array($status, ['approved_overload', 'rejected', 'revision_required'], true) && ! $note) {
            abort(422, 'Decision note is required.');
        }

        $review->update([
            'status' => $status,
            'reviewed_by' => $actor->id,
            'reviewed_at' => now(),
            'decision_note' => $note,
        ]);

        $this->audit($actor, 'academic_pmc_v047_faculty_load_review_decided', 'Faculty load review ' . $status, $review);
        return $review->fresh();
    }

    public function refreshRoomReadinessReviews(User $actor, array $data = []): array
    {
        $this->policy->authorizeWrite($actor);
        $run = ! empty($data['generation_run_id'])
            ? AcademicPmcTimetableGenerationRun::findOrFail($data['generation_run_id'])
            : AcademicPmcTimetableGenerationRun::latest()->first();
        abort_unless($run, 422, 'No timetable generation run is available for room readiness review.');

        $itemsByRoom = AcademicPmcTimetableGenerationItem::with(['courseGroup', 'classroom'])
            ->where('generation_run_id', $run->id)
            ->where('status', 'scheduled')
            ->whereNotNull('classroom_id')
            ->get()
            ->groupBy('classroom_id');

        $created = 0;
        foreach ($itemsByRoom as $roomId => $items) {
            $room = $items->first()?->classroom ?: Classroom::find($roomId);
            if (! $room) {
                continue;
            }

            $maxStrength = (int) $items->map(fn ($item) => (int) ($item->courseGroup?->current_strength ?? 0))->max();
            $roomCapacity = (int) ($room->capacity ?? 0);
            $labRequired = $items->contains(fn ($item) => str_contains((string) ($item->courseGroup?->group_type ?? ''), 'lab'));
            $labReady = ! $labRequired || (bool) ($room->has_lab ?? false) || $room->type === 'lab';
            $capacityOk = $roomCapacity >= $maxStrength;
            $usage = $items->groupBy('day_of_week')->map(fn ($dayItems) => $dayItems->count())->all();
            $reasons = [];

            if (! $capacityOk) {
                $reasons[] = 'capacity_below_largest_group';
            }
            if (! $labReady) {
                $reasons[] = 'lab_group_in_non_lab_room';
            }
            if ($items->count() >= 6) {
                $reasons[] = 'high_room_utilization';
            }

            $band = (! $capacityOk || ! $labReady) ? 'blocked' : ($reasons ? 'warning' : 'ready');
            AcademicPmcRoomReadinessReview::updateOrCreate(
                ['classroom_id' => $room->id, 'generation_run_id' => $run->id],
                [
                    'scheduled_classes' => $items->count(),
                    'max_group_strength' => $maxStrength,
                    'room_capacity' => $roomCapacity,
                    'lab_required' => $labRequired,
                    'lab_ready' => $labReady,
                    'capacity_ok' => $capacityOk,
                    'readiness_band' => $band,
                    'status' => $band === 'ready' ? 'review_required' : 'review_required',
                    'risk_reasons' => $reasons,
                    'usage_distribution' => $usage,
                    'metadata' => ['version' => 'PMC OS v0.048', 'run_title' => $run->title],
                ]
            );
            $created++;
        }

        $blocked = AcademicPmcRoomReadinessReview::where('generation_run_id', $run->id)
            ->where('readiness_band', 'blocked')
            ->whereNotIn('status', ['approved', 'approved_with_exception'])
            ->count();
        AcademicPmcTimetablePublishCheck::updateOrCreate(
            ['generation_run_id' => $run->id, 'check_type' => 'room_readiness'],
            [
                'status' => $blocked === 0 ? 'pass' : 'block',
                'severity' => $blocked === 0 ? 'info' : 'high',
                'title' => 'Room and lab readiness',
                'description' => $blocked === 0 ? 'Rooms/labs are ready or reviewed for this generation run.' : "{$blocked} room/lab readiness blocker(s) require PMC/Dean decision.",
                'required_role' => 'pmc_head',
                'metadata' => ['version' => 'PMC OS v0.048'],
            ]
        );

        $this->audit($actor, 'academic_pmc_v048_room_readiness_refreshed', 'Room readiness reviews refreshed', $run, ['reviews' => $created, 'blocked' => $blocked]);
        return ['reviews' => $created, 'blocked' => $blocked];
    }

    public function decideRoomReadinessReview(User $actor, AcademicPmcRoomReadinessReview $review, string $status, ?string $note): AcademicPmcRoomReadinessReview
    {
        $this->policy->authorizeWrite($actor);
        if (in_array($status, ['approved_with_exception', 'rejected', 'revision_required'], true) && ! $note) {
            abort(422, 'Decision note is required.');
        }

        $review->update([
            'status' => $status,
            'reviewed_by' => $actor->id,
            'reviewed_at' => now(),
            'decision_note' => $note,
        ]);

        $blocked = AcademicPmcRoomReadinessReview::where('generation_run_id', $review->generation_run_id)
            ->where('readiness_band', 'blocked')
            ->whereNotIn('status', ['approved', 'approved_with_exception'])
            ->count();
        AcademicPmcTimetablePublishCheck::updateOrCreate(
            ['generation_run_id' => $review->generation_run_id, 'check_type' => 'room_readiness'],
            [
                'status' => $blocked === 0 ? 'pass' : 'block',
                'severity' => $blocked === 0 ? 'info' : 'high',
                'title' => 'Room and lab readiness',
                'description' => $blocked === 0 ? 'Room/lab readiness decisions are clear for publish.' : "{$blocked} room/lab readiness blocker(s) remain open.",
                'required_role' => 'pmc_head',
                'metadata' => ['version' => 'PMC OS v0.048'],
            ]
        );

        $this->audit($actor, 'academic_pmc_v048_room_readiness_decided', 'Room readiness review ' . $status, $review);
        return $review->fresh();
    }

    public function requestCourseAllocationException(User $actor, array $data): AcademicPmcCourseAllocationException
    {
        $this->policy->authorizeWriteScope($actor, ['subject_id' => $data['subject_id'] ?? null, 'term_id' => $data['term_id'] ?? null]);
        $allocation = AcademicPmcStudentCourseAllocation::where('student_id', $data['student_id'])
            ->where('subject_id', $data['subject_id'])
            ->when($data['term_id'] ?? null, fn ($q, $termId) => $q->where('term_id', $termId))
            ->first();
        $flags = $this->allocationExceptionFlags($data, $allocation);

        $exception = AcademicPmcCourseAllocationException::create([
            'student_course_allocation_id' => $allocation?->id,
            'student_id' => $data['student_id'],
            'subject_id' => $data['subject_id'],
            'term_id' => $data['term_id'] ?? null,
            'exception_type' => $data['exception_type'],
            'status' => 'requested',
            'credit_delta' => (int) ($data['credit_delta'] ?? 0),
            'requires_dean_approval' => in_array('credit_overload', $flags, true) || in_array($data['exception_type'], ['improvement', 'audit'], true),
            'reason' => $data['reason'] ?? null,
            'validation_flags' => $flags,
            'requested_by' => $actor->id,
            'requested_at' => now(),
            'metadata' => ['version' => 'PMC OS v0.050'],
        ]);

        $this->audit($actor, 'academic_pmc_v050_course_allocation_exception_requested', 'Course allocation exception requested', $exception);
        return $exception->fresh();
    }

    public function decideCourseAllocationException(User $actor, AcademicPmcCourseAllocationException $exception, string $status, ?string $note): AcademicPmcCourseAllocationException
    {
        $this->policy->authorizeWriteScope($actor, ['subject_id' => $exception->subject_id, 'term_id' => $exception->term_id]);
        if (in_array($status, ['approved', 'rejected', 'returned'], true) && ! $note) {
            abort(422, 'Decision note is required.');
        }
        if ($exception->requires_dean_approval && $status === 'approved' && ! $actor->hasAnyRole(['admin', 'director', 'academic_department_owner', 'dean_academics'])) {
            abort(403, 'Dean/Academic leadership approval is required for this exception.');
        }

        $exception->update([
            'status' => $status,
            'decided_by' => $actor->id,
            'decided_at' => now(),
            'decision_note' => $note,
        ]);

        if ($status === 'approved') {
            $this->applyCourseAllocationException($actor, $exception->fresh());
        }

        $this->audit($actor, 'academic_pmc_v050_course_allocation_exception_decided', 'Course allocation exception ' . $status, $exception);
        return $exception->fresh();
    }

    public function requestCourseGroupAdjustment(User $actor, array $data): AcademicPmcCourseGroupAdjustment
    {
        $group = AcademicPmcCourseGroup::findOrFail($data['course_group_id']);
        $target = ! empty($data['target_course_group_id']) ? AcademicPmcCourseGroup::findOrFail($data['target_course_group_id']) : null;
        $this->policy->authorizeWriteScope($actor, [
            'program_id' => $group->program_id,
            'batch_id' => $group->batch_id,
            'term_id' => $group->term_id,
            'subject_id' => $group->subject_id,
        ]);

        $delta = max(0, (int) ($data['strength_delta'] ?? 0));
        $type = $data['adjustment_type'];
        [$toStrength, $targetToStrength] = match ($type) {
            'split' => [max(0, $group->current_strength - $delta), $target ? $target->current_strength + $delta : $delta],
            'merge' => [0, $target ? $target->current_strength + $group->current_strength : $group->current_strength],
            'rebalance' => [max(0, $group->current_strength - $delta), $target ? $target->current_strength + $delta : 0],
            'move_student' => [max(0, $group->current_strength - 1), $target ? $target->current_strength + 1 : 1],
            'lock', 'unlock' => [$group->current_strength, $target?->current_strength ?? 0],
            default => [$group->current_strength, $target?->current_strength ?? 0],
        };

        $impact = [
            'from_group' => $group->name,
            'target_group' => $target?->name,
            'student_id' => $data['student_id'] ?? null,
            'strength_delta' => $delta,
            'capacity_warning' => $target ? $targetToStrength > $target->max_capacity : false,
        ];

        $adjustment = AcademicPmcCourseGroupAdjustment::create([
            'course_group_id' => $group->id,
            'target_course_group_id' => $target?->id,
            'student_id' => $data['student_id'] ?? null,
            'adjustment_type' => $type,
            'status' => 'requested',
            'from_strength' => $group->current_strength,
            'to_strength' => $toStrength,
            'target_from_strength' => $target?->current_strength ?? 0,
            'target_to_strength' => $targetToStrength,
            'requires_dean_approval' => in_array($type, ['merge', 'unlock'], true) || ($impact['capacity_warning'] ?? false),
            'reason' => $data['reason'] ?? null,
            'impact_summary' => $impact,
            'requested_by' => $actor->id,
            'requested_at' => now(),
            'metadata' => ['version' => 'PMC OS v0.051'],
        ]);

        $this->audit($actor, 'academic_pmc_v051_course_group_adjustment_requested', 'Course group adjustment requested', $adjustment);
        return $adjustment->fresh();
    }

    public function requestFacultyAssignmentAcknowledgement(User $actor, AcademicPmcGroupFacultyAssignment $assignment): AcademicPmcFacultyAssignmentAcknowledgement
    {
        $group = $assignment->courseGroup;
        $this->policy->authorizeWriteScope($actor, [
            'program_id' => $group?->program_id,
            'batch_id' => $group?->batch_id,
            'term_id' => $group?->term_id,
            'subject_id' => $group?->subject_id,
        ]);

        $ack = AcademicPmcFacultyAssignmentAcknowledgement::updateOrCreate(
            ['group_faculty_assignment_id' => $assignment->id, 'teacher_id' => $assignment->teacher_id],
            [
                'status' => 'pending',
                'requested_by' => $actor->id,
                'requested_at' => now(),
                'metadata' => ['version' => 'PMC OS v0.052'],
            ]
        );

        AcademicPmcTimetablePublishCheck::updateOrCreate(
            ['generation_run_id' => null, 'check_type' => 'faculty_acknowledgements'],
            [
                'status' => 'warn',
                'severity' => 'medium',
                'title' => 'Faculty assignment acknowledgements',
                'description' => 'One or more faculty assignment acknowledgements are pending.',
                'required_role' => 'pmc_head',
                'metadata' => ['version' => 'PMC OS v0.052'],
            ]
        );

        $this->audit($actor, 'academic_pmc_v052_faculty_assignment_ack_requested', 'Faculty assignment acknowledgement requested', $ack);
        return $ack->fresh();
    }

    public function respondFacultyAssignmentAcknowledgement(User $actor, AcademicPmcFacultyAssignmentAcknowledgement $ack, string $responseType, ?string $note, array $constraints = []): AcademicPmcFacultyAssignmentAcknowledgement
    {
        $teacher = $actor->teacher;
        abort_unless($teacher && $teacher->id === $ack->teacher_id, 403);
        if (in_array($responseType, ['accept_with_constraints', 'raise_concern', 'decline'], true) && ! $note) {
            abort(422, 'Faculty note is required.');
        }

        $status = $responseType === 'accept' ? 'accepted' : 'concern_raised';
        $ack->update([
            'status' => $status,
            'response_type' => $responseType,
            'faculty_note' => $note,
            'constraints_raised' => $constraints,
            'responded_by' => $actor->id,
            'responded_at' => now(),
        ]);

        $this->refreshFacultyAcknowledgementPublishCheck();
        $this->audit($actor, 'academic_pmc_v052_faculty_assignment_ack_responded', 'Faculty assignment acknowledgement ' . $responseType, $ack);
        return $ack->fresh();
    }

    public function reviewFacultyAssignmentAcknowledgement(User $actor, AcademicPmcFacultyAssignmentAcknowledgement $ack, string $status, ?string $note): AcademicPmcFacultyAssignmentAcknowledgement
    {
        $this->policy->authorizeWrite($actor);
        if (in_array($status, ['concern_accepted', 'revision_required', 'reassigned'], true) && ! $note) {
            abort(422, 'Review note is required.');
        }

        $ack->update([
            'status' => $status,
            'reviewed_by' => $actor->id,
            'reviewed_at' => now(),
            'review_note' => $note,
        ]);

        if ($status === 'accepted') {
            $ack->assignment?->update(['approval_status' => 'faculty_accepted']);
        }
        if (in_array($status, ['revision_required', 'reassigned'], true)) {
            $ack->assignment?->update(['approval_status' => $status]);
        }

        $this->refreshFacultyAcknowledgementPublishCheck();
        $this->audit($actor, 'academic_pmc_v052_faculty_assignment_ack_reviewed', 'Faculty assignment acknowledgement reviewed', $ack);
        return $ack->fresh();
    }

    public function decideCourseGroupAdjustment(User $actor, AcademicPmcCourseGroupAdjustment $adjustment, string $status, ?string $note): AcademicPmcCourseGroupAdjustment
    {
        $group = $adjustment->courseGroup;
        $this->policy->authorizeWriteScope($actor, [
            'program_id' => $group?->program_id,
            'batch_id' => $group?->batch_id,
            'term_id' => $group?->term_id,
            'subject_id' => $group?->subject_id,
        ]);
        if (in_array($status, ['approved', 'rejected', 'returned'], true) && ! $note) {
            abort(422, 'Decision note is required.');
        }
        if ($adjustment->requires_dean_approval && $status === 'approved' && ! $actor->hasAnyRole(['admin', 'director', 'academic_department_owner', 'dean_academics'])) {
            abort(403, 'Dean/Academic leadership approval is required for this group adjustment.');
        }

        $adjustment->update([
            'status' => $status,
            'decided_by' => $actor->id,
            'decided_at' => now(),
            'decision_note' => $note,
        ]);

        if ($status === 'approved') {
            $this->applyCourseGroupAdjustment($actor, $adjustment->fresh());
        }

        $this->audit($actor, 'academic_pmc_v051_course_group_adjustment_decided', 'Course group adjustment ' . $status, $adjustment);
        return $adjustment->fresh();
    }

    public function bulkAllocateCore(User $actor, array $data): AcademicPmcCourseAllocationBatch
    {
        $students = Student::where('program_id', $data['program_id'])->when($data['batch_id'] ?? null, fn ($q) => $q->where('batch_id', $data['batch_id']))->where('status', 'active')->get();
        $batch = AcademicPmcCourseAllocationBatch::create([
            'title' => $data['title'],
            'program_id' => $data['program_id'],
            'batch_id' => $data['batch_id'] ?? null,
            'term_id' => $data['term_id'] ?? null,
            'owner_user_id' => $actor->id,
            'status' => 'allocated',
            'student_count' => $students->count(),
            'core_allocations' => 0,
            'rules' => ['max_credits' => $data['max_credits'] ?? 30],
        ]);

        $created = 0;
        foreach ($students as $student) {
            foreach (($data['subject_ids'] ?? []) as $subjectId) {
                $enrollment = StudentSubjectEnrollment::firstOrCreate(
                    ['student_id' => $student->id, 'subject_id' => $subjectId, 'term_id' => $data['term_id'] ?? null],
                    ['enrollment_type' => 'compulsory', 'status' => 'active']
                );
                AcademicPmcStudentCourseAllocation::firstOrCreate(
                    ['student_id' => $student->id, 'subject_id' => $subjectId, 'term_id' => $data['term_id'] ?? null],
                    ['allocation_batch_id' => $batch->id, 'student_subject_enrollment_id' => $enrollment->id, 'allocation_type' => 'core', 'allocation_source' => 'bulk_core', 'approval_status' => 'allocated', 'basket_status' => 'allocated']
                );
                $created++;
            }
        }

        $batch->update(['core_allocations' => $created]);
        $this->audit($actor, 'academic_pmc_v041_core_allocated', $batch->title, $batch, ['created' => $created]);
        return $batch->fresh();
    }

    public function allocateElectives(User $actor, array $data): array
    {
        $capacity = max(1, (int) ($data['capacity_per_subject'] ?? 60));
        $maxElectivesPerStudent = max(1, (int) ($data['max_electives_per_student'] ?? 2));
        $termId = $data['term_id'] ?? null;
        $subjectIds = $data['subject_ids'] ?? [];

        $choices = AcademicPmcElectiveChoice::with('student')
            ->when($data['program_id'] ?? null, fn ($q, $id) => $q->where('program_id', $id))
            ->when($data['batch_id'] ?? null, fn ($q, $id) => $q->where('batch_id', $id))
            ->when($termId, fn ($q, $id) => $q->where('term_id', $id))
            ->when($subjectIds, fn ($q) => $q->whereIn('subject_id', $subjectIds))
            ->whereIn('status', ['submitted', 'waitlisted'])
            ->orderBy('preference_rank')
            ->orderByDesc('priority_score')
            ->get();

        $batch = AcademicPmcCourseAllocationBatch::create([
            'title' => $data['title'] ?? 'PMC Elective Allocation',
            'program_id' => $data['program_id'] ?? null,
            'batch_id' => $data['batch_id'] ?? null,
            'term_id' => $termId,
            'owner_user_id' => $actor->id,
            'status' => 'allocated',
            'student_count' => $choices->pluck('student_id')->unique()->count(),
            'rules' => ['capacity_per_subject' => $capacity, 'strategy' => 'preference_priority_capacity'],
        ]);

        $allocated = 0;
        $waitlisted = 0;
        $choicesByStudent = $choices->groupBy('student_id');
        $allocatedPerStudent = AcademicPmcStudentCourseAllocation::query()
            ->where('allocation_type', 'elective')
            ->where('basket_status', 'allocated')
            ->where('waitlisted', false)
            ->when($termId, fn ($q) => $q->where('term_id', $termId))
            ->get()
            ->groupBy('student_id')
            ->map(fn ($rows) => $rows->count())
            ->toArray();

        $allocatedPerSubject = AcademicPmcStudentCourseAllocation::query()
            ->where('allocation_type', 'elective')
            ->where('waitlisted', false)
            ->when($termId, fn ($q) => $q->where('term_id', $termId))
            ->get()
            ->groupBy('subject_id')
            ->map(fn ($rows) => $rows->count())
            ->toArray();

        foreach ($choicesByStudent as $studentId => $studentChoices) {
            $studentAllocated = (int) ($allocatedPerStudent[$studentId] ?? 0);
            $orderedChoices = $studentChoices->sortBy([
                ['preference_rank', 'asc'],
                ['priority_score', 'desc'],
            ]);

            foreach ($orderedChoices as $choice) {
                $subjectId = $choice->subject_id;
                $filled = (int) ($allocatedPerSubject[$subjectId] ?? 0);
                $canAllocateMoreElectives = $studentAllocated < $maxElectivesPerStudent;
                $hasRoom = $filled < $capacity;

                if (! $canAllocateMoreElectives) {
                    $choice->update(['status' => 'waitlisted', 'decision_reason' => 'Student reached max elective allocation limit.']);
                    AcademicPmcStudentCourseAllocation::updateOrCreate(
                        ['student_id' => $studentId, 'subject_id' => $subjectId, 'term_id' => $termId],
                        [
                            'allocation_batch_id' => $batch->id,
                            'student_subject_enrollment_id' => null,
                            'allocation_type' => 'elective',
                            'allocation_source' => 'choice_window',
                            'approval_status' => 'waitlisted',
                            'basket_status' => 'waitlisted',
                            'priority_rank' => $choice->preference_rank,
                            'waitlisted' => true,
                            'validation_flags' => ['max_electives_reached'],
                            'metadata' => ['choice_id' => $choice->id, 'priority_score' => $choice->priority_score],
                        ]
                    );
                    $waitlisted++;
                    continue;
                }

                $isAllocated = $hasRoom;
                $enrollment = null;

                if ($isAllocated) {
                    $enrollment = StudentSubjectEnrollment::firstOrCreate(
                        ['student_id' => $studentId, 'subject_id' => $subjectId, 'term_id' => $termId],
                        ['enrollment_type' => 'elective', 'status' => 'active']
                    );
                    $allocatedPerStudent[$studentId] = ++$studentAllocated;
                    $allocatedPerSubject[$subjectId] = $filled + 1;
                    $allocated++;
                } else {
                    $waitlisted++;
                }

                AcademicPmcStudentCourseAllocation::updateOrCreate(
                    ['student_id' => $studentId, 'subject_id' => $subjectId, 'term_id' => $termId],
                    [
                        'allocation_batch_id' => $batch->id,
                        'student_subject_enrollment_id' => $enrollment?->id,
                        'allocation_type' => 'elective',
                        'allocation_source' => 'choice_window',
                        'approval_status' => $isAllocated ? 'allocated' : 'waitlisted',
                        'basket_status' => $isAllocated ? 'allocated' : 'waitlisted',
                        'priority_rank' => $choice->preference_rank,
                        'waitlisted' => ! $isAllocated,
                        'validation_flags' => $isAllocated ? [] : ['capacity_full'],
                        'metadata' => ['choice_id' => $choice->id, 'priority_score' => $choice->priority_score],
                    ]
                );

                $choice->update([
                    'status' => $isAllocated ? 'allocated' : 'waitlisted',
                    'decision_reason' => $isAllocated ? 'Allocated by preference and capacity.' : 'Capacity full; placed on waitlist.',
                ]);
            }
        }

        $batch->update(['elective_allocations' => $allocated, 'conflict_count' => $waitlisted]);
        $this->audit($actor, 'academic_pmc_v042_electives_allocated', $batch->title, $batch, ['allocated' => $allocated, 'waitlisted' => $waitlisted]);

        return ['batch' => $batch->fresh(), 'allocated' => $allocated, 'waitlisted' => $waitlisted];
    }

    public function autoBuildGroups(User $actor, array $data): AcademicPmcGroupBuildRun
    {
        $max = max(1, (int) ($data['max_capacity'] ?? 60));
        $min = max(1, (int) ($data['min_capacity'] ?? 1));
        $groupType = $data['group_type'] ?? 'core_section';
        $warnings = [];

        $allocations = AcademicPmcStudentCourseAllocation::with('student')
            ->where('subject_id', $data['subject_id'])
            ->when($data['term_id'] ?? null, fn ($q, $id) => $q->where('term_id', $id))
            ->where('waitlisted', false)
            ->whereIn('basket_status', ['allocated', 'approved', 'locked'])
            ->orderBy('student_id')
            ->get();

        $run = AcademicPmcGroupBuildRun::create([
            'title' => $data['title'] ?? 'PMC Auto Group Build',
            'program_id' => $data['program_id'] ?? null,
            'batch_id' => $data['batch_id'] ?? null,
            'term_id' => $data['term_id'] ?? null,
            'subject_id' => $data['subject_id'],
            'group_type' => $groupType,
            'strategy' => $data['strategy'] ?? 'balanced_capacity',
            'min_capacity' => $min,
            'max_capacity' => $max,
            'students_considered' => $allocations->count(),
            'created_by' => $actor->id,
        ]);

        $created = 0;
        foreach ($allocations->chunk($max) as $index => $chunk) {
            if ($chunk->count() < $min) {
                $warnings[] = "Group " . ($index + 1) . " has {$chunk->count()} students below minimum {$min}.";
            }

            $group = AcademicPmcCourseGroup::create([
                'name' => ($data['group_prefix'] ?? 'PMC Group') . ' ' . ($index + 1),
                'group_type' => $groupType,
                'program_id' => $data['program_id'] ?? null,
                'batch_id' => $data['batch_id'] ?? null,
                'term_id' => $data['term_id'] ?? null,
                'subject_id' => $data['subject_id'],
                'owner_user_id' => $actor->id,
                'min_capacity' => $min,
                'max_capacity' => $max,
                'current_strength' => $chunk->count(),
                'status' => 'draft',
                'constraints' => ['build_run_id' => $run->id],
            ]);

            foreach ($chunk as $allocation) {
                AcademicPmcCourseGroupMember::updateOrCreate(
                    ['course_group_id' => $group->id, 'student_id' => $allocation->student_id],
                    ['student_course_allocation_id' => $allocation->id, 'status' => 'active', 'moved_by' => $actor->id]
                );
            }
            $created++;
        }

        $run->update(['groups_created' => $created, 'warnings_count' => count($warnings), 'warnings' => $warnings]);
        $this->audit($actor, 'academic_pmc_v042_groups_auto_built', $run->title, $run, ['groups_created' => $created]);

        return $run->fresh();
    }

    public function createGroup(User $actor, array $data): AcademicPmcCourseGroup
    {
        $group = AcademicPmcCourseGroup::create($data + ['owner_user_id' => $data['owner_user_id'] ?? $actor->id]);
        $this->audit($actor, 'academic_pmc_v041_group_created', $group->name, $group);
        return $group;
    }

    public function assignFaculty(User $actor, array $data): AcademicPmcGroupFacultyAssignment
    {
        $assignment = AcademicPmcGroupFacultyAssignment::updateOrCreate(
            ['course_group_id' => $data['course_group_id'], 'teacher_id' => $data['teacher_id'], 'assignment_role' => $data['assignment_role']],
            $data + ['assigned_by' => $actor->id]
        );
        $this->audit($actor, 'academic_pmc_v041_group_faculty_assigned', 'Faculty assigned to course group', $assignment);
        return $assignment;
    }

    public function createLockedSlot(User $actor, array $data): AcademicPmcLockedSlot
    {
        $slot = AcademicPmcLockedSlot::create($data + ['created_by' => $actor->id]);
        $this->audit($actor, 'academic_pmc_v041_locked_slot_created', $slot->title, $slot);
        return $slot;
    }

    public function generate(User $actor, array $data): AcademicPmcTimetableGenerationRun
    {
        $run = app(TimetableOptimizationService::class)->solve($actor, [
            'program_id' => $data['program_id'] ?? null,
            'batch_id' => $data['batch_id'] ?? null,
            'term_id' => $data['term_id'] ?? null,
        ], [
            'title' => $data['title'] ?? 'PMC Optimized Timetable',
            'strategy' => $data['strategy'] ?? 'balanced',
        ]);
        $quality = $this->refreshConstraintsAndQuality($run);
        AcademicPmcTimetableSolverAttempt::where('generation_run_id', $run->id)->latest()->first()?->update([
            'status' => $quality->hard_conflicts > 0 ? 'completed_with_conflicts' : ($run->unscheduled_count > 0 ? 'completed_with_unscheduled' : 'completed'),
            'hard_conflicts' => $quality->hard_conflicts,
            'soft_warnings' => $quality->soft_warnings,
            'quality_score' => $quality->overall_score,
        ]);
        $this->audit($actor, 'academic_pmc_v041_timetable_generated', $run->title, $run);
        return $run->fresh();
    }

    public function refreshConstraintsAndQuality(AcademicPmcTimetableGenerationRun $run): AcademicPmcTimetableQualityScore
    {
        AcademicPmcTimetableConstraint::where('generation_run_id', $run->id)->delete();
        $items = AcademicPmcTimetableGenerationItem::where('generation_run_id', $run->id)->get();
        $hard = 0;
        $soft = 0;

        foreach ($this->resourceConflictBuckets($items) as $bucket) {
            foreach ($bucket['duplicates'] as $id => $duplicates) {
                $this->constraint(
                    $run,
                    $bucket['type'],
                    'hard',
                    str($bucket['type'])->headline()->toString(),
                    "{$bucket['label']} {$id} is booked more than once on day {$bucket['day']} in slot {$bucket['slot_id']}.",
                    $bucket['affected_type'],
                    (string) $id,
                    $bucket['fix']
                );
                $hard++;
            }
        }

        foreach ($items->where('status', 'unscheduled') as $item) {
            AcademicPmcTimetableConstraint::create(['generation_run_id' => $run->id, 'constraint_type' => 'unscheduled_class', 'severity' => 'hard', 'title' => 'Unscheduled class', 'description' => $item->explanation, 'affected_type' => 'course_group', 'affected_key' => (string) $item->course_group_id, 'recommended_fix' => 'Assign missing faculty/room or relax constraint.', 'source_route' => route('academics.pmc.timetable-generator.index')]);
            $hard++;
        }

        foreach ($items->where('status', 'scheduled') as $item) {
            $group = $item->courseGroup;
            $room = $item->classroom;
            $slot = $item->slot;
            $preference = $item->teacher_id ? AcademicPmcFacultyPreference::where('teacher_id', $item->teacher_id)->where(fn ($q) => $q->where('term_id', $group?->term_id)->orWhereNull('term_id'))->first() : null;

            if ($slot?->is_break) {
                $this->constraint($run, 'break_slot_used', 'hard', 'Break slot used for teaching', 'A teaching session was placed into a break/lunch slot.', 'timetable_slot', (string) $slot->id, 'Move this session to a non-break teaching slot.');
                $hard++;
            }

            if (($item->duration_slots ?? 1) > 1) {
                $teachingSlots = TimetableSlot::where('is_active', true)->where('is_break', false)->orderBy('sort_order')->get();
                $block = $this->blockSlotIds($teachingSlots, (int) $item->timetable_slot_id, (int) $item->duration_slots);
                if (count($block) < (int) $item->duration_slots) {
                    $this->constraint($run, 'multi_slot_block_incomplete', 'hard', 'Multi-slot session lacks contiguous teaching slots', 'A lab/practical/tutorial block cannot fit into the remaining non-break slots.', 'generation_item', (string) $item->id, 'Move the session earlier or configure a valid block slot.');
                    $hard++;
                }
            }

            if ($group && $room && ($room->capacity ?? 0) > 0 && $room->capacity < $group->current_strength) {
                $this->constraint($run, 'room_capacity_mismatch', 'hard', 'Room capacity mismatch', "Room capacity {$room->capacity} is below group strength {$group->current_strength}.", 'classroom', (string) $room->id, 'Move to a larger room or split the group.');
                $hard++;
            }

            if ($group && str_contains($group->group_type, 'lab') && $room && ! ($room->has_lab || $room->type === 'lab')) {
                $this->constraint($run, 'room_type_mismatch', 'hard', 'Lab group in non-lab room', 'Lab/practical group requires a lab-capable room.', 'course_group', (string) $group->id, 'Assign a lab room or change group type.');
                $hard++;
            }

            if ($preference && $preference->available_days && ! in_array((int) $item->day_of_week, array_map('intval', $preference->available_days), true)) {
                $this->constraint($run, 'faculty_day_unavailable', 'hard', 'Faculty unavailable on scheduled day', 'Faculty is scheduled outside available teaching days.', 'teacher', (string) $item->teacher_id, 'Move class to one of the faculty available days or reassign faculty.');
                $hard++;
            }

            $unavailableSlots = collect($preference?->unavailable_slots ?: []);
            if ($unavailableSlots->contains(fn ($slot) => (int) ($slot['day'] ?? 0) === (int) $item->day_of_week && (int) ($slot['slot_id'] ?? 0) === (int) $item->timetable_slot_id)) {
                $this->constraint($run, 'faculty_slot_unavailable', 'hard', 'Faculty unavailable in slot', 'Faculty has marked this slot unavailable.', 'teacher', (string) $item->teacher_id, 'Move class or override availability with Dean/PMC approval.');
                $hard++;
            }
        }

        foreach ($items->where('status', 'scheduled')->groupBy(fn ($item) => $item->teacher_id . '-' . $item->day_of_week) as $teacherDayItems) {
            $first = $teacherDayItems->first();
            $preference = $first?->teacher_id ? AcademicPmcFacultyPreference::where('teacher_id', $first->teacher_id)->where(fn ($q) => $q->whereNull('term_id')->orWhere('term_id', $first->courseGroup?->term_id))->first() : null;
            $max = $preference?->max_classes_per_day ?: 4;
            if ($teacherDayItems->count() > $max) {
                $this->constraint($run, 'faculty_daily_load', 'soft', 'Faculty daily load warning', "Faculty has {$teacherDayItems->count()} classes in one day; configured max is {$max}.", 'teacher', (string) $first->teacher_id, 'Distribute classes across the week or approve overload.');
                $soft++;
            }

            $maxConsecutive = (int) ($preference?->max_consecutive_classes ?: 3);
            $consecutive = $this->maxConsecutiveForItems($teacherDayItems);
            if ($consecutive > $maxConsecutive) {
                $this->constraint($run, 'faculty_consecutive_load', 'soft', 'Faculty consecutive teaching pressure', "Faculty has {$consecutive} consecutive teaching slot(s); configured max is {$maxConsecutive}.", 'teacher', (string) $first->teacher_id, 'Move one class away from the block or approve the exception.');
                $soft++;
            }
        }

        foreach ($items->where('status', 'scheduled')->groupBy(fn ($item) => $item->course_group_id . '-' . $item->day_of_week) as $groupDayItems) {
            $first = $groupDayItems->first();
            $group = $first?->courseGroup;
            $constraints = $group?->constraints ?: [];
            $maxDaily = (int) ($constraints['max_student_classes_per_day'] ?? $constraints['max_daily_classes'] ?? 4);
            $sessionLoad = (int) $groupDayItems->sum(fn ($item) => max(1, (int) ($item->duration_slots ?? 1)));
            if ($sessionLoad > $maxDaily) {
                $this->constraint($run, 'student_group_daily_load', 'soft', 'Student group daily load pressure', "Group has {$sessionLoad} teaching slot(s) in one day; configured max is {$maxDaily}.", 'course_group', (string) $first->course_group_id, 'Spread the group classes across more days or approve a compact-day exception.');
                $soft++;
            }

            $gapCount = $this->dayGapCount($groupDayItems);
            if ($gapCount > 1) {
                $this->constraint($run, 'student_group_day_gaps', 'soft', 'Student group day has avoidable gaps', "Group has {$gapCount} empty teaching gap(s) between scheduled classes on the same day.", 'course_group', (string) $first->course_group_id, 'Move classes closer together or use a saved compact-student strategy.');
                $soft++;
            }
        }

        foreach (AcademicPmcLockedSlot::where('is_hard_lock', false)->where('status', 'active')->limit(3)->get() as $locked) {
            AcademicPmcTimetableConstraint::create(['generation_run_id' => $run->id, 'constraint_type' => 'soft_locked_slot_preference', 'severity' => 'soft', 'title' => 'Soft locked slot preference', 'description' => $locked->title, 'affected_type' => 'locked_slot', 'affected_key' => (string) $locked->id, 'recommended_fix' => 'Review preference before publishing.', 'source_route' => route('academics.pmc.locked-slots.index')]);
            $soft++;
        }

        $studentCompactness = $this->studentCompactnessScore($items);
        $facultyBalance = max(40, 100 - ($items->where('status', 'scheduled')->groupBy(fn ($item) => $item->teacher_id . '-' . $item->day_of_week)->filter(fn ($group) => $group->count() > 4)->count() * 12));
        $roomUtilization = $this->roomUtilizationScore($items);
        $score = max(0, min(100, (int) round((100 - ($hard * 12) - ($soft * 3) + $studentCompactness + $facultyBalance + $roomUtilization) / 4)));
        $quality = AcademicPmcTimetableQualityScore::updateOrCreate(
            ['generation_run_id' => $run->id],
            ['overall_score' => $score, 'hard_conflicts' => $hard, 'soft_warnings' => $soft, 'student_compactness_score' => $studentCompactness, 'faculty_balance_score' => $facultyBalance, 'room_utilization_score' => $roomUtilization, 'details' => ['formula' => 'avg(conflict-adjusted, student compactness, faculty balance, room utilization)', 'version' => 'PMC OS v0.063', 'faculty_consecutive_checked' => true, 'student_group_day_gaps_checked' => true]]
        );
        $run->update(['hard_conflict_count' => $hard, 'soft_warning_count' => $soft, 'quality_score' => $score]);
        $this->refreshPublishChecks($run, $hard, $soft, $score);
        return $quality;
    }

    public function refreshPublishChecks(AcademicPmcTimetableGenerationRun $run, int $hard, int $soft, int $score): void
    {
        AcademicPmcTimetablePublishCheck::where('generation_run_id', $run->id)->delete();
        $facultySuitabilityDiagnostics = $this->facultySuitabilityDiagnostics($run);
        $facultySuitabilityBlockers = (int) $facultySuitabilityDiagnostics['blocker_total'];
        $checks = [
            ['hard_conflicts', $hard === 0 ? 'pass' : 'block', 'critical', 'Hard conflicts before publish', "{$hard} hard conflicts found.", 'pmc_head'],
            ['soft_warnings', $soft <= 3 ? 'pass' : 'warn', 'medium', 'Soft warning review', "{$soft} soft warnings need PMC review.", 'pmc_manager'],
            ['quality_score', $score >= 70 ? 'pass' : 'block', 'high', 'Timetable quality threshold', "Quality score is {$score}; minimum publish threshold is 70.", 'pmc_head'],
            ['faculty_suitability', $facultySuitabilityBlockers === 0 ? 'pass' : 'block', 'high', 'Faculty suitability before publish', $facultySuitabilityBlockers === 0 ? 'Faculty suitability blockers are clear.' : "{$facultySuitabilityBlockers} faculty suitability blocker(s) remain: expertise, adjunct availability, acknowledgement, overload, approval, or backup-only gaps.", 'pmc_head'],
            ['dean_after_freeze', 'warn', 'medium', 'Dean approval after freeze', 'Any post-freeze revision requires Dean approval.', 'dean_academics'],
        ];

        foreach ($checks as [$type, $status, $severity, $title, $description, $role]) {
            AcademicPmcTimetablePublishCheck::create([
                'generation_run_id' => $run->id,
                'check_type' => $type,
                'status' => $status,
                'severity' => $severity,
                'title' => $title,
                'description' => $description,
                'required_role' => $role,
                'metadata' => $type === 'faculty_suitability' ? ['version' => 'PMC OS v0.089', 'diagnostics' => $facultySuitabilityDiagnostics] : null,
            ]);
        }
    }

    private function syncFacultySuitabilityPublishCheck(?AcademicPmcTimetableGenerationRun $run = null): void
    {
        if (! $run) {
            return;
        }

        $diagnostics = $this->facultySuitabilityDiagnostics($run);
        $blockers = (int) $diagnostics['blocker_total'];

        AcademicPmcTimetablePublishCheck::updateOrCreate(
            ['generation_run_id' => $run->id, 'check_type' => 'faculty_suitability'],
            [
                'status' => $blockers === 0 ? 'pass' : 'block',
                'severity' => 'high',
                'title' => 'Faculty suitability before publish',
                'description' => $blockers === 0
                    ? 'Faculty suitability blockers are clear.'
                    : "{$blockers} faculty suitability blocker(s) remain: expertise, adjunct availability, acknowledgement, overload, approval, or backup-only gaps.",
                'required_role' => 'pmc_head',
                'metadata' => [
                    'version' => 'PMC OS v0.089',
                    'diagnostics' => $diagnostics,
                ],
            ]
        );
    }

    public function applySolverAlternative(User $actor, AcademicPmcTimetableGenerationItem $item, int $alternativeIndex, ?string $decisionNote = null, bool $allowHardConflictOverride = false, ?string $overrideReason = null): AcademicPmcTimetableGenerationItem
    {
        $run = $item->generationRun;
        abort_unless($run, 404, 'Generation run not found for this timetable item.');
        abort_if(in_array($run->status, ['published', 'published_with_dean_override', 'frozen', 'archived'], true), 422, 'Published or frozen timetable runs cannot be changed directly. Create a revision request instead.');

        $metadata = $item->metadata ?: [];
        $alternatives = array_values($metadata['placement_alternatives'] ?? []);
        abort_unless(isset($alternatives[$alternativeIndex]), 422, 'The selected solver alternative is no longer available.');

        $alternative = $alternatives[$alternativeIndex];
        $slotId = (int) ($alternative['slot_id'] ?? 0);
        $roomId = (int) ($alternative['room_id'] ?? 0);
        $day = (int) ($alternative['day'] ?? 0);
        abort_unless($day >= 1 && $day <= 7 && TimetableSlot::whereKey($slotId)->exists() && Classroom::whereKey($roomId)->exists(), 422, 'The selected solver alternative references an invalid day, slot, or room.');

        $beforeQuality = $this->refreshConstraintsAndQuality($run);
        $beforeHardConflicts = (int) $beforeQuality->hard_conflicts;
        $canOverrideHardConflict = $actor->hasAnyRole(['admin', 'director', 'academic_department_owner', 'dean_academics']);

        $previousPlacement = [
            'day' => $item->day_of_week,
            'slot_id' => $item->timetable_slot_id,
            'room_id' => $item->classroom_id,
            'confidence' => $item->confidence,
            'metadata' => $metadata,
        ];

        $item->update([
            'day_of_week' => $day,
            'timetable_slot_id' => $slotId,
            'classroom_id' => $roomId,
            'confidence' => (int) ($alternative['score'] ?? $item->confidence ?? 80),
            'is_locked' => false,
            'explanation' => 'Applied solver alternative after PMC/manual review.',
            'metadata' => array_merge($metadata, [
                'version' => 'PMC OS v0.067',
                'placement_score' => (int) ($alternative['score'] ?? $item->confidence ?? 80),
                'placement_reasons' => $alternative['reasons'] ?? [],
                'previous_placement' => $previousPlacement,
                'applied_solver_alternative' => [
                    'index' => $alternativeIndex,
                    'applied_by' => $actor->id,
                    'applied_at' => now()->toDateTimeString(),
                    'decision_note' => $decisionNote,
                    'alternative' => $alternative,
                    'hard_conflict_override' => $allowHardConflictOverride && $canOverrideHardConflict,
                    'override_reason' => $overrideReason,
                ],
            ]),
        ]);

        $afterQuality = $this->refreshConstraintsAndQuality($run);
        if ((int) $afterQuality->hard_conflicts > $beforeHardConflicts) {
            if (! ($allowHardConflictOverride && $canOverrideHardConflict && filled($overrideReason))) {
                $item->update([
                    'day_of_week' => $previousPlacement['day'],
                    'timetable_slot_id' => $previousPlacement['slot_id'],
                    'classroom_id' => $previousPlacement['room_id'],
                    'confidence' => $previousPlacement['confidence'],
                    'metadata' => array_merge($previousPlacement['metadata'], [
                        'last_blocked_solver_alternative' => [
                            'index' => $alternativeIndex,
                            'blocked_at' => now()->toDateTimeString(),
                            'blocked_by' => $actor->id,
                            'reason' => 'hard_conflict_introduced',
                            'before_hard_conflicts' => $beforeHardConflicts,
                            'after_hard_conflicts' => (int) $afterQuality->hard_conflicts,
                            'alternative' => $alternative,
                        ],
                    ]),
                ]);
                $this->refreshConstraintsAndQuality($run);
                abort(422, 'Solver alternative would introduce a hard conflict. Dean/Admin override with reason is required.');
            }

            $this->audit($actor, 'academic_pmc_v067_solver_alternative_hard_conflict_override', 'Applied solver alternative with Dean/Admin hard-conflict override for item #' . $item->id, $item, [
                'generation_run_id' => $run->id,
                'before_hard_conflicts' => $beforeHardConflicts,
                'after_hard_conflicts' => (int) $afterQuality->hard_conflicts,
                'override_reason' => $overrideReason,
                'alternative' => $alternative,
            ]);
        }

        $this->audit($actor, 'academic_pmc_v066_solver_alternative_applied', 'Applied solver alternative to timetable item #' . $item->id, $item, [
            'generation_run_id' => $run->id,
            'previous_placement' => $previousPlacement,
            'alternative' => $alternative,
            'decision_note' => $decisionNote,
            'before_hard_conflicts' => $beforeHardConflicts,
            'after_hard_conflicts' => (int) $afterQuality->hard_conflicts,
            'hard_conflict_override' => $allowHardConflictOverride && $canOverrideHardConflict && filled($overrideReason),
        ]);

        return $item->fresh();
    }

    public function moveGeneratedItem(User $actor, AcademicPmcTimetableGenerationItem $item, array $data, bool $allowHardConflictOverride = false, ?string $overrideReason = null): AcademicPmcTimetableGenerationItem
    {
        $run = $item->generationRun;
        abort_unless($run, 404, 'Generation run not found for this timetable item.');
        abort_if(in_array($run->status, ['published', 'published_with_dean_override', 'frozen', 'archived'], true), 422, 'Published or frozen timetable runs cannot be changed directly. Create a revision request instead.');

        $slotId = (int) $data['timetable_slot_id'];
        $roomId = (int) $data['classroom_id'];
        $day = (int) $data['day_of_week'];
        abort_unless($day >= 1 && $day <= 7 && TimetableSlot::whereKey($slotId)->exists() && Classroom::whereKey($roomId)->exists(), 422, 'Manual move references an invalid day, slot, or room.');

        $metadata = $item->metadata ?: [];
        $beforeQuality = $this->refreshConstraintsAndQuality($run);
        $beforeHardConflicts = (int) $beforeQuality->hard_conflicts;
        $canOverrideHardConflict = $actor->hasAnyRole(['admin', 'director', 'academic_department_owner', 'dean_academics']);
        $previousPlacement = [
            'day' => $item->day_of_week,
            'slot_id' => $item->timetable_slot_id,
            'room_id' => $item->classroom_id,
            'confidence' => $item->confidence,
            'metadata' => $metadata,
        ];

        $item->update([
            'day_of_week' => $day,
            'timetable_slot_id' => $slotId,
            'classroom_id' => $roomId,
            'confidence' => max(1, (int) ($item->confidence ?? 75) - 2),
            'is_locked' => false,
            'explanation' => 'Moved manually by PMC timetable review.',
            'metadata' => array_merge($metadata, [
                'version' => 'PMC OS v0.068',
                'previous_placement' => $previousPlacement,
                'manual_move' => [
                    'moved_by' => $actor->id,
                    'moved_at' => now()->toDateTimeString(),
                    'decision_note' => $data['decision_note'] ?? null,
                    'target' => ['day' => $day, 'slot_id' => $slotId, 'room_id' => $roomId],
                    'hard_conflict_override' => $allowHardConflictOverride && $canOverrideHardConflict,
                    'override_reason' => $overrideReason,
                ],
            ]),
        ]);

        $afterQuality = $this->refreshConstraintsAndQuality($run);
        if ((int) $afterQuality->hard_conflicts > $beforeHardConflicts) {
            if (! ($allowHardConflictOverride && $canOverrideHardConflict && filled($overrideReason))) {
                $item->update([
                    'day_of_week' => $previousPlacement['day'],
                    'timetable_slot_id' => $previousPlacement['slot_id'],
                    'classroom_id' => $previousPlacement['room_id'],
                    'confidence' => $previousPlacement['confidence'],
                    'metadata' => array_merge($previousPlacement['metadata'], [
                        'last_blocked_manual_move' => [
                            'blocked_at' => now()->toDateTimeString(),
                            'blocked_by' => $actor->id,
                            'reason' => 'hard_conflict_introduced',
                            'before_hard_conflicts' => $beforeHardConflicts,
                            'after_hard_conflicts' => (int) $afterQuality->hard_conflicts,
                            'target' => ['day' => $day, 'slot_id' => $slotId, 'room_id' => $roomId],
                        ],
                    ]),
                ]);
                $this->refreshConstraintsAndQuality($run);
                abort(422, 'Manual move would introduce a hard conflict. Dean/Admin override with reason is required.');
            }

            $this->audit($actor, 'academic_pmc_v068_manual_move_hard_conflict_override', 'Applied manual timetable move with Dean/Admin hard-conflict override for item #' . $item->id, $item, [
                'generation_run_id' => $run->id,
                'before_hard_conflicts' => $beforeHardConflicts,
                'after_hard_conflicts' => (int) $afterQuality->hard_conflicts,
                'override_reason' => $overrideReason,
                'target' => ['day' => $day, 'slot_id' => $slotId, 'room_id' => $roomId],
            ]);
        }

        $this->audit($actor, 'academic_pmc_v068_manual_timetable_item_moved', 'Moved generated timetable item #' . $item->id, $item, [
            'generation_run_id' => $run->id,
            'previous_placement' => $previousPlacement,
            'target' => ['day' => $day, 'slot_id' => $slotId, 'room_id' => $roomId],
            'decision_note' => $data['decision_note'] ?? null,
            'before_hard_conflicts' => $beforeHardConflicts,
            'after_hard_conflicts' => (int) $afterQuality->hard_conflicts,
            'hard_conflict_override' => $allowHardConflictOverride && $canOverrideHardConflict && filled($overrideReason),
        ]);

        return $item->fresh();
    }

    public function refreshGenerationImpactPreview(User $actor, AcademicPmcTimetableGenerationRun $run): Collection
    {
        $items = AcademicPmcTimetableGenerationItem::with(['courseGroup.subject', 'teacher.user', 'classroom', 'slot'])
            ->where('generation_run_id', $run->id)
            ->get();
        $groupIds = $items->pluck('course_group_id')->filter()->unique()->values();
        $teacherIds = $items->pluck('teacher_id')->filter()->unique()->values();
        $roomIds = $items->pluck('classroom_id')->filter()->unique()->values();
        $students = AcademicPmcCourseGroupMember::with('student.user')
            ->whereIn('course_group_id', $groupIds)
            ->where('status', 'active')
            ->get()
            ->unique('student_id')
            ->values();
        $changedItems = $items->filter(function (AcademicPmcTimetableGenerationItem $item) {
            $metadata = $item->metadata ?: [];
            return ! empty($metadata['previous_placement']) || ! empty($metadata['manual_move']) || ! empty($metadata['applied_solver_alternative']);
        })->values();
        $conflicts = AcademicPmcTimetableConstraint::where('generation_run_id', $run->id)->get();

        AcademicPmcTimetableImpactRecord::where('metadata->generation_run_id', $run->id)
            ->where('metadata->version', 'PMC OS v0.069')
            ->delete();

        $records = collect([
            [
                'type' => 'students',
                'title' => 'Students affected by generated timetable',
                'count' => $students->count(),
                'records' => $students->take(20)->map(fn ($member) => [
                    'student_id' => $member->student_id,
                    'name' => $member->student?->user?->name,
                    'course_group_id' => $member->course_group_id,
                ])->values()->all(),
                'severity' => $students->count() > 0 ? 'medium' : 'low',
            ],
            [
                'type' => 'faculty',
                'title' => 'Faculty affected by generated timetable',
                'count' => $teacherIds->count(),
                'records' => $items->whereNotNull('teacher_id')->unique('teacher_id')->take(20)->map(fn ($item) => [
                    'teacher_id' => $item->teacher_id,
                    'name' => $item->teacher?->user?->name,
                    'course_group' => $item->courseGroup?->name,
                ])->values()->all(),
                'severity' => $teacherIds->count() > 0 ? 'medium' : 'low',
            ],
            [
                'type' => 'rooms',
                'title' => 'Rooms and labs affected by generated timetable',
                'count' => $roomIds->count(),
                'records' => $items->whereNotNull('classroom_id')->unique('classroom_id')->take(20)->map(fn ($item) => [
                    'classroom_id' => $item->classroom_id,
                    'name' => $item->classroom?->name,
                    'course_group' => $item->courseGroup?->name,
                ])->values()->all(),
                'severity' => $roomIds->count() > 0 ? 'medium' : 'low',
            ],
            [
                'type' => 'groups',
                'title' => 'Course sections/groups affected by generated timetable',
                'count' => $groupIds->count(),
                'records' => $items->whereNotNull('course_group_id')->unique('course_group_id')->take(20)->map(fn ($item) => [
                    'course_group_id' => $item->course_group_id,
                    'name' => $item->courseGroup?->name,
                    'subject' => $item->courseGroup?->subject?->code,
                ])->values()->all(),
                'severity' => $groupIds->count() > 0 ? 'medium' : 'low',
            ],
            [
                'type' => 'changed_slots',
                'title' => 'Manual or alternative placement changes',
                'count' => $changedItems->count(),
                'records' => $changedItems->take(20)->map(fn ($item) => [
                    'item_id' => $item->id,
                    'course_group' => $item->courseGroup?->name,
                    'day' => $item->day_of_week,
                    'slot' => $item->slot?->name,
                    'room' => $item->classroom?->name,
                ])->values()->all(),
                'severity' => $changedItems->count() > 0 ? 'high' : 'low',
            ],
            [
                'type' => 'conflicts',
                'title' => 'Open hard conflicts and soft warnings',
                'count' => $conflicts->count(),
                'records' => $conflicts->take(20)->map(fn ($constraint) => [
                    'constraint_id' => $constraint->id,
                    'type' => $constraint->constraint_type,
                    'severity' => $constraint->severity,
                    'title' => $constraint->title,
                    'recommended_fix' => $constraint->recommended_fix,
                ])->values()->all(),
                'severity' => $conflicts->where('severity', 'hard')->isNotEmpty() ? 'critical' : ($conflicts->isNotEmpty() ? 'high' : 'low'),
            ],
            [
                'type' => 'notification_audience',
                'title' => 'Notification audience before publish/revision',
                'count' => $students->count() + $teacherIds->count(),
                'records' => [
                    'students' => $students->count(),
                    'faculty' => $teacherIds->count(),
                    'rooms' => $roomIds->count(),
                    'groups' => $groupIds->count(),
                ],
                'severity' => $items->isNotEmpty() ? 'medium' : 'low',
            ],
        ])->map(function (array $impact) use ($run, $actor) {
            return AcademicPmcTimetableImpactRecord::create([
                'impact_type' => $impact['type'],
                'title' => $impact['title'],
                'affected_count' => $impact['count'],
                'affected_records' => $impact['records'],
                'metadata' => [
                    'version' => 'PMC OS v0.069',
                    'generation_run_id' => $run->id,
                    'program_id' => $run->program_id,
                    'batch_id' => $run->batch_id,
                    'term_id' => $run->term_id,
                    'severity' => $impact['severity'],
                    'refreshed_by' => $actor->id,
                    'refreshed_at' => now()->toDateTimeString(),
                ],
            ]);
        });

        $run->update([
            'input_summary' => array_merge($run->input_summary ?: [], [
                'impact_preview' => [
                    'version' => 'PMC OS v0.069',
                    'affected_students' => $students->count(),
                    'affected_faculty' => $teacherIds->count(),
                    'affected_rooms' => $roomIds->count(),
                    'affected_groups' => $groupIds->count(),
                    'changed_slots' => $changedItems->count(),
                    'open_conflicts' => $conflicts->count(),
                    'refreshed_at' => now()->toDateTimeString(),
                ],
            ]),
        ]);

        $this->audit($actor, 'academic_pmc_v069_timetable_impact_preview_refreshed', 'PMC timetable generation impact preview refreshed', $run, [
            'generation_run_id' => $run->id,
            'records' => $records->count(),
            'affected_students' => $students->count(),
            'affected_faculty' => $teacherIds->count(),
            'changed_slots' => $changedItems->count(),
            'open_conflicts' => $conflicts->count(),
        ]);

        return $records;
    }

    public function publishRun(User $actor, AcademicPmcTimetableGenerationRun $run, array $data): TimetableVersion
    {
        $this->refreshConstraintsAndQuality($run);
        $blocking = AcademicPmcTimetablePublishCheck::where('generation_run_id', $run->id)->where('status', 'block')->get();
        $canOverride = $actor->hasAnyRole(['admin', 'director', 'academic_department_owner', 'dean_academics']);

        if ($blocking->isNotEmpty() && ! ($canOverride && ! empty($data['override_reason']))) {
            $blockedChecks = $blocking->pluck('title')->filter()->implode(', ');
            abort(422, 'Publish is blocked by hard timetable checks: ' . ($blockedChecks ?: 'unresolved publish checks') . '. Dean/Admin override requires a reason.');
        }

        $impactRecords = $this->refreshGenerationImpactPreview($actor, $run);
        $impactSummary = $run->fresh()->input_summary['impact_preview'] ?? [];

        $lastVersion = TimetableVersion::where('program_id', $run->program_id)
            ->where('term_id', $run->term_id)
            ->when($run->batch_id, fn ($q) => $q->where('batch_id', $run->batch_id))
            ->max('version_number') ?: 0;

        TimetableVersion::where('program_id', $run->program_id)
            ->where('term_id', $run->term_id)
            ->when($run->batch_id, fn ($q) => $q->where('batch_id', $run->batch_id))
            ->where('status', 'published')
            ->get()
            ->each(fn (TimetableVersion $publishedVersion) => $this->archiveOperationalVersion($publishedVersion));

        $version = TimetableVersion::create([
            'program_id' => $run->program_id,
            'term_id' => $run->term_id,
            'batch_id' => $run->batch_id,
            'version_number' => $lastVersion + 1,
            'status' => 'published',
            'created_by' => $actor->id,
            'published_by' => $actor->id,
            'published_at' => now(),
            'effective_from' => $data['effective_from'] ?? now()->toDateString(),
            'notes' => $data['decision_reason'] ?? 'Published from PMC timetable generation run #' . $run->id,
        ]);

        $run->update(['timetable_version_id' => $version->id, 'status' => $blocking->isNotEmpty() ? 'published_with_dean_override' : 'published']);
        $this->markRunItemsOfficial($run->fresh(), $version, $actor);
        $syncedEntries = $this->syncRunToOperationalTimetable($run->fresh(), $version, $actor);

        AcademicPmcTimetableVersionWorkflow::create([
            'timetable_version_id' => $version->id,
            'generation_run_id' => $run->id,
            'lifecycle_status' => 'published',
            'approval_status' => $blocking->isNotEmpty() ? 'dean_override_published' : 'pmc_published',
            'published_by' => $actor->id,
            'published_at' => now(),
            'decision_reason' => $data['decision_reason'] ?? null,
            'override_reason' => $data['override_reason'] ?? null,
            'publish_summary' => [
                'scheduled' => $run->scheduled_count,
                'unscheduled' => $run->unscheduled_count,
                'hard_conflicts' => $run->hard_conflict_count,
                'soft_warnings' => $run->soft_warning_count,
                'quality_score' => $run->quality_score,
                'blocking_checks' => $blocking->pluck('title')->values(),
                'operational_entries_synced' => $syncedEntries,
                'impact_preview' => array_merge($impactSummary, ['impact_records' => $impactRecords->count(), 'version' => 'PMC OS v0.070']),
            ],
        ]);

        $publishNotificationMetadata = [
            'version' => 'PMC OS v0.071',
            'generation_run_id' => $run->id,
            'impact_preview' => array_merge($impactSummary, ['impact_records' => $impactRecords->count(), 'version' => 'PMC OS v0.070']),
            'scheduled' => $run->scheduled_count,
            'unscheduled' => $run->unscheduled_count,
            'hard_conflicts' => $run->hard_conflict_count,
            'soft_warnings' => $run->soft_warning_count,
            'quality_score' => $run->quality_score,
            'operational_entries_synced' => $syncedEntries,
        ];
        $recipientNotificationCounts = $this->logPublishRecipientNotifications($version, $run->fresh(), $publishNotificationMetadata);
        $this->logLifecycleNotification($version, 'publish', 'Timetable version published', 'students', $publishNotificationMetadata + ['audience_count' => $impactSummary['affected_students'] ?? 0]);
        $this->logLifecycleNotification($version, 'publish', 'Timetable version published', 'faculty', $publishNotificationMetadata + ['audience_count' => $impactSummary['affected_faculty'] ?? 0]);
        $this->audit($actor, 'academic_pmc_v043_timetable_published', 'Published timetable version #' . $version->version_number, $version, ['run_id' => $run->id, 'recipient_notifications' => $recipientNotificationCounts]);

        return $version;
    }

    private function logPublishRecipientNotifications(TimetableVersion $version, AcademicPmcTimetableGenerationRun $run, array $baseMetadata): array
    {
        $items = AcademicPmcTimetableGenerationItem::with(['courseGroup', 'teacher.user'])
            ->where('generation_run_id', $run->id)
            ->where('status', 'scheduled')
            ->get();
        $facultyCreated = 0;
        $studentCreated = 0;

        $items->whereNotNull('teacher_id')
            ->groupBy('teacher_id')
            ->each(function (Collection $teacherItems) use ($version, $baseMetadata, &$facultyCreated) {
                $teacher = $teacherItems->first()?->teacher;
                if (! $teacher?->user_id) {
                    return;
                }

                AcademicPmcTimetableNotification::create([
                    'notification_type' => 'publish',
                    'recipient_type' => 'faculty',
                    'recipient_user_id' => $teacher->user_id,
                    'title' => 'Timetable version published for your assigned classes',
                    'message' => 'Your assigned course groups are included in timetable version #' . $version->version_number . '.',
                    'status' => 'queued',
                    'source_type' => 'timetable_version',
                    'source_key' => (string) $version->id,
                    'metadata' => array_merge($baseMetadata, [
                        'version' => 'PMC OS v0.073',
                        'recipient_scope' => 'individual_faculty',
                        'audience_count' => 1,
                        'course_group_ids' => $teacherItems->pluck('course_group_id')->filter()->unique()->values()->all(),
                    ]),
                ]);
                $facultyCreated++;
            });

        $groupIds = $items->pluck('course_group_id')->filter()->unique()->values();
        AcademicPmcCourseGroupMember::with('student.user')
            ->whereIn('course_group_id', $groupIds)
            ->where('status', 'active')
            ->get()
            ->groupBy('student.user_id')
            ->each(function (Collection $members, mixed $userId) use ($version, $baseMetadata, &$studentCreated) {
                if (! $userId) {
                    return;
                }

                AcademicPmcTimetableNotification::create([
                    'notification_type' => 'publish',
                    'recipient_type' => 'student',
                    'recipient_user_id' => (int) $userId,
                    'title' => 'Timetable version published for your enrolled groups',
                    'message' => 'Your course groups are included in timetable version #' . $version->version_number . '.',
                    'status' => 'queued',
                    'source_type' => 'timetable_version',
                    'source_key' => (string) $version->id,
                    'metadata' => array_merge($baseMetadata, [
                        'version' => 'PMC OS v0.073',
                        'recipient_scope' => 'individual_student',
                        'audience_count' => 1,
                        'course_group_ids' => $members->pluck('course_group_id')->filter()->unique()->values()->all(),
                        'student_ids' => $members->pluck('student_id')->filter()->unique()->values()->all(),
                    ]),
                ]);
                $studentCreated++;
            });

        return ['faculty' => $facultyCreated, 'students' => $studentCreated];
    }

    public function freezeVersion(User $actor, TimetableVersion $version, array $data): AcademicPmcTimetableVersionWorkflow
    {
        $this->authorizeDeanLifecycle($actor, 'freeze');

        $workflow = AcademicPmcTimetableVersionWorkflow::updateOrCreate(
            ['timetable_version_id' => $version->id],
            [
                'generation_run_id' => AcademicPmcTimetableGenerationRun::where('timetable_version_id', $version->id)->latest()->value('id'),
                'lifecycle_status' => 'frozen',
                'approval_status' => 'dean_frozen',
                'frozen_by' => $actor->id,
                'frozen_at' => now(),
                'decision_reason' => $data['decision_reason'] ?? 'Frozen by Dean/Academic leadership.',
                'impact_summary' => $this->versionImpactSummary($version),
            ]
        );

        $this->logLifecycleNotification($version, 'freeze', 'Timetable version frozen', 'faculty');
        $this->audit($actor, 'academic_pmc_v043_timetable_frozen', 'Frozen timetable version #' . $version->version_number, $workflow);

        return $workflow->fresh();
    }

    public function unfreezeVersion(User $actor, TimetableVersion $version, array $data): AcademicPmcTimetableVersionWorkflow
    {
        $this->authorizeDeanLifecycle($actor, 'unfreeze');
        if (empty($data['decision_reason'])) {
            abort(422, 'Unfreeze reason is required.');
        }

        $workflow = AcademicPmcTimetableVersionWorkflow::updateOrCreate(
            ['timetable_version_id' => $version->id],
            [
                'generation_run_id' => AcademicPmcTimetableGenerationRun::where('timetable_version_id', $version->id)->latest()->value('id'),
                'lifecycle_status' => 'revision_requested',
                'approval_status' => 'dean_unfrozen',
                'unfrozen_by' => $actor->id,
                'unfrozen_at' => now(),
                'decision_reason' => $data['decision_reason'],
                'impact_summary' => $this->versionImpactSummary($version),
            ]
        );

        $this->audit($actor, 'academic_pmc_v043_timetable_unfrozen', 'Unfrozen timetable version #' . $version->version_number, $workflow);

        return $workflow->fresh();
    }

    public function rollbackVersion(User $actor, TimetableVersion $version, array $data): TimetableVersion
    {
        $this->authorizeDeanLifecycle($actor, 'rollback');
        abort_unless(in_array($version->status, ['published', 'archived'], true), 422, 'Only a previously published timetable version can be rolled back.');
        if (empty($data['decision_reason'])) {
            abort(422, 'Rollback reason is required.');
        }

        TimetableVersion::where('program_id', $version->program_id)
            ->where('term_id', $version->term_id)
            ->when($version->batch_id, fn ($q) => $q->where('batch_id', $version->batch_id))
            ->where('status', 'published')
            ->where('id', '!=', $version->id)
            ->get()
            ->each(fn (TimetableVersion $publishedVersion) => $this->archiveOperationalVersion($publishedVersion));
        if ($version->status === 'published') {
            $version->update(['status' => 'archived']);
        }

        $rollback = TimetableVersion::create([
            'program_id' => $version->program_id,
            'term_id' => $version->term_id,
            'batch_id' => $version->batch_id,
            'version_number' => (TimetableVersion::where('program_id', $version->program_id)->where('term_id', $version->term_id)->max('version_number') ?: $version->version_number) + 1,
            'status' => 'published',
            'created_by' => $actor->id,
            'published_by' => $actor->id,
            'published_at' => now(),
            'effective_from' => $data['effective_from'] ?? now()->toDateString(),
            'notes' => 'Rollback from version #' . $version->version_number . ': ' . $data['decision_reason'],
        ]);

        AcademicPmcTimetableVersionWorkflow::create([
            'timetable_version_id' => $rollback->id,
            'rollback_from_version_id' => $version->id,
            'lifecycle_status' => 'rollback_published',
            'approval_status' => 'dean_rollback',
            'published_by' => $actor->id,
            'published_at' => now(),
            'decision_reason' => $data['decision_reason'],
            'impact_summary' => $this->versionImpactSummary($version),
        ]);

        $syncedEntries = $this->repointRollbackOperationalEntries($actor, $version, $rollback);
        $this->logLifecycleNotification($rollback, 'rollback', 'Timetable rollback published', 'students');
        $this->audit($actor, 'academic_pmc_v043_timetable_rollback', 'Rollback published from timetable version #' . $version->version_number, $rollback, [
            'rollback_from_version_id' => $version->id,
            'operational_entries_synced' => $syncedEntries,
        ]);

        return $rollback;
    }

    private function repointRollbackOperationalEntries(User $actor, TimetableVersion $source, TimetableVersion $rollback): int
    {
        $updated = TimetableEntry::where('timetable_version_id', $source->id)->update([
            'timetable_version_id' => $rollback->id,
            'status' => 'published',
            'is_active' => true,
            'updated_at' => now(),
        ]);

        if ($updated > 0) {
            return $updated;
        }

        $run = AcademicPmcTimetableGenerationRun::where('timetable_version_id', $source->id)->latest()->first();

        if (! $run) {
            return 0;
        }

        return $this->syncRunToOperationalTimetable($run, $rollback, $actor);
    }

    private function archiveOperationalVersion(TimetableVersion $version): void
    {
        TimetableEntry::where('timetable_version_id', $version->id)->update([
            'status' => 'archived',
            'is_active' => false,
            'updated_at' => now(),
        ]);

        AcademicPmcTimetableGenerationItem::where('timetable_version_id', $version->id)->update([
            'official_status' => 'archived',
            'updated_at' => now(),
        ]);

        if ($version->status !== 'archived') {
            $version->update(['status' => 'archived']);
        }
    }

    private function officialPublishedVersionIds(): array
    {
        return TimetableVersion::where('status', 'published')->pluck('id')->all();
    }

    private function officialPublishedGenerationRunIds(): array
    {
        return AcademicPmcTimetableGenerationRun::whereIn('timetable_version_id', $this->officialPublishedVersionIds())->pluck('id')->all();
    }

    private function officialTimetableItemsQuery(): Builder
    {
        return AcademicPmcTimetableGenerationItem::query()
            ->where('official_status', 'published')
            ->whereNotNull('timetable_version_id')
            ->whereHas('timetableVersion', fn (Builder $query) => $query->where('status', 'published'));
    }

    private function parallelSlotGroups(Collection $items): Collection
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

    private function markRunItemsOfficial(AcademicPmcTimetableGenerationRun $run, TimetableVersion $version, User $actor): void
    {
        AcademicPmcTimetableGenerationItem::with('courseGroup')
            ->where('generation_run_id', $run->id)
            ->whereIn('status', ['scheduled', 'published', 'locked'])
            ->chunkById(100, function ($items) use ($run, $version, $actor) {
                foreach ($items as $item) {
                    $group = $item->courseGroup;
                    $item->update([
                        'timetable_version_id' => $version->id,
                        'program_id' => $group?->program_id ?: $run->program_id,
                        'batch_id' => $group?->batch_id ?: $run->batch_id,
                        'term_id' => $group?->term_id ?: $run->term_id,
                        'subject_id' => $group?->subject_id,
                        'official_status' => 'published',
                        'source_type' => 'generated',
                        'published_at' => now(),
                        'published_by' => $actor->id,
                        'metadata' => array_merge($item->metadata ?: [], [
                            'canonical_official_session' => true,
                            'official_source' => 'academic_pmc_timetable_generation_items',
                            'timetable_version_id' => $version->id,
                            'published_by' => $actor->id,
                            'published_at' => now()->toDateTimeString(),
                        ]),
                    ]);
                }
            });
    }

    private function syncRunToOperationalTimetable(AcademicPmcTimetableGenerationRun $run, TimetableVersion $version, User $actor): int
    {
        $semester = $this->operationalSemester($run->term);
        $synced = 0;

        $items = AcademicPmcTimetableGenerationItem::with(['courseGroup.subject.program.department', 'teacher', 'classroom', 'slot'])
            ->where('generation_run_id', $run->id)
            ->where('timetable_version_id', $version->id)
            ->where('official_status', 'published')
            ->whereIn('status', ['scheduled', 'published', 'locked'])
            ->whereNotNull('teacher_id')
            ->whereNotNull('classroom_id')
            ->whereNotNull('timetable_slot_id')
            ->get();

        foreach ($items as $item) {
            $group = $item->courseGroup;
            if (! $group || ! $group->subject || ! $item->day_of_week) {
                continue;
            }

            $course = $this->legacyCourseForGroup($group);
            $entry = $this->matchingOperationalEntry($semester, $course, $item)
                ?: new TimetableEntry();

            $entry->fill([
                'semester_id' => $semester->id,
                'course_id' => $course->id,
                'program_id' => $group->program_id ?: $run->program_id,
                'term_id' => $group->term_id ?: $run->term_id,
                'batch_id' => $group->batch_id ?: $run->batch_id,
                'subject_id' => $group->subject_id,
                'teacher_id' => $item->teacher_id,
                'classroom_id' => $item->classroom_id,
                'timetable_slot_id' => $item->timetable_slot_id,
                'day_of_week' => $item->day_of_week,
                'is_active' => true,
                'status' => 'published',
                'timetable_version_id' => $version->id,
                'pmc_generation_item_id' => $item->id,
            ]);
            $entry->save();

            $item->update([
                'operational_timetable_entry_id' => $entry->id,
                'metadata' => array_merge($item->metadata ?: [], [
                    'operational_sync' => 'published',
                    'operational_synced_at' => now()->toDateTimeString(),
                    'operational_synced_by' => $actor->id,
                    'timetable_version_id' => $version->id,
                ]),
            ]);
            $synced++;
        }

        $this->audit($actor, 'academic_pmc_v061_operational_timetable_synced', 'PMC generated timetable synced to operational timetable entries', $version, ['generation_run_id' => $run->id, 'synced_entries' => $synced]);
        return $synced;
    }

    private function operationalSemester(?Term $term): Semester
    {
        $semester = $term
            ? Semester::where('number', $term->term_number)->first()
            : null;
        if ($semester) {
            return $semester;
        }

        $semester = Semester::current() ?: Semester::first();
        if ($semester) {
            return $semester;
        }

        $year = AcademicYear::current() ?: AcademicYear::firstOrCreate(
            ['name' => now()->year . '-' . now()->addYear()->year],
            [
                'start_year' => now()->year,
                'end_year' => now()->addYear()->year,
                'start_date' => now()->startOfYear()->toDateString(),
                'end_date' => now()->addYear()->endOfYear()->toDateString(),
                'is_current' => true,
            ]
        );

        return Semester::create([
            'academic_year_id' => $year->id,
            'name' => $term?->name ?: 'PMC Operational Term',
            'number' => $term?->term_number ?: 1,
            'start_date' => now()->startOfMonth()->toDateString(),
            'end_date' => now()->addMonths(4)->endOfMonth()->toDateString(),
            'is_current' => true,
        ]);
    }

    private function legacyCourseForGroup(AcademicPmcCourseGroup $group): Course
    {
        $departmentId = $group->subject?->department_id
            ?: $group->program?->department_id
            ?: Department::query()->value('id');
        if (! $departmentId) {
            $departmentId = Department::firstOrCreate(['code' => 'ACAD'], ['name' => 'Academics'])->id;
        }

        return Course::firstOrCreate(
            ['code' => 'PMCG' . $group->id],
            [
                'department_id' => $departmentId,
                'name' => 'PMC Group ' . $group->name,
                'description' => 'Operational timetable course bridge for PMC group #' . $group->id,
                'duration_years' => 1,
                'total_semesters' => 1,
                'is_active' => true,
            ]
        );
    }

    private function matchingOperationalEntry(Semester $semester, Course $course, AcademicPmcTimetableGenerationItem $item): ?TimetableEntry
    {
        if ($item->operational_timetable_entry_id) {
            $existing = TimetableEntry::where('semester_id', $semester->id)
                ->whereKey($item->operational_timetable_entry_id)
                ->first();

            if ($existing) {
                return $existing;
            }
        }

        return TimetableEntry::where('semester_id', $semester->id)
            ->where('pmc_generation_item_id', $item->id)
            ->first()
            ?: TimetableEntry::where('semester_id', $semester->id)
            ->where('classroom_id', $item->classroom_id)
            ->where('timetable_slot_id', $item->timetable_slot_id)
            ->where('day_of_week', $item->day_of_week)
            ->first()
            ?: TimetableEntry::where('semester_id', $semester->id)
            ->where('teacher_id', $item->teacher_id)
            ->where('timetable_slot_id', $item->timetable_slot_id)
            ->where('day_of_week', $item->day_of_week)
            ->first();
    }

    public function createResolutionAction(User $actor, AcademicPmcTimetableConstraint $constraint, array $data): AcademicPmcTimetableResolutionAction
    {
        $actionType = $data['action_type'] ?? $this->defaultResolutionActionType($constraint);
        $action = AcademicPmcTimetableResolutionAction::updateOrCreate(
            ['constraint_id' => $constraint->id, 'action_type' => $actionType],
            [
                'generation_run_id' => $constraint->generation_run_id,
                'title' => $data['title'] ?? 'Resolve ' . $constraint->title,
                'description' => $data['description'] ?? $constraint->recommended_fix,
                'owner_user_id' => $data['owner_user_id'] ?? $actor->id,
                'assigned_by' => $actor->id,
                'priority' => $data['priority'] ?? ($constraint->severity === 'hard' ? 'high' : 'normal'),
                'status' => 'open',
                'due_at' => $data['due_at'] ?? now()->addDays($constraint->severity === 'hard' ? 1 : 3),
                'metadata' => [
                    'constraint_type' => $constraint->constraint_type,
                    'affected_type' => $constraint->affected_type,
                    'affected_key' => $constraint->affected_key,
                    'source_route' => $constraint->source_route,
                ],
            ]
        );

        $this->audit($actor, 'academic_pmc_v045_resolution_action_created', $action->title, $action, ['constraint_id' => $constraint->id]);
        return $action->fresh();
    }

    public function closeResolutionAction(User $actor, AcademicPmcTimetableResolutionAction $action, array $data): AcademicPmcTimetableResolutionAction
    {
        if (empty($data['resolution_note'])) {
            abort(422, 'Resolution note is required.');
        }

        $action->update([
            'status' => $data['status'] ?? 'resolved',
            'resolution_note' => $data['resolution_note'],
            'evidence' => $data['evidence'] ?? ['method' => 'manual_review', 'actor_user_id' => $actor->id],
            'closed_at' => now(),
        ]);

        $openActions = AcademicPmcTimetableResolutionAction::where('generation_run_id', $action->generation_run_id)
            ->whereIn('status', ['open', 'in_progress', 'blocked'])
            ->count();
        AcademicPmcTimetablePublishCheck::updateOrCreate(
            ['generation_run_id' => $action->generation_run_id, 'check_type' => 'resolution_actions'],
            [
                'status' => $openActions === 0 ? 'pass' : 'block',
                'severity' => $openActions === 0 ? 'info' : 'high',
                'title' => 'Open conflict resolution actions',
                'description' => "{$openActions} resolution action(s) remain open.",
                'required_role' => 'pmc_head',
            ]
        );

        $this->audit($actor, 'academic_pmc_v045_resolution_action_closed', $action->title, $action);
        return $action->fresh();
    }

    public function requestChange(User $actor, array $data): AcademicPmcTimetableChangeRequest
    {
        $targetItem = null;
        if (! empty($data['pmc_generation_item_id'])) {
            $targetItem = $this->officialTimetableItemsQuery()
                ->with(['courseGroup.members', 'classroom', 'teacher.user', 'slot'])
                ->findOrFail($data['pmc_generation_item_id']);
            $data['timetable_version_id'] = $targetItem->timetable_version_id;
        }

        $change = AcademicPmcTimetableChangeRequest::create($data + ['requested_by' => $actor->id, 'status' => 'requested']);

        $targetItem
            ? $this->createSessionChangeImpactRecords($change, $targetItem)
            : $this->createGeneralChangeImpactRecords($change);

        $this->audit($actor, 'academic_pmc_v041_change_requested', $change->reason ?: 'Timetable change requested', $change);
        return $change;
    }

    public function decideChange(User $actor, AcademicPmcTimetableChangeRequest $change, string $status, ?string $note): AcademicPmcTimetableChangeRequest
    {
        if (! in_array($change->status, ['requested', 'pending', 'open', 'revision_requested'], true)) {
            abort(422, 'Reviewed timetable change decisions are locked. Create a new change request for further revision.');
        }

        if (in_array($status, ['rejected', 'revision_requested'], true) && ! $note) {
            abort(422, 'Decision note is required.');
        }
        $change->update(['status' => $status, 'decided_by' => $actor->id, 'decision_note' => $note]);
        $this->audit($actor, 'academic_pmc_v041_change_decided', $change->change_type, $change);
        return $change->fresh();
    }

    private function createGeneralChangeImpactRecords(AcademicPmcTimetableChangeRequest $change): void
    {
        foreach (['faculty', 'students', 'rooms', 'groups', 'workload'] as $type) {
            AcademicPmcTimetableImpactRecord::create([
                'change_request_id' => $change->id,
                'impact_type' => $type,
                'title' => str($type)->headline() . ' affected by timetable change',
                'affected_count' => 0,
                'affected_records' => [],
                'metadata' => ['source' => 'general_change_request'],
            ]);
        }
    }

    private function createSessionChangeImpactRecords(AcademicPmcTimetableChangeRequest $change, AcademicPmcTimetableGenerationItem $item): void
    {
        $group = $item->courseGroup;
        $studentIds = $group
            ? $group->members->where('status', 'active')->pluck('student_id')->filter()->values()
            : collect();

        foreach ([
            [
                'type' => 'faculty',
                'title' => 'Faculty affected by session change',
                'count' => $item->teacher_id ? 1 : 0,
                'records' => array_filter([
                    'teacher_id' => $item->teacher_id,
                    'teacher_name' => $item->teacher?->user?->name,
                ]),
            ],
            [
                'type' => 'students',
                'title' => 'Students affected by session change',
                'count' => $studentIds->count(),
                'records' => ['student_ids' => $studentIds->all()],
            ],
            [
                'type' => 'rooms',
                'title' => 'Room affected by session change',
                'count' => $item->classroom_id ? 1 : 0,
                'records' => array_filter([
                    'classroom_id' => $item->classroom_id,
                    'room' => $item->classroom?->room_number ?? $item->classroom?->name,
                ]),
            ],
            [
                'type' => 'groups',
                'title' => 'Course group affected by session change',
                'count' => $item->course_group_id ? 1 : 0,
                'records' => array_filter([
                    'course_group_id' => $item->course_group_id,
                    'course_group' => $group?->name,
                    'subject_id' => $item->subject_id ?: $group?->subject_id,
                ]),
            ],
            [
                'type' => 'workload',
                'title' => 'Teaching workload affected by session change',
                'count' => max(1, (int) ($item->duration_slots ?? 1)),
                'records' => [
                    'duration_slots' => max(1, (int) ($item->duration_slots ?? 1)),
                    'day_of_week' => $item->day_of_week,
                    'timetable_slot_id' => $item->timetable_slot_id,
                    'slot' => $item->slot?->name,
                ],
            ],
        ] as $impact) {
            AcademicPmcTimetableImpactRecord::create([
                'change_request_id' => $change->id,
                'impact_type' => $impact['type'],
                'title' => $impact['title'],
                'affected_count' => $impact['count'],
                'affected_records' => $impact['records'],
                'metadata' => [
                    'source' => 'canonical_pmc_official_session',
                    'pmc_generation_item_id' => $item->id,
                    'timetable_version_id' => $item->timetable_version_id,
                ],
            ]);
        }
    }

    public function recommendSubstitution(User $actor, array $data): AcademicPmcSubstitutionRecommendation
    {
        $original = Teacher::find($data['original_teacher_id'] ?? null);
        $courseGroup = AcademicPmcCourseGroup::with('subject')->find($data['course_group_id'] ?? null);
        $targetDate = \Carbon\Carbon::parse($data['substitution_date'] ?? now()->toDateString());
        $targetItem = $courseGroup
            ? $this->officialTimetableItemsQuery()
                ->where('course_group_id', $courseGroup->id)
                ->where('teacher_id', $original?->id)
                ->where('status', 'scheduled')
                ->orderByDesc('id')
                ->first()
            : null;
        $dayOfWeek = $targetItem?->day_of_week ?: $targetDate->dayOfWeekIso;
        $slotId = $targetItem?->timetable_slot_id;

        $ranked = Teacher::with('user')
            ->where('id', '!=', $original?->id)
            ->where('status', 'active')
            ->get()
            ->map(fn (Teacher $candidate) => $this->scoreSubstitutionCandidate($candidate, $courseGroup, $dayOfWeek, $slotId))
            ->sortByDesc('score')
            ->values();

        $best = $ranked->first();
        $substitute = $best && ($best['score'] ?? 0) > 0 ? Teacher::find($best['teacher_id']) : null;
        $recommendation = AcademicPmcSubstitutionRecommendation::create([
            'pmc_generation_item_id' => $targetItem?->id,
            'timetable_entry_id' => $targetItem?->operational_timetable_entry_id,
            'course_group_id' => $data['course_group_id'] ?? null,
            'original_teacher_id' => $original?->id,
            'substitute_teacher_id' => $substitute?->id,
            'substitution_date' => $data['substitution_date'] ?? now()->toDateString(),
            'status' => $substitute ? 'recommended' : 'uncovered',
            'score' => $best['score'] ?? 0,
            'reasons' => $substitute ? ($best['reasons'] ?? []) : ['no_conflict_free_substitute_found'],
            'conflict_checks' => [
                'target_day' => $dayOfWeek,
                'target_slot_id' => $slotId,
                'faculty' => $best['conflict_checks']['faculty'] ?? 'uncovered',
                'availability' => $best['conflict_checks']['availability'] ?? 'uncovered',
                'workload' => $best['conflict_checks']['workload'] ?? 'uncovered',
                'ranked_candidates' => $ranked->take(5)->map(fn ($row) => [
                    'teacher_id' => $row['teacher_id'],
                    'teacher_name' => $row['teacher_name'],
                    'score' => $row['score'],
                    'reasons' => $row['reasons'],
                    'conflict_checks' => $row['conflict_checks'],
                ])->values()->all(),
            ],
        ]);
        $this->audit($actor, 'academic_pmc_v041_substitution_recommended', 'Substitution recommendation created', $recommendation);
        return $recommendation;
    }

    private function scoreSubstitutionCandidate(Teacher $candidate, ?AcademicPmcCourseGroup $courseGroup, int $dayOfWeek, ?int $slotId): array
    {
        $preference = AcademicPmcFacultyPreference::where('teacher_id', $candidate->id)
            ->where(fn ($q) => $q->whereNull('term_id')->orWhere('term_id', $courseGroup?->term_id))
            ->first();
        $sameSlotConflict = $slotId
            ? $this->officialTimetableItemsQuery()
                ->where('teacher_id', $candidate->id)
                ->where('day_of_week', $dayOfWeek)
                ->where('timetable_slot_id', $slotId)
                ->where('status', 'scheduled')
                ->exists()
            : false;
        $sameDayCount = $this->officialTimetableItemsQuery()
            ->where('teacher_id', $candidate->id)
            ->where('day_of_week', $dayOfWeek)
            ->where('status', 'scheduled')
            ->count();
        $weeklyCount = $this->officialTimetableItemsQuery()
            ->where('teacher_id', $candidate->id)
            ->where('status', 'scheduled')
            ->count();
        $backupAssignment = $courseGroup
            ? AcademicPmcGroupFacultyAssignment::where('course_group_id', $courseGroup->id)
                ->where('teacher_id', $candidate->id)
                ->where(fn ($q) => $q->where('assignment_role', 'backup')->orWhere('is_backup', true))
                ->exists()
            : false;

        $score = 50;
        $reasons = ['active_faculty'];
        $checks = [
            'faculty' => 'clear',
            'availability' => 'not_declared',
            'workload' => 'clear',
        ];

        if ($sameSlotConflict) {
            $score -= 70;
            $reasons[] = 'same_slot_faculty_conflict';
            $checks['faculty'] = 'blocked_same_slot';
        } else {
            $score += 15;
            $reasons[] = 'no_same_slot_conflict';
        }

        if ($backupAssignment) {
            $score += 25;
            $reasons[] = 'assigned_backup_for_group';
        }

        $expertise = collect($preference?->subject_expertise ?? [])->map(fn ($value) => str($value)->lower()->toString());
        $subjectText = str(($courseGroup?->subject?->code ?? '') . ' ' . ($courseGroup?->subject?->name ?? ''))->lower()->toString();
        if ($expertise->isNotEmpty() && $expertise->contains(fn ($value) => $value !== '' && str_contains($subjectText, $value))) {
            $score += 20;
            $reasons[] = 'subject_expertise_match';
        } elseif ($courseGroup?->subject) {
            $score -= 5;
            $reasons[] = 'subject_expertise_not_confirmed';
        }

        $availableDays = collect($preference?->available_days ?? [])->map(fn ($value) => (int) $value)->filter()->values();
        if ($availableDays->isNotEmpty()) {
            if ($availableDays->contains($dayOfWeek)) {
                $score += 15;
                $reasons[] = 'available_on_substitution_day';
                $checks['availability'] = 'available_day';
            } else {
                $score -= ($preference?->faculty_type === 'adjunct') ? 60 : 35;
                $reasons[] = $preference?->faculty_type === 'adjunct' ? 'adjunct_not_available_that_day' : 'not_in_available_day_list';
                $checks['availability'] = 'blocked_day';
            }
        }

        $slotBlocked = $slotId && $this->isSlotUnavailable($preference?->unavailable_slots ?? [], $dayOfWeek, $slotId);
        if ($slotBlocked) {
            $score -= 60;
            $reasons[] = 'faculty_marked_slot_unavailable';
            $checks['availability'] = 'blocked_slot';
        }

        $maxDaily = (int) ($preference?->max_classes_per_day ?: 4);
        $maxWeekly = (int) ($preference?->max_weekly_load ?: 18);
        if ($sameDayCount + 1 > $maxDaily) {
            $score -= 20;
            $reasons[] = 'daily_load_limit_risk';
            $checks['workload'] = 'daily_limit_risk';
        } else {
            $score += 8;
            $reasons[] = 'daily_load_within_limit';
        }
        if ($weeklyCount + 1 > $maxWeekly) {
            $score -= 20;
            $reasons[] = 'weekly_load_limit_risk';
            $checks['workload'] = 'weekly_limit_risk';
        } else {
            $score += 8;
            $reasons[] = 'weekly_load_within_limit';
        }

        return [
            'teacher_id' => $candidate->id,
            'teacher_name' => $candidate->user?->name ?? $candidate->employee_id ?? 'Unassigned faculty',
            'score' => max(0, min(100, $score)),
            'reasons' => array_values(array_unique($reasons)),
            'conflict_checks' => $checks,
        ];
    }

    private function isSlotUnavailable(array $unavailableSlots, int $dayOfWeek, int $slotId): bool
    {
        foreach ($unavailableSlots as $key => $value) {
            if (is_array($value) && array_key_exists('day', $value) && array_key_exists('slot_id', $value)) {
                if ((int) $value['day'] === $dayOfWeek && (int) $value['slot_id'] === $slotId) {
                    return true;
                }
                continue;
            }

            if (is_numeric($key) && is_array($value)) {
                if ((int) $key === $dayOfWeek && in_array($slotId, array_map('intval', $value), true)) {
                    return true;
                }
                continue;
            }

            if (is_numeric($key) && is_numeric($value)) {
                if ((int) $key === $dayOfWeek && (int) $value === $slotId) {
                    return true;
                }
            }
        }

        return false;
    }

    public function logNotification(User $actor, array $data): AcademicPmcTimetableNotification
    {
        $notification = AcademicPmcTimetableNotification::create($data + ['status' => 'queued']);
        $this->audit($actor, 'academic_pmc_v041_notification_logged', $notification->title, $notification);
        return $notification;
    }

    public function updateNotificationStatus(User $actor, AcademicPmcTimetableNotification $notification, string $status, ?string $note = null): AcademicPmcTimetableNotification
    {
        abort_unless(in_array($status, ['queued', 'sent', 'read', 'failed', 'cancelled'], true), 422, 'Invalid notification status.');
        if (in_array($status, ['failed', 'cancelled'], true) && ! filled($note)) {
            abort(422, 'Failure or cancellation note is required.');
        }

        $metadata = $notification->metadata ?: [];
        $history = $metadata['status_history'] ?? [];
        $history[] = [
            'from' => $notification->status,
            'to' => $status,
            'note' => $note,
            'actor_user_id' => $actor->id,
            'changed_at' => now()->toDateTimeString(),
        ];

        $notification->update([
            'status' => $status,
            'metadata' => array_merge($metadata, [
                'version' => $metadata['version'] ?? 'PMC OS v0.074',
                'latest_status_note' => $note,
                'latest_status_actor_id' => $actor->id,
                'latest_status_changed_at' => now()->toDateTimeString(),
                'status_history' => $history,
            ]),
        ]);

        $this->audit($actor, 'academic_pmc_v074_notification_status_updated', 'PMC timetable notification status updated to ' . $status, $notification, [
            'notification_id' => $notification->id,
            'status' => $status,
            'note' => $note,
        ]);

        return $notification->fresh();
    }

    public function retryNotification(User $actor, AcademicPmcTimetableNotification $notification, ?string $note = null): AcademicPmcTimetableNotification
    {
        abort_unless(in_array($notification->status, ['failed', 'cancelled'], true), 422, 'Only failed or cancelled notifications can be retried.');

        $metadata = $notification->metadata ?: [];
        $retryCount = (int) ($metadata['retry_count'] ?? 0) + 1;
        $changedAt = now();
        $history = $metadata['status_history'] ?? [];
        $history[] = [
            'from' => $notification->status,
            'to' => 'queued',
            'note' => $note,
            'actor_user_id' => $actor->id,
            'changed_at' => $changedAt->toDateTimeString(),
            'retry_count' => $retryCount,
            'action' => 'retry',
        ];

        $notification->update([
            'status' => 'queued',
            'metadata' => array_merge($metadata, [
                'version' => 'PMC OS v0.075',
                'retry_count' => $retryCount,
                'last_retry_note' => $note,
                'last_retry_actor_id' => $actor->id,
                'last_retry_at' => $changedAt->toDateTimeString(),
                'next_retry_at' => $changedAt->copy()->addMinutes(min(60, 5 * $retryCount))->toDateTimeString(),
                'latest_status_note' => $note,
                'latest_status_actor_id' => $actor->id,
                'latest_status_changed_at' => $changedAt->toDateTimeString(),
                'status_history' => $history,
            ]),
        ]);

        $this->audit($actor, 'academic_pmc_v075_notification_retry_queued', 'PMC timetable notification retry queued', $notification, [
            'notification_id' => $notification->id,
            'retry_count' => $retryCount,
            'note' => $note,
        ]);

        return $notification->fresh();
    }

    private function allocationSurface(User $user, array $filters): array
    {
        $batches = $this->applyScope(AcademicPmcCourseAllocationBatch::with(['program', 'batch', 'term', 'owner']), $user, ['program_id' => 'program', 'batch_id' => 'batch', 'term_id' => 'term'])->latest();
        $allocations = $this->applyScope(
            $this->filter(AcademicPmcStudentCourseAllocation::with(['student.user', 'subject', 'term']), $filters),
            $user,
            [],
            ['term' => ['id' => 'term'], 'student' => ['program_id' => 'program', 'batch_id' => 'batch']]
        )->latest();
        $electiveChoices = $this->applyScope(AcademicPmcElectiveChoice::with(['student.user', 'subject', 'term']), $user, ['program_id' => 'program', 'batch_id' => 'batch', 'term_id' => 'term'])->latest();
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
            'allocationPressureDiagnostics' => $this->allocationPressureDiagnostics($user),
        ];
    }

    private function studentBasketSurface(User $user, array $filters): array
    {
        $allocations = $this->applyScope(
            $this->filter(AcademicPmcStudentCourseAllocation::with(['student.user', 'subject', 'term']), $filters),
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
            'basketDiagnostics' => $this->courseBasketDiagnostics($user),
            'allocationPressureDiagnostics' => $this->allocationPressureDiagnostics($user),
        ];
    }

    private function groupSurface(User $user, array $filters): array
    {
        $groups = $this->applyScope($this->filter(AcademicPmcCourseGroup::with(['program', 'subject', 'owner']), $filters), $user, ['program_id' => 'program', 'batch_id' => 'batch', 'term_id' => 'term'])->latest();
        $memberships = $this->applyScope(
            \App\Models\AcademicPmcCourseGroupMember::with(['courseGroup', 'student.user']),
            $user,
            [],
            ['courseGroup' => ['program_id' => 'program', 'batch_id' => 'batch', 'term_id' => 'term', 'subject_id' => 'subject']]
        )->latest();
        $buildRuns = $this->applyScope(AcademicPmcGroupBuildRun::with(['subject', 'creator']), $user, ['program_id' => 'program', 'batch_id' => 'batch', 'term_id' => 'term'])->latest();
        $groupAdjustments = $this->applyScope(
            AcademicPmcCourseGroupAdjustment::with(['courseGroup', 'targetCourseGroup', 'student.user', 'requester', 'decider']),
            $user,
            [],
            ['courseGroup' => ['program_id' => 'program', 'batch_id' => 'batch', 'term_id' => 'term', 'subject_id' => 'subject'], 'targetCourseGroup' => ['program_id' => 'program', 'batch_id' => 'batch', 'term_id' => 'term', 'subject_id' => 'subject'], 'student' => ['program_id' => 'program', 'batch_id' => 'batch']]
        )->latest();

        return [
            'title' => 'PMC Section And Group Builder',
            'description' => 'Core sections, elective groups, lab/tutorial/project groups, and student membership.',
            'groups' => $groups->paginate(15),
            'memberships' => $memberships->paginate(15),
            'buildRuns' => $buildRuns->paginate(10, ['*'], 'build_runs_page'),
            'groupAdjustments' => $groupAdjustments->paginate(15, ['*'], 'adjustments_page'),
            'groupDiagnostics' => $this->courseGroupDiagnostics($user),
        ];
    }

    private function facultySurface(User $user, string $surface, array $filters): array
    {
        $assignments = $this->applyScope(AcademicPmcGroupFacultyAssignment::with(['courseGroup.subject', 'teacher.user']), $user, [], ['courseGroup' => ['program_id' => 'program', 'batch_id' => 'batch', 'term_id' => 'term']])->latest();
        $preferences = $this->applyScope(AcademicPmcFacultyPreference::with('teacher.user'), $user, ['term_id' => 'term'])->latest();
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
        $rules = $this->applyScope(AcademicPmcWorkloadRule::query(), $user, ['program_id' => 'program', 'term_id' => 'term'])->latest();

        return [
            'title' => 'PMC Section/Group Faculty And Load Planning',
            'description' => 'Faculty assignment to exact sections/groups, preferences, adjunct days, load rules, and shortage planning.',
            'assignments' => $assignments->paginate(15),
            'preferences' => $preferences->paginate(15),
            'acknowledgements' => $acknowledgements->paginate(15, ['*'], 'ack_page'),
            'loadReviews' => $loadReviews->paginate(15, ['*'], 'load_reviews_page'),
            'rules' => $rules->paginate(15),
            'facultyDiagnostics' => $this->facultyAllocationDiagnostics($user),
            'facultySuitabilityDiagnostics' => $this->facultySuitabilityDiagnostics(null, $user),
            'surfaceKey' => $surface,
        ];
    }

    private function lockedSlotSurface(User $user, array $filters): array
    {
        $lockedSlots = $this->applyScope(AcademicPmcLockedSlot::with(['slot', 'courseGroup']), $user, ['program_id' => 'program', 'batch_id' => 'batch', 'term_id' => 'term'])->latest();

        return [
            'title' => 'PMC Locked Slots And Timetable Readiness',
            'description' => 'Manual slot reservations and readiness checklist respected by timetable generation.',
            'lockedSlots' => $lockedSlots->paginate(15),
            'readiness' => $this->readinessChecklist($user),
            'readinessInputDiagnostics' => $this->readinessInputDiagnostics($user),
        ];
    }

    private function generatorSurface(User $user, string $surface, array $filters): array
    {
        $generationRunIds = (clone $this->applyScope(
            AcademicPmcTimetableGenerationRun::query(),
            $user,
            ['program_id' => 'program', 'batch_id' => 'batch', 'term_id' => 'term']
        ))->pluck('id');
        $generationDiagnostics = $this->generationValidationDiagnostics($user);
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
            'runs' => $this->applyScope(AcademicPmcTimetableGenerationRun::query(), $user, ['program_id' => 'program', 'batch_id' => 'batch', 'term_id' => 'term'])->latest()->paginate(10),
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

    private function plannerSurface(User $user, array $filters): array
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

        $this->applyTimetableItemSort($items, $filters);

        return [
            'title' => 'PMC Timetable Planning Board',
            'description' => 'Batch, faculty, room, and group grid view with conflict and lock indicators.',
            'items' => $items->paginate(30),
            'constraints' => $this->constrainConstraintsByUserScope(
                AcademicPmcTimetableConstraint::query(),
                $user,
                AcademicPmcTimetableGenerationRun::query(),
                ['program_id' => 'program', 'batch_id' => 'batch', 'term_id' => 'term']
            )
                ->when($filters['severity'] ?? null, fn (Builder $query, string $severity) => $query->where('severity', $severity))
                ->latest()
                ->paginate(15)
                ->withQueryString(),
            'resolutionActions' => $this->applyScope(AcademicPmcTimetableResolutionAction::with(['constraint', 'owner']), $user, [], ['generationRun' => ['program_id' => 'program', 'batch_id' => 'batch', 'term_id' => 'term']])->latest()->paginate(15, ['*'], 'resolution_page'),
        ];
    }

    private function versionSurface(User $user, array $filters): array
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
            'publishReadinessDiagnostics' => $this->publishFreezeReadinessDiagnostics($user),
        ];
    }

    private function substitutionSurface(User $user, array $filters): array
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
            'substitutionEmergencyDiagnostics' => $this->substitutionEmergencyDiagnostics($user),
        ];
    }

    private function reportsSurface(User $user, array $filters): array
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

    private function readinessChecklist(?User $user = null): array
    {
        $facultySuitabilityDiagnostics = $this->facultySuitabilityDiagnostics(null, $user);

        return [
            ['label' => 'Student course allocation locked', 'ready' => $this->applyScope(
                AcademicPmcStudentCourseAllocation::query(),
                $user,
                [],
                ['student' => ['program_id' => 'program', 'batch_id' => 'batch']]
            )->whereIn('basket_status', ['approved', 'locked', 'allocated'])->exists()],
            ['label' => 'Sections/groups created', 'ready' => $this->applyScope(
                AcademicPmcCourseGroup::query(),
                $user,
                ['program_id' => 'program', 'batch_id' => 'batch', 'term_id' => 'term']
            )->exists()],
            ['label' => 'Faculty assigned to groups', 'ready' => $this->applyScope(
                AcademicPmcGroupFacultyAssignment::query(),
                $user,
                [],
                ['courseGroup' => ['program_id' => 'program', 'batch_id' => 'batch', 'term_id' => 'term']]
            )->exists()],
            ['label' => 'Faculty suitability blockers cleared', 'ready' => (int) $facultySuitabilityDiagnostics['blocker_total'] === 0 && (int) $facultySuitabilityDiagnostics['total_assignments'] > 0],
            ['label' => 'Faculty availability/preferences captured', 'ready' => $this->applyScope(
                AcademicPmcFacultyPreference::query(),
                $user,
                ['program_id' => 'program', 'batch_id' => 'batch', 'term_id' => 'term']
            )->exists()],
            ['label' => 'Rooms/labs and locked slots reviewed', 'ready' => $this->applyScope(
                AcademicPmcLockedSlot::query(),
                $user,
                ['program_id' => 'program', 'batch_id' => 'batch', 'term_id' => 'term']
            )->exists()],
            ['label' => 'Hard conflicts zero before publish', 'ready' => AcademicPmcTimetableConstraint::where('severity', 'hard')->count() === 0],
        ];
    }

    private function launchControl(?User $user = null): array
    {
        $basketDiagnostics = $this->courseBasketDiagnostics($user);
        $groupDiagnostics = $this->courseGroupDiagnostics($user);
        $facultyDiagnostics = $this->facultyAllocationDiagnostics($user);
        $facultySuitabilityDiagnostics = $this->facultySuitabilityDiagnostics(null, $user);
        $readinessInputDiagnostics = $this->readinessInputDiagnostics($user);
        $generationDiagnostics = $this->generationValidationDiagnostics($user);
        $publishReadinessDiagnostics = $this->publishFreezeReadinessDiagnostics();
        $facultyBlockers = (int) $facultyDiagnostics['blocker_total'] + (int) $facultySuitabilityDiagnostics['blocker_total'];

        $stages = [
            $this->launchStage('Course baskets', (int) $basketDiagnostics['ready_allocations'], (int) $basketDiagnostics['blocker_total'], 'Clear unapproved, waitlisted, overload, pending exception, and ungrouped basket blockers.', 'academics.pmc.student-course-baskets.index', ['status' => 'pending']),
            $this->launchStage('Sections and groups', (int) $groupDiagnostics['ready_groups'], (int) $groupDiagnostics['blocker_total'], 'Lock valid groups, resolve capacity issues, add missing faculty, and clear pending adjustments.', 'academics.pmc.course-groups.index', ['status' => 'active']),
            $this->launchStage('Faculty allocation', (int) min($facultyDiagnostics['ready_assignments'], $facultySuitabilityDiagnostics['suitable_assignments']), $facultyBlockers, 'Resolve missing primary/backup faculty, suitability/expertise gaps, adjunct-day constraints, acknowledgement concerns, availability gaps, and load-review blockers.', 'academics.pmc.section-faculty-allocation.index', ['status' => 'requested']),
            $this->launchStage('Readiness inputs', (int) $readinessInputDiagnostics['ready_inputs'], (int) $readinessInputDiagnostics['blocker_total'], 'Resolve incomplete preferences, invalid locked slots, hard-lock collisions, and room/lab readiness blockers.', 'academics.pmc.locked-slots.index', []),
            $this->launchStage('Generate and validate', (int) $generationDiagnostics['ready_generations'], (int) $generationDiagnostics['blocker_total'], 'Regenerate stale runs, schedule unscheduled classes, clear hard conflicts, close resolution actions, and refresh impact preview.', 'academics.pmc.timetable-generator.index', []),
            $this->launchStage('Publish and notify', (int) $publishReadinessDiagnostics['ready_versions'], (int) $publishReadinessDiagnostics['blocker_total'], 'Publish/freeze only after lifecycle workflow, impact, sync, revision, and notification blockers are clear.', 'academics.pmc.timetable-versions-v041.index', []),
        ];

        $readyStages = collect($stages)->where('status', 'ready')->count();
        $blockedStages = collect($stages)->where('status', 'blocked')->count();

        return [
            'status' => $blockedStages === 0 ? 'ready_to_launch' : 'attention_required',
            'ready_stages' => $readyStages,
            'total_stages' => count($stages),
            'blocked_stages' => $blockedStages,
            'next_action' => collect($stages)->firstWhere('status', 'blocked') ?: collect($stages)->last(),
            'stages' => $stages,
        ];
    }

    private function publishFreezeReadinessDiagnostics(?User $user = null): array
    {
        $latestVersion = TimetableVersion::latest()->first();
        $latestWorkflow = $latestVersion
            ? AcademicPmcTimetableVersionWorkflow::where('timetable_version_id', $latestVersion->id)->latest()->first()
            : null;

        $publishedVersions = TimetableVersion::where('status', 'published')->count();
        $frozenVersions = TimetableVersion::where('status', 'frozen')->count()
            + AcademicPmcTimetableVersionWorkflow::where('lifecycle_status', 'frozen')->count();
        $blockingPublishChecks = AcademicPmcTimetablePublishCheck::whereIn('status', ['block', 'blocked', 'pending', 'open'])->count();
        $pendingChangeRequests = AcademicPmcTimetableChangeRequest::whereIn('status', ['requested', 'pending', 'open', 'revision_requested'])->count();
        $failedNotifications = AcademicPmcTimetableNotification::whereIn('status', ['failed', 'cancelled'])->count();
        $queuedNotifications = AcademicPmcTimetableNotification::whereIn('status', ['queued', 'pending'])->count();
        $rollbackWorkflows = AcademicPmcTimetableVersionWorkflow::where('lifecycle_status', 'like', '%rollback%')->count();
        $missingLifecycleWorkflow = $latestVersion && ! $latestWorkflow ? 1 : 0;
        $missingOfficialVersion = $publishedVersions + $frozenVersions === 0 ? 1 : 0;
        $operationalEntriesSynced = (int) data_get($latestWorkflow?->publish_summary, 'operational_entries_synced', 0);
        $impactRecords = (int) data_get($latestWorkflow?->publish_summary, 'impact_preview.impact_records', 0);
        $affectedStudents = (int) data_get($latestWorkflow?->impact_summary, 'affected_students', data_get($latestWorkflow?->publish_summary, 'impact_preview.affected_students', 0));
        $affectedFaculty = (int) data_get($latestWorkflow?->impact_summary, 'affected_faculty', data_get($latestWorkflow?->publish_summary, 'impact_preview.affected_faculty', 0));

        $blockerTotal = $missingOfficialVersion
            + $missingLifecycleWorkflow
            + $blockingPublishChecks
            + $pendingChangeRequests
            + $failedNotifications;

        return [
            'latest_version_label' => $latestVersion ? '#' . $latestVersion->version_number : 'No official version',
            'latest_version_status' => $latestVersion?->status ?? 'missing',
            'latest_lifecycle_status' => $latestWorkflow?->lifecycle_status ?? 'missing',
            'latest_approval_status' => $latestWorkflow?->approval_status ?? 'missing',
            'published_versions' => $publishedVersions,
            'frozen_versions' => $frozenVersions,
            'missing_official_version' => $missingOfficialVersion,
            'missing_lifecycle_workflow' => $missingLifecycleWorkflow,
            'blocking_publish_checks' => $blockingPublishChecks,
            'pending_change_requests' => $pendingChangeRequests,
            'failed_notifications' => $failedNotifications,
            'queued_notifications' => $queuedNotifications,
            'rollback_workflows' => $rollbackWorkflows,
            'operational_entries_synced' => $operationalEntriesSynced,
            'impact_records' => $impactRecords,
            'affected_students' => $affectedStudents,
            'affected_faculty' => $affectedFaculty,
            'ready_versions' => $blockerTotal === 0 ? max(1, $publishedVersions + $frozenVersions) : 0,
            'blocker_total' => $blockerTotal,
            'status' => $blockerTotal === 0 ? 'ready' : 'attention_required',
            'recommended_action' => $blockerTotal === 0 ? 'Official timetable lifecycle is ready for freeze/notification monitoring.' : 'Clear official version, lifecycle workflow, publish-check, revision, and failed-notification blockers before final freeze.',
        ];
    }

    private function substitutionEmergencyDiagnostics(?User $user = null): array
    {
        $today = now()->toDateString();
        $tomorrow = now()->addDay()->toDateString();

        $todayRecommendations = AcademicPmcSubstitutionRecommendation::whereDate('substitution_date', $today);
        $upcomingRecommendations = AcademicPmcSubstitutionRecommendation::whereBetween('substitution_date', [$today, $tomorrow]);
        $uncoveredToday = (clone $todayRecommendations)->where(function ($query) {
            $query->where('status', 'uncovered')->orWhereNull('substitute_teacher_id');
        })->count();
        $pendingRecommendations = (clone $upcomingRecommendations)->whereIn('status', ['recommended', 'pending', 'open'])->count();
        $lowScoreRecommendations = (clone $upcomingRecommendations)->where('score', '<', 60)->count();
        $failedSubstitutionNotices = AcademicPmcTimetableNotification::whereIn('notification_type', ['substitution', 'timetable_revision', 'cancellation'])
            ->whereIn('status', ['failed', 'cancelled'])
            ->count();
        $queuedSubstitutionNotices = AcademicPmcTimetableNotification::whereIn('notification_type', ['substitution', 'timetable_revision', 'cancellation'])
            ->whereIn('status', ['queued', 'pending'])
            ->count();
        $sameDayChangeRequests = AcademicPmcTimetableChangeRequest::whereIn('change_type', ['substitution', 'cancellation', 'reschedule', 'room_change', 'faculty_change'])
            ->whereIn('status', ['requested', 'pending', 'open', 'revision_requested'])
            ->count();
        $repeatedOriginalTeachers = AcademicPmcSubstitutionRecommendation::query()
            ->select('original_teacher_id')
            ->whereNotNull('original_teacher_id')
            ->whereDate('substitution_date', '>=', now()->subDays(14)->toDateString())
            ->groupBy('original_teacher_id')
            ->havingRaw('COUNT(*) >= 2')
            ->get()
            ->count();
        $repeatedCourseGroups = AcademicPmcSubstitutionRecommendation::query()
            ->select('course_group_id')
            ->whereNotNull('course_group_id')
            ->whereDate('substitution_date', '>=', now()->subDays(14)->toDateString())
            ->groupBy('course_group_id')
            ->havingRaw('COUNT(*) >= 2')
            ->get()
            ->count();

        $blockerTotal = $uncoveredToday
            + $lowScoreRecommendations
            + $failedSubstitutionNotices
            + $sameDayChangeRequests;

        return [
            'today_recommendations' => (clone $todayRecommendations)->count(),
            'upcoming_recommendations' => (clone $upcomingRecommendations)->count(),
            'uncovered_today' => $uncoveredToday,
            'pending_recommendations' => $pendingRecommendations,
            'low_score_recommendations' => $lowScoreRecommendations,
            'failed_substitution_notices' => $failedSubstitutionNotices,
            'queued_substitution_notices' => $queuedSubstitutionNotices,
            'same_day_change_requests' => $sameDayChangeRequests,
            'repeated_original_teachers' => $repeatedOriginalTeachers,
            'repeated_course_groups' => $repeatedCourseGroups,
            'blocker_total' => $blockerTotal,
            'status' => $blockerTotal === 0 ? 'ready' : 'attention_required',
            'recommended_action' => $blockerTotal === 0 ? 'No urgent substitution blockers for today/tomorrow.' : 'Resolve uncovered classes, weak recommendations, same-day changes, and failed substitution notifications before class time.',
        ];
    }

    private function generationValidationDiagnostics(?User $user = null): array
    {
        $latestRun = AcademicPmcTimetableGenerationRun::latest()->first();

        if (! $latestRun) {
            return [
                'has_run' => false,
                'latest_run_title' => 'No generation run yet',
                'latest_run_status' => 'missing',
                'scheduled_classes' => 0,
                'unscheduled_classes' => 0,
                'hard_conflicts' => 0,
                'soft_warnings' => 0,
                'quality_score' => 0,
                'quality_band' => 'missing',
                'solver_attempts' => 0,
                'failed_solver_attempts' => 0,
                'open_resolution_actions' => 0,
                'blocking_publish_checks' => 0,
                'impact_preview_records' => 0,
                'missing_impact_preview' => 1,
                'stale_input_sources' => 0,
                'ready_generations' => 0,
                'blocker_total' => 1,
                'status' => 'attention_required',
                'recommended_action' => 'Generate the timetable draft, validate constraints, and refresh the impact preview before publish.',
            ];
        }

        $runId = $latestRun->id;
        $this->syncFacultySuitabilityPublishCheck($latestRun);
        $hardConflicts = AcademicPmcTimetableConstraint::where('generation_run_id', $runId)->where('severity', 'hard')->count();
        $softWarnings = AcademicPmcTimetableConstraint::where('generation_run_id', $runId)->where('severity', 'soft')->count();
        $openResolutionActions = AcademicPmcTimetableResolutionAction::where('generation_run_id', $runId)->whereNotIn('status', ['closed', 'done', 'cancelled'])->count();
        $blockingPublishChecks = AcademicPmcTimetablePublishCheck::where('generation_run_id', $runId)->whereIn('status', ['block', 'blocked', 'pending', 'open'])->count();
        $solverAttempts = AcademicPmcTimetableSolverAttempt::where('generation_run_id', $runId)->count();
        $failedSolverAttempts = AcademicPmcTimetableSolverAttempt::where('generation_run_id', $runId)->whereIn('status', ['failed', 'error', 'blocked'])->count();
        $impactPreviewRecords = AcademicPmcTimetableImpactRecord::where('metadata->generation_run_id', $runId)->count();
        $staleInputSources = $this->staleGenerationInputSourceCount($latestRun);
        $qualityScore = (int) $latestRun->quality_score;
        $missingImpactPreview = ((int) $latestRun->scheduled_count > 0 && $impactPreviewRecords === 0) ? 1 : 0;

        $blockerTotal = (int) $latestRun->unscheduled_count
            + $hardConflicts
            + $openResolutionActions
            + $blockingPublishChecks
            + $failedSolverAttempts
            + $missingImpactPreview
            + $staleInputSources
            + ($qualityScore < 70 ? 1 : 0);

        return [
            'has_run' => true,
            'latest_run_title' => $latestRun->title,
            'latest_run_status' => $latestRun->status,
            'scheduled_classes' => (int) $latestRun->scheduled_count,
            'unscheduled_classes' => (int) $latestRun->unscheduled_count,
            'hard_conflicts' => $hardConflicts,
            'soft_warnings' => $softWarnings,
            'quality_score' => $qualityScore,
            'quality_band' => $qualityScore >= 85 ? 'strong' : ($qualityScore >= 70 ? 'publishable' : 'weak'),
            'solver_attempts' => $solverAttempts,
            'failed_solver_attempts' => $failedSolverAttempts,
            'open_resolution_actions' => $openResolutionActions,
            'blocking_publish_checks' => $blockingPublishChecks,
            'impact_preview_records' => $impactPreviewRecords,
            'missing_impact_preview' => $missingImpactPreview,
            'stale_input_sources' => $staleInputSources,
            'ready_generations' => $blockerTotal === 0 ? 1 : 0,
            'blocker_total' => $blockerTotal,
            'status' => $blockerTotal === 0 ? 'ready' : 'attention_required',
            'recommended_action' => $blockerTotal === 0 ? 'Generation is validated and ready for publish review.' : 'Resolve unscheduled classes, stale inputs, conflicts, publish checks, and missing impact preview before publishing.',
        ];
    }

    private function staleGenerationInputSourceCount(AcademicPmcTimetableGenerationRun $run): int
    {
        $updatedAt = $run->updated_at;

        return collect([
            AcademicPmcStudentCourseAllocation::where('updated_at', '>', $updatedAt)->exists(),
            AcademicPmcCourseGroup::where('updated_at', '>', $updatedAt)->exists(),
            AcademicPmcCourseGroupMember::where('updated_at', '>', $updatedAt)->exists(),
            AcademicPmcGroupFacultyAssignment::where('updated_at', '>', $updatedAt)->exists(),
            AcademicPmcFacultyPreference::where('updated_at', '>', $updatedAt)->exists(),
            AcademicPmcLockedSlot::where('updated_at', '>', $updatedAt)->exists(),
            AcademicPmcRoomReadinessReview::where('updated_at', '>', $updatedAt)->exists(),
            AcademicPmcTimetableSessionDemand::where('updated_at', '>', $updatedAt)->exists(),
        ])->filter()->count();
    }

    private function readinessInputDiagnostics(?User $user = null): array
    {
        $preferences = $this->applyScope(
            AcademicPmcFacultyPreference::query(),
            $user,
            ['program_id' => 'program', 'batch_id' => 'batch', 'term_id' => 'term']
        )->get();
        $lockedSlots = $this->applyScope(
            AcademicPmcLockedSlot::query(),
            $user,
            ['program_id' => 'program', 'batch_id' => 'batch', 'term_id' => 'term']
        )->where('status', 'active')->get();
        $roomReviews = $this->applyScope(
            AcademicPmcRoomReadinessReview::query(),
            $user,
            ['program_id' => 'program', 'batch_id' => 'batch', 'term_id' => 'term']
        )->get();

        $incompletePreferences = $preferences->filter(function (AcademicPmcFacultyPreference $preference) {
            return empty($preference->available_days) && empty($preference->preferred_slots) && empty($preference->unavailable_slots);
        })->count();
        $restrictivePreferences = $preferences->filter(function (AcademicPmcFacultyPreference $preference) {
            return (int) $preference->max_classes_per_day <= 2 || (int) $preference->max_weekly_load <= 8 || ! empty($preference->unavailable_slots);
        })->count();

        $lockedMissingContext = $lockedSlots->filter(function (AcademicPmcLockedSlot $slot) {
            return ! $slot->timetable_slot_id || ($slot->slot_type === 'lab_block' && ! $slot->classroom_id) || ($slot->slot_type === 'faculty_fixed' && ! $slot->teacher_id);
        })->count();
        $hardLockCollisions = $this->hardLockCollisionCount($lockedSlots);

        $roomReviewBlockers = AcademicPmcRoomReadinessReview::whereIn('status', ['review_required', 'revision_required', 'rejected'])
            ->orWhereIn('readiness_band', ['blocked', 'warning'])
            ->count();
        $labNotReady = $roomReviews->filter(fn (AcademicPmcRoomReadinessReview $review) => $review->lab_required && ! $review->lab_ready)->count();
        $capacityExceptions = $roomReviews->filter(fn (AcademicPmcRoomReadinessReview $review) => ! $review->capacity_ok)->count();

        $blockerTotal = $incompletePreferences + $lockedMissingContext + $hardLockCollisions + $roomReviewBlockers + $labNotReady + $capacityExceptions;

        return [
            'total_preferences' => $preferences->count(),
            'complete_preferences' => $preferences->count() - $incompletePreferences,
            'incomplete_preferences' => $incompletePreferences,
            'restrictive_preferences' => $restrictivePreferences,
            'active_locked_slots' => $lockedSlots->count(),
            'hard_locked_slots' => $lockedSlots->where('is_hard_lock', true)->count(),
            'soft_locked_slots' => $lockedSlots->where('is_hard_lock', false)->count(),
            'locked_slots_missing_context' => $lockedMissingContext,
            'hard_lock_collisions' => $hardLockCollisions,
            'room_reviews' => $roomReviews->count(),
            'approved_room_reviews' => $roomReviews->whereIn('status', ['approved', 'approved_with_exception'])->count(),
            'room_review_blockers' => $roomReviewBlockers,
            'lab_not_ready' => $labNotReady,
            'capacity_exceptions' => $capacityExceptions,
            'ready_inputs' => $preferences->count() + $lockedSlots->count() + $roomReviews->whereIn('status', ['approved', 'approved_with_exception'])->count(),
            'blocker_total' => $blockerTotal,
            'status' => $blockerTotal === 0 && ($preferences->isNotEmpty() || $lockedSlots->isNotEmpty() || $roomReviews->isNotEmpty()) ? 'ready' : 'attention_required',
            'recommended_action' => $blockerTotal === 0 ? 'Readiness inputs are ready for generation.' : 'Resolve preference, locked-slot, and room/lab readiness blockers before timetable generation.',
        ];
    }

    private function hardLockCollisionCount(Collection $lockedSlots): int
    {
        $hardLocks = $lockedSlots->where('is_hard_lock', true);
        $collisionKeys = collect();

        foreach (['teacher_id', 'classroom_id', 'course_group_id'] as $field) {
            $hardLocks
                ->filter(fn (AcademicPmcLockedSlot $slot) => filled($slot->{$field}))
                ->groupBy(fn (AcademicPmcLockedSlot $slot) => $field . ':' . $slot->day_of_week . ':' . $slot->timetable_slot_id . ':' . $slot->{$field})
                ->filter(fn (Collection $group) => $group->count() > 1)
                ->keys()
                ->each(fn ($key) => $collisionKeys->push($key));
        }

        return $collisionKeys->unique()->count();
    }

    private function facultyAllocationDiagnostics(?User $user = null): array
    {
        $assignments = $this->applyScope(
            AcademicPmcGroupFacultyAssignment::with(['teacher', 'acknowledgements']),
            $user,
            [],
            ['courseGroup' => ['program_id' => 'program', 'batch_id' => 'batch', 'term_id' => 'term']]
        )->get();
        $assignedGroupIds = $assignments->pluck('course_group_id')->filter()->unique();
        $groups = $this->applyScope(
            AcademicPmcCourseGroup::with('facultyAssignments'),
            $user,
            ['program_id' => 'program', 'batch_id' => 'batch', 'term_id' => 'term']
        )->get();
        $assignedTeacherIds = $assignments->pluck('teacher_id')->filter()->unique();
        $preferenceTeacherIds = $this->applyScope(
            AcademicPmcFacultyPreference::query(),
            $user,
            ['program_id' => 'program', 'batch_id' => 'batch', 'term_id' => 'term']
        )
            ->whereIn('teacher_id', $assignedTeacherIds)
            ->pluck('teacher_id')
            ->unique();

        $groupsMissingPrimary = $groups->filter(fn (AcademicPmcCourseGroup $group) => $group->facultyAssignments->whereIn('assignment_role', ['primary', 'co_faculty', 'lab_faculty', 'tutorial_faculty'])->isEmpty())->count();
        $groupsWithoutBackup = $groups->filter(fn (AcademicPmcCourseGroup $group) => $group->facultyAssignments->where('is_backup', true)->isEmpty())->count();
        $scopedAssignmentIds = $assignments->pluck('id')->filter()->values();
        $pendingAcknowledgements = $user && ! $this->policy->canIgnorePmcScope($user)
            ? AcademicPmcFacultyAssignmentAcknowledgement::whereIn('group_faculty_assignment_id', $scopedAssignmentIds)->whereIn(
                'status',
                ['pending', 'requested', 'concern_raised', 'declined', 'revision_required']
            )->count()
            : AcademicPmcFacultyAssignmentAcknowledgement::whereIn(
                'status',
                ['pending', 'requested', 'concern_raised', 'declined', 'revision_required']
            )->count();
        $assignmentsWithoutAcknowledgement = $assignments->filter(fn (AcademicPmcGroupFacultyAssignment $assignment) => $assignment->acknowledgements->isEmpty())->count();
        $teachersMissingPreference = $assignedTeacherIds->diff($preferenceTeacherIds)->count();
        $unapprovedAssignments = $assignments->whereNotIn('approval_status', ['pmc_approved', 'faculty_accepted', 'accepted', 'approved'])->count();
        $scopedTeacherIds = $assignments->pluck('teacher_id')->filter()->values();
        $loadReviewBlockers = AcademicPmcFacultyLoadReview::query()
            ->when($user && ! $this->policy->canIgnorePmcScope($user), function ($query) use ($scopedTeacherIds) {
                if ($scopedTeacherIds->isEmpty()) {
                    $query->whereRaw('1 = 0');
                    return;
                }
                $query->whereIn('teacher_id', $scopedTeacherIds);
            })
            ->whereIn('status', ['review_required', 'approval_required', 'revision_required'])
            ->orWhere(fn ($query) => $query->whereIn('load_band', ['overload', 'critical'])->whereNotIn('status', ['approved_overload', 'approved']))
            ->count();
        $overloadReviews = AcademicPmcFacultyLoadReview::query()
            ->when($user && ! $this->policy->canIgnorePmcScope($user), function ($query) use ($scopedTeacherIds) {
                if ($scopedTeacherIds->isEmpty()) {
                    $query->whereRaw('1 = 0');
                    return;
                }
                $query->whereIn('teacher_id', $scopedTeacherIds);
            })
            ->whereIn('load_band', ['overload', 'critical'])->count();
        $blockerTotal = $groupsMissingPrimary + $pendingAcknowledgements + $assignmentsWithoutAcknowledgement + $teachersMissingPreference + $unapprovedAssignments + $loadReviewBlockers;

        return [
            'total_assignments' => $assignments->count(),
            'ready_assignments' => $assignments->filter(fn (AcademicPmcGroupFacultyAssignment $assignment) => in_array($assignment->approval_status, ['pmc_approved', 'faculty_accepted', 'accepted', 'approved'], true))->count(),
            'assigned_groups' => $assignedGroupIds->count(),
            'groups_missing_primary' => $groupsMissingPrimary,
            'groups_without_backup' => $groupsWithoutBackup,
            'pending_acknowledgements' => $pendingAcknowledgements,
            'assignments_without_acknowledgement' => $assignmentsWithoutAcknowledgement,
            'teachers_missing_preference' => $teachersMissingPreference,
            'unapproved_assignments' => $unapprovedAssignments,
            'load_review_blockers' => $loadReviewBlockers,
            'overload_reviews' => $overloadReviews,
            'blocker_total' => $blockerTotal,
            'status' => $blockerTotal === 0 && $assignments->isNotEmpty() ? 'ready' : 'attention_required',
            'recommended_action' => $blockerTotal === 0 ? 'Faculty allocation is ready for timetable generation.' : 'Resolve faculty assignment, acknowledgement, preference, and load-review blockers before timetable generation.',
        ];
    }

    private function facultySuitabilityDiagnostics(?AcademicPmcTimetableGenerationRun $run = null, ?User $user = null): array
    {
        $courseGroupIds = $run
            ? AcademicPmcTimetableGenerationItem::where('generation_run_id', $run->id)->pluck('course_group_id')->filter()->unique()->values()
            : collect();
        $assignments = AcademicPmcGroupFacultyAssignment::with(['courseGroup.subject', 'teacher.user', 'acknowledgements'])
            ->when($run && $courseGroupIds->isNotEmpty(), fn ($query) => $query->whereIn('course_group_id', $courseGroupIds))
            ->when($run && $courseGroupIds->isEmpty(), fn ($query) => $query->whereRaw('1 = 0'))
            ->when(! $run && $user, function ($query) use ($user) {
                return $this->applyScope(
                    $query,
                    $user,
                    [],
                    ['courseGroup' => ['program_id' => 'program', 'batch_id' => 'batch', 'term_id' => 'term']]
                );
            })
            ->get();
        $teacherIds = $assignments->pluck('teacher_id')->filter()->unique();
        $preferences = AcademicPmcFacultyPreference::whereIn('teacher_id', $teacherIds)->get()->groupBy('teacher_id');
        $loadReviews = AcademicPmcFacultyLoadReview::whereIn('teacher_id', $teacherIds)->latest()->get()->groupBy('teacher_id');

        $missingExpertise = 0;
        $adjunctDayRisk = 0;
        $restrictionRisks = 0;
        $ackConcernRisks = 0;
        $declinedAssignments = 0;
        $overloadRisks = 0;
        $unapprovedSuitability = 0;
        $backupOnlyPrimaryGap = 0;
        $suitableAssignments = 0;

        foreach ($assignments as $assignment) {
            $teacherPreferences = $preferences->get($assignment->teacher_id, collect());
            $preference = $teacherPreferences->firstWhere('term_id', $assignment->courseGroup?->term_id)
                ?: $teacherPreferences->firstWhere('term_id', null);
            $teacherLoadReviews = $loadReviews->get($assignment->teacher_id, collect());
            $latestLoad = ($run ? $teacherLoadReviews->firstWhere('generation_run_id', $run->id) : null)
                ?: $teacherLoadReviews->firstWhere('term_id', $assignment->courseGroup?->term_id)
                ?: $teacherLoadReviews->firstWhere('term_id', null);
            $subjectId = $assignment->courseGroup?->subject_id;
            $expertise = collect($preference?->subject_expertise ?? [])->map(fn ($value) => (string) $value);
            $hasExpertise = ! $subjectId || $expertise->isEmpty() || $expertise->contains((string) $subjectId) || $expertise->contains($assignment->courseGroup?->subject?->code);
            $isAdjunct = in_array($preference?->faculty_type, ['adjunct', 'visiting', 'guest'], true);
            $hasAllowedDays = ! $isAdjunct || ! empty($preference?->available_days);
            $hasRestriction = filled($preference?->restriction_notes) || ! empty($preference?->unavailable_slots);
            $ackStatuses = $assignment->acknowledgements->pluck('status')->merge($assignment->acknowledgements->pluck('response_type'))->filter();
            $hasConcern = $ackStatuses->intersect(['concern_raised', 'decline', 'declined', 'revision_required'])->isNotEmpty();
            $isDeclined = $ackStatuses->intersect(['decline', 'declined'])->isNotEmpty();
            $loadBlocked = $latestLoad && (in_array($latestLoad->load_band, ['overload', 'critical'], true) || in_array($latestLoad->status, ['approval_required', 'revision_required'], true));
            $approved = in_array($assignment->approval_status, ['pmc_approved', 'faculty_accepted', 'accepted', 'approved'], true);
            $primaryGap = $assignment->is_backup && ! AcademicPmcGroupFacultyAssignment::where('course_group_id', $assignment->course_group_id)
                ->whereIn('assignment_role', ['primary', 'co_faculty', 'lab_faculty', 'tutorial_faculty'])
                ->exists();

            $missingExpertise += $hasExpertise ? 0 : 1;
            $adjunctDayRisk += $hasAllowedDays ? 0 : 1;
            $restrictionRisks += $hasRestriction ? 1 : 0;
            $ackConcernRisks += $hasConcern ? 1 : 0;
            $declinedAssignments += $isDeclined ? 1 : 0;
            $overloadRisks += $loadBlocked ? 1 : 0;
            $unapprovedSuitability += $approved ? 0 : 1;
            $backupOnlyPrimaryGap += $primaryGap ? 1 : 0;

            if ($hasExpertise && $hasAllowedDays && ! $hasConcern && ! $loadBlocked && $approved && ! $primaryGap) {
                $suitableAssignments++;
            }
        }

        $blockerTotal = $missingExpertise
            + $adjunctDayRisk
            + $ackConcernRisks
            + $declinedAssignments
            + $overloadRisks
            + $unapprovedSuitability
            + $backupOnlyPrimaryGap;

        return [
            'total_assignments' => $assignments->count(),
            'suitable_assignments' => $suitableAssignments,
            'missing_expertise' => $missingExpertise,
            'adjunct_day_risk' => $adjunctDayRisk,
            'restriction_risks' => $restrictionRisks,
            'acknowledgement_concerns' => $ackConcernRisks,
            'declined_assignments' => $declinedAssignments,
            'overload_risks' => $overloadRisks,
            'unapproved_suitability' => $unapprovedSuitability,
            'backup_only_primary_gap' => $backupOnlyPrimaryGap,
            'blocker_total' => $blockerTotal,
            'status' => $blockerTotal === 0 && $assignments->isNotEmpty() ? 'ready' : 'attention_required',
            'recommended_action' => $blockerTotal === 0
                ? 'Faculty suitability is ready for timetable generation.'
                : 'Resolve subject expertise gaps, adjunct-day constraints, acknowledgement concerns, overload approvals, and backup-only primary gaps before final timetable generation.',
        ];
    }

    private function courseGroupDiagnostics(?User $user = null): array
    {
        $scopeIds = $user && ! $this->policy->canIgnorePmcScope($user) ? [
            'program_id' => $this->policy->scopedProgramIds($user),
            'batch_id' => $this->policy->scopedBatchIds($user),
            'term_id' => $this->policy->scopedTermIds($user),
            'subject_id' => $this->policy->scopedSubjectIds($user),
        ] : null;
        $applyAnyCourseGroupScope = function (Builder $query) use ($scopeIds): Builder {
            if ($scopeIds === null) {
                return $query;
            }

            $hasAnyConcreteScope = collect($scopeIds)->contains(fn ($ids) => is_array($ids) && ! empty($ids));
            if (! $hasAnyConcreteScope) {
                return $query->whereRaw('1 = 0');
            }

            return $query->where(function (Builder $scopeQuery) use ($scopeIds): void {
                foreach ($scopeIds as $column => $ids) {
                    if (is_array($ids) && ! empty($ids)) {
                        $scopeQuery->orWhereIn($column, $ids);
                    }
                }
            });
        };

        $groups = $applyAnyCourseGroupScope(AcademicPmcCourseGroup::with(['members', 'facultyAssignments']))->get();
        $pendingAdjustments = AcademicPmcCourseGroupAdjustment::whereIn('status', ['requested', 'pending', 'under_review'])
            ->when($scopeIds !== null, fn ($query) => $query->whereHas('courseGroup', fn (Builder $groupQuery) => $applyAnyCourseGroupScope($groupQuery)))
            ->count();
        $ungroupedAllocations = AcademicPmcStudentCourseAllocation::whereIn('basket_status', ['approved', 'locked', 'allocated'])
            ->when($scopeIds !== null, function ($query) use ($scopeIds) {
                $hasAnyConcreteScope = collect($scopeIds)->contains(fn ($ids) => is_array($ids) && ! empty($ids));
                if (! $hasAnyConcreteScope) {
                    return $query->whereRaw('1 = 0');
                }

                return $query->where(function (Builder $scopeQuery) use ($scopeIds): void {
                    if (! empty($scopeIds['term_id'])) {
                        $scopeQuery->orWhereIn('term_id', $scopeIds['term_id']);
                    }
                    if (! empty($scopeIds['subject_id'])) {
                        $scopeQuery->orWhereIn('subject_id', $scopeIds['subject_id']);
                    }
                    if (! empty($scopeIds['program_id'])) {
                        $scopeQuery->orWhereHas('student', fn (Builder $studentQuery) => $studentQuery->whereIn('program_id', $scopeIds['program_id']));
                    }
                    if (! empty($scopeIds['batch_id'])) {
                        $scopeQuery->orWhereHas('student', fn (Builder $studentQuery) => $studentQuery->whereIn('batch_id', $scopeIds['batch_id']));
                    }
                });
            })
            ->whereDoesntHave('groupMemberships', fn ($query) => $query->where('status', 'active'))
            ->count();

        $underMin = $groups->filter(fn (AcademicPmcCourseGroup $group) => (int) $group->current_strength < (int) $group->min_capacity)->count();
        $overCapacity = $groups->filter(fn (AcademicPmcCourseGroup $group) => (int) $group->current_strength > (int) $group->max_capacity)->count();
        $withoutFaculty = $groups->filter(fn (AcademicPmcCourseGroup $group) => $group->facultyAssignments->isEmpty())->count();
        $unlocked = $groups->where('is_locked', false)->count();
        $draft = $groups->whereNotIn('status', ['active', 'locked', 'approved'])->count();
        $strengthMismatch = $groups->filter(fn (AcademicPmcCourseGroup $group) => $group->members->where('status', 'active')->count() !== (int) $group->current_strength)->count();
        $blockerTotal = $underMin + $overCapacity + $withoutFaculty + $ungroupedAllocations + $pendingAdjustments + $strengthMismatch;

        return [
            'total_groups' => $groups->count(),
            'ready_groups' => $groups->filter(fn (AcademicPmcCourseGroup $group) => $group->is_locked && $group->facultyAssignments->isNotEmpty() && (int) $group->current_strength >= (int) $group->min_capacity && (int) $group->current_strength <= (int) $group->max_capacity)->count(),
            'unlocked_groups' => $unlocked,
            'draft_groups' => $draft,
            'under_min_groups' => $underMin,
            'over_capacity_groups' => $overCapacity,
            'groups_without_faculty' => $withoutFaculty,
            'ungrouped_allocations' => $ungroupedAllocations,
            'pending_adjustments' => $pendingAdjustments,
            'strength_mismatch_groups' => $strengthMismatch,
            'blocker_total' => $blockerTotal,
            'status' => $blockerTotal === 0 && $groups->isNotEmpty() ? 'ready' : 'attention_required',
            'recommended_action' => $blockerTotal === 0 ? 'Sections and groups are ready for timetable generation.' : 'Resolve group capacity, locking, faculty, and membership blockers before timetable generation.',
        ];
    }

    private function courseBasketDiagnostics(?User $user = null): array
    {
        $allocations = AcademicPmcStudentCourseAllocation::with(['subject', 'allocationBatch', 'groupMemberships'])
            ->when($user && ! $this->policy->canIgnorePmcScope($user), function ($query) use ($user) {
                return $this->applyScope(
                    $query,
                    $user,
                    [],
                    ['student' => ['program_id' => 'program', 'batch_id' => 'batch']]
                );
            })
            ->whereNotIn('basket_status', ['dropped', 'withdrawn'])
            ->get();

        $ungrouped = $allocations->filter(fn (AcademicPmcStudentCourseAllocation $allocation) => $allocation->groupMemberships->where('status', 'active')->isEmpty())->count();
        $unapproved = $allocations->whereNotIn('basket_status', ['approved', 'locked', 'allocated'])->count();
        $waitlisted = $allocations->where('waitlisted', true)->count();
        $flagged = $allocations->filter(fn (AcademicPmcStudentCourseAllocation $allocation) => filled($allocation->validation_flags))->count();
        $pendingExceptions = AcademicPmcCourseAllocationException::whereIn('status', ['requested', 'pending', 'under_review'])->count();

        $creditOverload = $allocations
            ->groupBy(fn (AcademicPmcStudentCourseAllocation $allocation) => ($allocation->student_id ?: 'missing') . '-' . ($allocation->term_id ?: 'missing'))
            ->filter(function (Collection $studentTermAllocations) {
                $credits = (int) $studentTermAllocations->sum(fn (AcademicPmcStudentCourseAllocation $allocation) => (int) ($allocation->subject?->credits ?? 0));
                $maxCredits = (int) data_get($studentTermAllocations->first()?->allocationBatch?->rules, 'max_credits', 30);

                return $credits > $maxCredits;
            })
            ->count();

        $blockerTotal = $unapproved + $waitlisted + $flagged + $pendingExceptions + $ungrouped + $creditOverload;

        return [
            'total_allocations' => $allocations->count(),
            'ready_allocations' => $allocations->whereIn('basket_status', ['approved', 'locked', 'allocated'])->count(),
            'unapproved_allocations' => $unapproved,
            'waitlisted_allocations' => $waitlisted,
            'flagged_allocations' => $flagged,
            'pending_exceptions' => $pendingExceptions,
            'ungrouped_allocations' => $ungrouped,
            'credit_overload_baskets' => $creditOverload,
            'blocker_total' => $blockerTotal,
            'status' => $blockerTotal === 0 && $allocations->isNotEmpty() ? 'ready' : 'attention_required',
            'recommended_action' => $blockerTotal === 0 ? 'Course baskets are ready for group locking.' : 'Review basket blockers before section/group locking.',
        ];
    }

    private function allocationPressureDiagnostics(?User $user = null): array
    {
        $choices = AcademicPmcElectiveChoice::query();
        $allocations = AcademicPmcStudentCourseAllocation::query();
        $exceptions = AcademicPmcCourseAllocationException::query();

        $submittedChoices = (clone $choices)->whereIn('status', ['submitted', 'pending'])->count();
        $allocatedChoices = (clone $choices)->where('status', 'allocated')->count();
        $waitlistedChoices = (clone $choices)->where('status', 'waitlisted')->count();
        $rejectedChoices = (clone $choices)->where('status', 'rejected')->count();
        $unprocessedChoiceStudents = (clone $choices)
            ->whereIn('status', ['submitted', 'pending', 'waitlisted'])
            ->whereNotNull('student_id')
            ->distinct('student_id')
            ->count('student_id');
        $waitlistSubjects = AcademicPmcStudentCourseAllocation::query()
            ->select('subject_id')
            ->where('waitlisted', true)
            ->whereNotNull('subject_id')
            ->groupBy('subject_id')
            ->get()
            ->count();
        $duplicateStudentSubjectAllocations = AcademicPmcStudentCourseAllocation::query()
            ->selectRaw('student_id, subject_id, term_id, COUNT(*) as allocation_count')
            ->whereNotNull('student_id')
            ->whereNotNull('subject_id')
            ->groupBy('student_id', 'subject_id', 'term_id')
            ->havingRaw('COUNT(*) > 1')
            ->get()
            ->count();
        $studentsWithoutBasket = Student::where('status', 'active')
            ->whereNotIn('id', AcademicPmcStudentCourseAllocation::query()
                ->select('student_id')
                ->whereNotNull('student_id')
                ->whereNotIn('basket_status', ['dropped', 'withdrawn']))
            ->count();
        $singleCourseBaskets = AcademicPmcStudentCourseAllocation::query()
            ->selectRaw('student_id, term_id, COUNT(*) as allocation_count')
            ->whereNotNull('student_id')
            ->whereNotIn('basket_status', ['dropped', 'withdrawn'])
            ->groupBy('student_id', 'term_id')
            ->havingRaw('COUNT(*) <= 1')
            ->get()
            ->count();
        $pendingAddDrop = (clone $exceptions)->whereIn('exception_type', ['add', 'drop', 'open_elective'])->whereIn('status', ['requested', 'pending', 'under_review'])->count();
        $pendingRepeatBacklog = (clone $exceptions)->whereIn('exception_type', ['repeat', 'backlog', 'improvement'])->whereIn('status', ['requested', 'pending', 'under_review'])->count();
        $deanApprovalPending = (clone $exceptions)->where('requires_dean_approval', true)->whereIn('status', ['requested', 'pending', 'under_review'])->count();
        $manualOverrides = (clone $allocations)->where(function ($query) {
            $query->whereNotNull('override_reason')->orWhere('allocation_source', 'manual_override');
        })->count();
        $recentAllocationRounds = AcademicPmcCourseAllocationBatch::where('created_at', '>=', now()->subDays(14))->count();

        $pressureTotal = $submittedChoices
            + $waitlistedChoices
            + $pendingAddDrop
            + $pendingRepeatBacklog
            + $deanApprovalPending
            + $duplicateStudentSubjectAllocations
            + $studentsWithoutBasket
            + $singleCourseBaskets;

        return [
            'elective_choices_total' => (clone $choices)->count(),
            'submitted_choices' => $submittedChoices,
            'allocated_choices' => $allocatedChoices,
            'waitlisted_choices' => $waitlistedChoices,
            'rejected_choices' => $rejectedChoices,
            'unprocessed_choice_students' => $unprocessedChoiceStudents,
            'waitlist_subjects' => $waitlistSubjects,
            'duplicate_student_subject_allocations' => $duplicateStudentSubjectAllocations,
            'students_without_basket' => $studentsWithoutBasket,
            'single_course_baskets' => $singleCourseBaskets,
            'pending_add_drop' => $pendingAddDrop,
            'pending_repeat_backlog' => $pendingRepeatBacklog,
            'dean_approval_pending' => $deanApprovalPending,
            'manual_overrides' => $manualOverrides,
            'recent_allocation_rounds' => $recentAllocationRounds,
            'pressure_total' => $pressureTotal,
            'status' => $pressureTotal === 0 ? 'ready' : 'attention_required',
            'recommended_action' => $pressureTotal === 0
                ? 'Allocation rounds are settled for section/group locking.'
                : 'Resolve pending elective choices, waitlists, add/drop exceptions, repeat/backlog cases, duplicate baskets, and incomplete student baskets before locking sections.',
        ];
    }

    private function launchStage(string $label, int $doneCount, int $blockerCount, string $action, string $route, array $filters): array
    {
        return [
            'label' => $label,
            'done_count' => $doneCount,
            'blocker_count' => $blockerCount,
            'status' => $doneCount > 0 && $blockerCount === 0 ? 'ready' : 'blocked',
            'recommended_action' => $action,
            'route' => $route,
            'filters' => $filters,
        ];
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

    private function scopedGenerationRunIdsByUser(User $user): \Illuminate\Support\Collection
    {
        if ($this->policy->canIgnorePmcScope($user)) {
            return AcademicPmcTimetableGenerationRun::query()->pluck('id');
        }

        $scopes = $this->applyScope(
            AcademicPmcTimetableGenerationRun::query(),
            $user,
            ['program_id' => 'program', 'batch_id' => 'batch', 'term_id' => 'term']
        );

        return (clone $scopes)->pluck('id');
    }

    private function constrainConstraintsByUserScope(
        Builder $query,
        User $user,
        Builder $generationRunQuery,
        array $generationRunScopeMap = ['program_id' => 'program', 'batch_id' => 'batch', 'term_id' => 'term']
    ): Builder {
        if ($this->policy->canIgnorePmcScope($user)) {
            return $query;
        }

        $runIds = (clone $this->applyScope($generationRunQuery, $user, $generationRunScopeMap))->pluck('id');
        if ($runIds->isEmpty()) {
            return $query->whereRaw('1 = 0');
        }

        return $query->whereIn('generation_run_id', $runIds);
    }

    private function filter(Builder $query, array $filters): Builder
    {
        $table = $query->getModel()->getTable();

        foreach (['program_id', 'batch_id', 'term_id', 'subject_id', 'student_id', 'allocation_type'] as $field) {
            if (! empty($filters[$field]) && Schema::hasColumn($table, $field)) {
                $query->where($field, $filters[$field]);
            }
        }

        if (! empty($filters['status'])) {
            if (Schema::hasColumn($table, 'status')) {
                $query->where('status', $filters['status']);
            } elseif ($query->getModel() instanceof AcademicPmcStudentCourseAllocation) {
                $query->where(fn (Builder $inner) => $inner
                    ->where('basket_status', $filters['status'])
                    ->orWhere('approval_status', $filters['status']));
            }
        }

        if (! empty($filters['search'])) {
            $search = $filters['search'];
            if ($query->getModel() instanceof AcademicPmcStudentCourseAllocation) {
                $query->where(function (Builder $inner) use ($search) {
                    $inner->whereHas('student.user', fn (Builder $user) => $user->where('name', 'like', "%{$search}%"))
                        ->orWhereHas('student', fn (Builder $student) => $student
                            ->where('enrollment_number', 'like', "%{$search}%")
                            ->orWhere('roll_number', 'like', "%{$search}%")
                            ->orWhere('student_id', 'like', "%{$search}%"))
                        ->orWhereHas('subject', fn (Builder $subject) => $subject->where('name', 'like', "%{$search}%")->orWhere('code', 'like', "%{$search}%"));
                });
            } elseif ($query->getModel() instanceof AcademicPmcCourseGroup) {
                $query->where(function (Builder $inner) use ($search) {
                    $inner->where('name', 'like', "%{$search}%")
                        ->orWhere('group_type', 'like', "%{$search}%")
                        ->orWhereHas('subject', fn (Builder $subject) => $subject->where('name', 'like', "%{$search}%")->orWhere('code', 'like', "%{$search}%"));
                });
            }
        }

        return $query;
    }

    private function applyTimetableItemSort(Builder $query, array $filters): void
    {
        $sort = $filters['sort'] ?? 'day_of_week';
        $direction = ($filters['direction'] ?? 'asc') === 'desc' ? 'desc' : 'asc';

        if (in_array($sort, ['day_of_week', 'timetable_slot_id', 'status', 'confidence'], true)) {
            $query->orderBy($sort, $direction)->orderBy('timetable_slot_id');
            return;
        }

        $query->orderBy('day_of_week')->orderBy('timetable_slot_id');
    }

    private function courseAllocationExportRows(User $user, array $filters): array
    {
        $query = $this->applyScope(
            $this->filter(AcademicPmcStudentCourseAllocation::with(['student.user', 'subject', 'term']), $filters),
            $user,
            [],
            ['term' => ['id' => 'term'], 'student' => ['program_id' => 'program', 'batch_id' => 'batch']]
        )->latest();

        return [
            ['student', 'subject', 'type', 'approval', 'basket', 'waitlisted', 'term'],
            $query->limit(1000)->get()->map(fn ($row) => [
                $row->student?->user?->name ?? $row->student?->enrollment_number ?? $row->student?->roll_number ?? $row->student?->student_id ?? '',
                $row->subject?->name ?? $row->subject?->code ?? '',
                $row->allocation_type,
                $row->approval_status,
                $row->basket_status,
                $row->waitlisted ? 'yes' : 'no',
                $row->term?->name ?? '',
            ]),
        ];
    }

    private function courseGroupExportRows(User $user, array $filters): array
    {
        $query = $this->applyScope(
            $this->filter(AcademicPmcCourseGroup::with(['program', 'subject', 'owner']), $filters),
            $user,
            ['program_id' => 'program', 'batch_id' => 'batch', 'term_id' => 'term']
        )->latest();

        return [
            ['group', 'type', 'program', 'subject', 'strength', 'status', 'locked'],
            $query->limit(1000)->get()->map(fn ($row) => [
                $row->name,
                $row->group_type,
                $row->program?->code ?? '',
                $row->subject?->name ?? $row->subject?->code ?? '',
                $row->current_strength . '/' . $row->max_capacity,
                $row->status,
                $row->is_locked ? 'yes' : 'no',
            ]),
        ];
    }

    private function timetablePlannerExportRows(User $user, array $filters): array
    {
        $query = $this->applyScope(
            AcademicPmcTimetableGenerationItem::with(['courseGroup.subject', 'teacher.user', 'classroom', 'slot']),
            $user,
            [],
            ['generationRun' => ['program_id' => 'program', 'batch_id' => 'batch', 'term_id' => 'term'], 'courseGroup' => ['program_id' => 'program', 'batch_id' => 'batch', 'term_id' => 'term']]
        )
            ->when($filters['status'] ?? null, fn (Builder $item, string $status) => $item->where('status', $status), fn (Builder $item) => $item->where('status', 'scheduled'))
            ->when($filters['subject_id'] ?? null, fn (Builder $item, string $subjectId) => $item->whereHas('courseGroup', fn (Builder $group) => $group->where('subject_id', $subjectId)))
            ->when($filters['search'] ?? null, function (Builder $item, string $search) {
                $item->where(function (Builder $inner) use ($search) {
                    $inner->whereHas('courseGroup', fn (Builder $group) => $group->where('name', 'like', "%{$search}%")
                        ->orWhereHas('subject', fn (Builder $subject) => $subject->where('name', 'like', "%{$search}%")->orWhere('code', 'like', "%{$search}%")))
                        ->orWhereHas('teacher.user', fn (Builder $teacher) => $teacher->where('name', 'like', "%{$search}%"))
                        ->orWhereHas('classroom', fn (Builder $room) => $room->where('name', 'like', "%{$search}%")->orWhere('room_number', 'like', "%{$search}%"));
                });
            });

        $this->applyTimetableItemSort($query, $filters);

        return [
            ['day', 'slot', 'group', 'subject', 'faculty', 'room', 'status', 'locked', 'confidence'],
            $query->limit(1000)->get()->map(fn ($row) => [
                $row->day_of_week,
                $row->slot?->name ?? $row->timetable_slot_id,
                $row->courseGroup?->name ?? '',
                $row->courseGroup?->subject?->name ?? '',
                $row->teacher?->user?->name ?? '',
                $row->classroom?->name ?? $row->classroom?->room_number ?? '',
                $row->status,
                $row->is_locked ? 'yes' : 'no',
                $row->confidence,
            ]),
        ];
    }

    private function allocationExceptionFlags(array $data, ?AcademicPmcStudentCourseAllocation $allocation): array
    {
        $flags = [];
        $type = $data['exception_type'];
        if ($type === 'drop' && ! $allocation) {
            $flags[] = 'drop_without_existing_allocation';
        }
        if ($type !== 'drop' && $allocation && ! in_array($allocation->basket_status, ['dropped', 'withdrawn'], true)) {
            $flags[] = 'duplicate_existing_allocation';
        }
        if ((int) ($data['credit_delta'] ?? 0) > 6) {
            $flags[] = 'credit_overload';
        }
        if (in_array($type, ['repeat', 'backlog'], true)) {
            $flags[] = 'repeat_backlog_priority';
        }
        if (in_array($type, ['improvement', 'audit'], true)) {
            $flags[] = 'dean_review_required';
        }

        return $flags;
    }

    private function applyCourseAllocationException(User $actor, AcademicPmcCourseAllocationException $exception): void
    {
        if ($exception->exception_type === 'drop') {
            $exception->allocation?->update([
                'approval_status' => 'drop_approved',
                'basket_status' => 'dropped',
                'override_reason' => $exception->decision_note,
                'metadata' => ['exception_id' => $exception->id, 'version' => 'PMC OS v0.050'],
            ]);
            return;
        }

        $enrollment = StudentSubjectEnrollment::firstOrCreate(
            ['student_id' => $exception->student_id, 'subject_id' => $exception->subject_id, 'term_id' => $exception->term_id],
            ['enrollment_type' => in_array($exception->exception_type, ['open_elective', 'audit'], true) ? 'elective' : 'compulsory', 'status' => 'active']
        );

        AcademicPmcStudentCourseAllocation::updateOrCreate(
            ['student_id' => $exception->student_id, 'subject_id' => $exception->subject_id, 'term_id' => $exception->term_id],
            [
                'student_subject_enrollment_id' => $enrollment->id,
                'allocation_type' => $exception->exception_type,
                'allocation_source' => 'exception_' . $exception->exception_type,
                'approval_status' => 'exception_approved',
                'basket_status' => 'approved',
                'waitlisted' => false,
                'override_reason' => $exception->decision_note,
                'validation_flags' => $exception->validation_flags,
                'metadata' => ['exception_id' => $exception->id, 'approved_by' => $actor->id, 'version' => 'PMC OS v0.050'],
            ]
        );
    }

    private function applyCourseGroupAdjustment(User $actor, AcademicPmcCourseGroupAdjustment $adjustment): void
    {
        $group = $adjustment->courseGroup;
        $target = $adjustment->targetCourseGroup;
        if (! $group) {
            return;
        }

        match ($adjustment->adjustment_type) {
            'lock' => $group->update(['is_locked' => true, 'status' => 'locked']),
            'unlock' => $group->update(['is_locked' => false, 'status' => 'ready']),
            'merge' => $this->mergeGroupIntoTarget($group, $target, $adjustment, $actor),
            'move_student' => $this->moveStudentBetweenGroups($group, $target, $adjustment, $actor),
            default => $this->applyStrengthAdjustment($group, $target, $adjustment),
        };
    }

    private function applyStrengthAdjustment(AcademicPmcCourseGroup $group, ?AcademicPmcCourseGroup $target, AcademicPmcCourseGroupAdjustment $adjustment): void
    {
        $group->update(['current_strength' => $adjustment->to_strength, 'status' => $adjustment->adjustment_type === 'split' ? 'rebalanced' : $group->status]);
        if ($target) {
            $target->update(['current_strength' => $adjustment->target_to_strength, 'status' => 'rebalanced']);
        }
    }

    private function mergeGroupIntoTarget(AcademicPmcCourseGroup $group, ?AcademicPmcCourseGroup $target, AcademicPmcCourseGroupAdjustment $adjustment, User $actor): void
    {
        if ($target) {
            AcademicPmcCourseGroupMember::where('course_group_id', $group->id)->get()->each(function (AcademicPmcCourseGroupMember $member) use ($target, $actor, $adjustment) {
                $existing = AcademicPmcCourseGroupMember::where('course_group_id', $target->id)->where('student_id', $member->student_id)->first();
                if ($existing) {
                    $existing->update(['moved_by' => $actor->id, 'move_reason' => $adjustment->decision_note, 'status' => 'active']);
                    $member->delete();
                    return;
                }
                $member->update(['course_group_id' => $target->id, 'moved_by' => $actor->id, 'move_reason' => $adjustment->decision_note]);
            });
            $target->update(['current_strength' => $adjustment->target_to_strength, 'status' => 'rebalanced']);
        }
        $group->update(['current_strength' => 0, 'status' => 'merged', 'is_locked' => true]);
    }

    private function moveStudentBetweenGroups(AcademicPmcCourseGroup $group, ?AcademicPmcCourseGroup $target, AcademicPmcCourseGroupAdjustment $adjustment, User $actor): void
    {
        if (! $target || ! $adjustment->student_id) {
            return;
        }
        $sourceMember = AcademicPmcCourseGroupMember::where('course_group_id', $group->id)->where('student_id', $adjustment->student_id)->first();
        $targetMember = AcademicPmcCourseGroupMember::where('course_group_id', $target->id)->where('student_id', $adjustment->student_id)->first();
        if ($targetMember) {
            $targetMember->update(['moved_by' => $actor->id, 'move_reason' => $adjustment->decision_note, 'status' => 'active']);
            $sourceMember?->delete();
        } else {
            $sourceMember?->update([
                'course_group_id' => $target->id,
                'moved_by' => $actor->id,
                'move_reason' => $adjustment->decision_note,
            ]);
        }
        $group->update(['current_strength' => $adjustment->to_strength]);
        $target->update(['current_strength' => $adjustment->target_to_strength]);
    }

    private function refreshFacultyAcknowledgementPublishCheck(): void
    {
        $open = AcademicPmcFacultyAssignmentAcknowledgement::whereIn('status', ['pending', 'concern_raised'])->count();
        AcademicPmcTimetablePublishCheck::updateOrCreate(
            ['generation_run_id' => null, 'check_type' => 'faculty_acknowledgements'],
            [
                'status' => $open === 0 ? 'pass' : 'warn',
                'severity' => $open === 0 ? 'info' : 'medium',
                'title' => 'Faculty assignment acknowledgements',
                'description' => $open === 0 ? 'Faculty assignment acknowledgements are clear.' : "{$open} faculty acknowledgement(s) are pending or have concerns.",
                'required_role' => 'pmc_head',
                'metadata' => ['version' => 'PMC OS v0.052'],
            ]
        );
    }

    private function createSessionDemands(AcademicPmcTimetableGenerationRun $run, AcademicPmcCourseGroup $group, ?AcademicPmcGroupFacultyAssignment $assignment): Collection
    {
        $constraints = $group->constraints ?: [];
        if (! empty($constraints['session_mix']) && is_array($constraints['session_mix'])) {
            return collect($constraints['session_mix'])->map(function ($mix, $type) use ($run, $group) {
                return AcademicPmcTimetableSessionDemand::create([
                    'generation_run_id' => $run->id,
                    'course_group_id' => $group->id,
                    'session_type' => is_string($type) ? $type : ($mix['type'] ?? 'lecture'),
                    'required_sessions_per_week' => max(1, (int) ($mix['sessions'] ?? 1)),
                    'duration_slots' => max(1, (int) ($mix['duration_slots'] ?? 1)),
                    'source' => 'group_session_mix',
                    'rules' => $mix,
                    'metadata' => ['version' => 'PMC OS v0.062'],
                ]);
            })->values();
        }

        $sessionType = str_contains($group->group_type, 'lab') ? 'lab' : (str_contains($group->group_type, 'tutorial') ? 'tutorial' : 'lecture');
        $duration = $sessionType === 'lab' ? 2 : 1;
        $weeklyHours = (int) ($assignment?->weekly_hours ?: ($constraints['weekly_hours'] ?? $group->subject?->credits ?? 3));
        $sessions = (int) ($constraints['weekly_sessions'] ?? max(1, (int) ceil($weeklyHours / $duration)));

        return collect([AcademicPmcTimetableSessionDemand::create([
            'generation_run_id' => $run->id,
            'course_group_id' => $group->id,
            'session_type' => $sessionType,
            'required_sessions_per_week' => $sessions,
            'duration_slots' => $duration,
            'source' => $assignment?->weekly_hours ? 'faculty_weekly_hours' : 'group_or_subject_defaults',
            'rules' => ['weekly_hours' => $weeklyHours, 'group_type' => $group->group_type, 'subject_credits' => $group->subject?->credits],
            'metadata' => ['version' => 'PMC OS v0.062'],
        ])]);
    }

    private function findFeasiblePlacement(AcademicPmcCourseGroup $group, int $teacherId, Collection $rooms, Collection $slots, ?AcademicPmcFacultyPreference $preference, ?AcademicPmcLockedSlot $locked, AcademicPmcTimetableSessionDemand $demand, int $sessionIndex, array $occupied, string $strategy): ?array
    {
        if ($locked && $sessionIndex === 1) {
            $slot = $slots->firstWhere('id', $locked->timetable_slot_id);
            $room = $locked->classroom_id ? $rooms->firstWhere('id', $locked->classroom_id) : $this->bestRoomForGroup($rooms, $group);
            if ($slot && $room && $this->isPlacementFree($group, $teacherId, $room->id, (int) $locked->day_of_week, $slot->id, $preference, $occupied, $slots, $demand->duration_slots)) {
                return [(int) $locked->day_of_week, $slot, $room, true, 100, ['hard_locked_slot']];
            }
        }

        $days = $this->candidateDays($preference, $sessionIndex, $strategy);
        $candidates = collect();
        foreach ($days as $day) {
            foreach ($slots as $slot) {
                if ($this->isSlotUnavailable($preference?->unavailable_slots ?? [], (int) $day, (int) $slot->id)) {
                    continue;
                }

                foreach ($this->candidateRooms($rooms, $group) as $room) {
                    if ($this->isPlacementFree($group, $teacherId, $room->id, (int) $day, (int) $slot->id, $preference, $occupied, $slots, $demand->duration_slots)) {
                        [$score, $reasons] = $this->scorePlacementCandidate($group, $teacherId, $room, (int) $day, $slot, $preference, $occupied, $slots, $demand, $strategy);
                        $candidates->push([
                            'day' => (int) $day,
                            'slot' => $slot,
                            'room' => $room,
                            'score' => $score,
                            'reasons' => $reasons,
                        ]);
                    }
                }
            }
        }

        $rankedCandidates = $candidates
            ->sort(function ($a, $b) {
                return ($b['score'] <=> $a['score'])
                    ?: ($a['day'] <=> $b['day'])
                    ?: (($a['slot']->sort_order ?? 0) <=> ($b['slot']->sort_order ?? 0))
                    ?: (($a['room']->capacity ?? 0) <=> ($b['room']->capacity ?? 0));
            })
            ->values();

        $best = $rankedCandidates->first();
        $alternatives = $rankedCandidates
            ->skip(1)
            ->take(3)
            ->map(fn ($candidate) => [
                'day' => $candidate['day'],
                'slot_id' => $candidate['slot']->id,
                'slot_name' => $candidate['slot']->name,
                'slot_order' => $candidate['slot']->sort_order,
                'room_id' => $candidate['room']->id,
                'room_name' => $candidate['room']->name,
                'score' => $candidate['score'],
                'reasons' => $candidate['reasons'],
            ])
            ->values()
            ->all();

        return $best ? [$best['day'], $best['slot'], $best['room'], false, $best['score'], $best['reasons'], $alternatives] : null;
    }

    private function scorePlacementCandidate(AcademicPmcCourseGroup $group, int $teacherId, Classroom $room, int $day, TimetableSlot $slot, ?AcademicPmcFacultyPreference $preference, array $occupied, Collection $slots, AcademicPmcTimetableSessionDemand $demand, string $strategy): array
    {
        $teacherDayLoad = $this->occupiedCountOnDay($occupied, 'teacher', $teacherId, $day);
        $groupDayLoad = $this->occupiedCountOnDay($occupied, 'group', $group->id, $day);
        $adjacentGroup = $this->hasAdjacentOccupiedSlot($occupied, 'group', $group->id, $day, (int) $slot->id);
        $preferredSlot = in_array((int) $slot->id, array_map('intval', $preference?->preferred_slots ?: []), true);
        $roomWaste = max(0, (int) ($room->capacity ?? 0) - (int) $group->current_strength);
        $maxDaily = (int) ($preference?->max_classes_per_day ?: 4);
        $maxConsecutive = (int) ($preference?->max_consecutive_classes ?: 3);

        $score = 80;
        $reasons = [];

        $roomFitScore = max(0, 24 - min(24, (int) floor($roomWaste / 5)));
        $facultyBalanceScore = max(0, 24 - ($teacherDayLoad * 8));
        $studentCompactScore = $adjacentGroup ? 24 : max(8, 18 - ($groupDayLoad * 3));
        $preferenceScore = $preferredSlot ? 12 : 0;

        if ($teacherDayLoad + (int) $demand->duration_slots > $maxDaily) {
            $facultyBalanceScore -= 10;
            $reasons[] = 'near_faculty_daily_limit';
        }

        if ($this->wouldCreateConsecutivePressure($occupied, 'teacher', $teacherId, $day, (int) $slot->id, (int) $demand->duration_slots, $maxConsecutive, $slots)) {
            $facultyBalanceScore -= 8;
            $reasons[] = 'consecutive_teaching_pressure';
        }

        if ($adjacentGroup) {
            $reasons[] = 'keeps_student_day_compact';
        }
        if ($preferredSlot) {
            $reasons[] = 'faculty_preferred_slot';
        }
        if ($roomWaste <= 10) {
            $reasons[] = 'room_capacity_close_fit';
        }

        $score += match ($strategy) {
            'student_compact' => ($studentCompactScore * 2) + (int) round($facultyBalanceScore / 2) + (int) round($roomFitScore / 3) + $preferenceScore,
            'faculty_balanced' => ($facultyBalanceScore * 2) + (int) round($studentCompactScore / 2) + (int) round($roomFitScore / 3) + $preferenceScore,
            'adjunct_priority' => $preferenceScore + ($facultyBalanceScore * 2) + (int) round($studentCompactScore / 2) + (int) round($roomFitScore / 3),
            'room_optimized' => ($roomFitScore * 2) + $facultyBalanceScore + (int) round($studentCompactScore / 2) + $preferenceScore,
            default => $studentCompactScore + $facultyBalanceScore + $roomFitScore + $preferenceScore,
        };

        $reasons[] = 'strategy_' . ($strategy ?: 'balanced');
        $reasons[] = 'teacher_day_load_' . $teacherDayLoad;
        $reasons[] = 'group_day_load_' . $groupDayLoad;

        return [max(1, min(100, (int) round($score / 2))), array_values(array_unique($reasons))];
    }

    private function candidateDays(?AcademicPmcFacultyPreference $preference, int $sessionIndex, string $strategy): array
    {
        $days = array_values(array_map('intval', $preference?->available_days ?: range(1, 6)));
        if ($strategy === 'adjunct_priority') {
            return $days;
        }

        $offset = ($sessionIndex - 1) % max(count($days), 1);
        return array_values(array_unique(array_merge(array_slice($days, $offset), array_slice($days, 0, $offset))));
    }

    private function candidateRooms(Collection $rooms, AcademicPmcCourseGroup $group): Collection
    {
        $requiresLab = str_contains($group->group_type, 'lab');
        return $rooms
            ->filter(fn ($room) => ($room->capacity ?? 0) >= $group->current_strength)
            ->when($requiresLab, fn ($collection) => $collection->filter(fn ($room) => $room->has_lab || $room->type === 'lab'))
            ->sortBy('capacity')
            ->values();
    }

    private function isPlacementFree(AcademicPmcCourseGroup $group, int $teacherId, int $roomId, int $day, int $slotId, ?AcademicPmcFacultyPreference $preference, array $occupied, Collection $slots, int $durationSlots = 1): bool
    {
        if (! empty($preference?->available_days) && ! in_array($day, array_map('intval', $preference->available_days), true)) {
            return false;
        }

        $blockSlotIds = $this->blockSlotIds($slots, $slotId, $durationSlots);
        if (count($blockSlotIds) < $durationSlots) {
            return false;
        }

        foreach ($blockSlotIds as $blockSlotId) {
            if ($this->isSlotUnavailable($preference?->unavailable_slots ?? [], $day, $blockSlotId)) {
                return false;
            }

            if ($this->placementBlockedByHardLock($group, $teacherId, $roomId, $day, $blockSlotId)) {
                return false;
            }

            $key = $day . '-' . $blockSlotId;
            if (isset($occupied['teacher'][$teacherId][$key]) || isset($occupied['room'][$roomId][$key]) || isset($occupied['group'][$group->id][$key])) {
                return false;
            }

            foreach ($group->members as $member) {
                if (isset($occupied['student'][$member->student_id][$key])) {
                    return false;
                }
            }
        }

        return true;
    }

    private function placementFailureDiagnostics(AcademicPmcCourseGroup $group, int $teacherId, Collection $rooms, Collection $slots, ?AcademicPmcFacultyPreference $preference, array $occupied, int $durationSlots, string $strategy): array
    {
        $candidateDays = $this->candidateDays($preference, 1, $strategy);
        $candidateRooms = $this->candidateRooms($rooms, $group);
        $blockers = [
            'no_candidate_days' => count($candidateDays) === 0 ? 1 : 0,
            'no_candidate_rooms' => $candidateRooms->isEmpty() ? 1 : 0,
            'incomplete_multi_slot_block' => 0,
            'faculty_unavailable' => 0,
            'hard_lock_blocked' => 0,
            'occupied_resource_or_student' => 0,
        ];
        $sampledCandidates = [];

        foreach ($candidateDays as $day) {
            foreach ($slots as $slot) {
                $slotId = (int) $slot->id;
                $blockSlotIds = $this->blockSlotIds($slots, $slotId, $durationSlots);

                if (count($blockSlotIds) < $durationSlots) {
                    $blockers['incomplete_multi_slot_block']++;
                    $sampledCandidates[] = ['day' => (int) $day, 'slot_id' => $slotId, 'reason' => 'incomplete_multi_slot_block'];
                    continue;
                }

                $facultyUnavailable = collect($blockSlotIds)->contains(fn (int $blockSlotId): bool =>
                    $this->isSlotUnavailable($preference?->unavailable_slots ?? [], (int) $day, $blockSlotId)
                );
                if ($facultyUnavailable) {
                    $blockers['faculty_unavailable']++;
                    $sampledCandidates[] = ['day' => (int) $day, 'slot_id' => $slotId, 'reason' => 'faculty_unavailable'];
                    continue;
                }

                foreach ($candidateRooms as $room) {
                    $hardLocked = false;
                    $occupiedConflict = false;

                    foreach ($blockSlotIds as $blockSlotId) {
                        if ($this->placementBlockedByHardLock($group, $teacherId, (int) $room->id, (int) $day, $blockSlotId)) {
                            $hardLocked = true;
                            break;
                        }

                        $key = $day . '-' . $blockSlotId;
                        if (isset($occupied['teacher'][$teacherId][$key]) || isset($occupied['room'][$room->id][$key]) || isset($occupied['group'][$group->id][$key])) {
                            $occupiedConflict = true;
                            break;
                        }

                        foreach ($group->members as $member) {
                            if (isset($occupied['student'][$member->student_id][$key])) {
                                $occupiedConflict = true;
                                break 2;
                            }
                        }
                    }

                    if ($hardLocked) {
                        $blockers['hard_lock_blocked']++;
                        $sampledCandidates[] = ['day' => (int) $day, 'slot_id' => $slotId, 'room_id' => (int) $room->id, 'reason' => 'hard_lock_blocked'];
                        continue;
                    }

                    if ($occupiedConflict) {
                        $blockers['occupied_resource_or_student']++;
                        $sampledCandidates[] = ['day' => (int) $day, 'slot_id' => $slotId, 'room_id' => (int) $room->id, 'reason' => 'occupied_resource_or_student'];
                    }
                }
            }
        }

        $activeBlockers = collect($blockers)->filter(fn (int $count): bool => $count > 0)->sortDesc();
        $primary = (string) ($activeBlockers->keys()->first() ?: 'no_feasible_candidate');

        return [
            'summary' => 'No feasible slot found. Primary blocker: ' . str_replace('_', ' ', $primary) . '.',
            'primary_blocker' => $primary,
            'blockers' => $activeBlockers->keys()->values()->all(),
            'blocker_counts' => $blockers,
            'candidate_days' => array_values($candidateDays),
            'candidate_rooms' => $candidateRooms->pluck('id')->map(fn ($id) => (int) $id)->values()->all(),
            'sampled_blocked_candidates' => collect($sampledCandidates)->take(8)->values()->all(),
            'recommended_actions' => $this->recommendedActionsForPlacementBlockers($primary),
        ];
    }

    private function recommendedActionsForPlacementBlockers(string $primaryBlocker): array
    {
        return match ($primaryBlocker) {
            'no_candidate_rooms' => ['Add or activate a suitable room/lab, increase room capacity, or split the course group.'],
            'faculty_unavailable' => ['Adjust faculty availability, choose another faculty member, or approve a formal exception.'],
            'hard_lock_blocked' => ['Review hard locked slots for the batch, room, teacher, or group and move/remove the lock if appropriate.'],
            'incomplete_multi_slot_block' => ['Move the session to an earlier contiguous slot or configure more active non-break teaching slots.'],
            'occupied_resource_or_student' => ['Move another session, change room/faculty, or split overlapping student groups.'],
            'missing_primary_faculty' => ['Assign a primary faculty member to this course group.'],
            default => ['Review faculty, room, group, availability, and locked-slot constraints for this demand.'],
        };
    }

    private function placementBlockedByHardLock(AcademicPmcCourseGroup $group, int $teacherId, int $roomId, int $day, int $slotId): bool
    {
        $members = $group->relationLoaded('members')
            ? $group->members->where('status', 'active')->pluck('student_id')->filter()->unique()
            : $group->members()->where('status', 'active')->pluck('student_id')->filter()->unique();

        $locks = AcademicPmcLockedSlot::with('courseGroup.members')
            ->where('status', 'active')
            ->where('is_hard_lock', true)
            ->where('day_of_week', $day)
            ->where('timetable_slot_id', $slotId)
            ->where(fn ($query) => $query->where('term_id', $group->term_id)->orWhereNull('term_id'))
            ->get();

        foreach ($locks as $lock) {
            if ((int) $lock->course_group_id === (int) $group->id) {
                if ($lock->teacher_id && (int) $lock->teacher_id !== $teacherId) {
                    return true;
                }

                if ($lock->classroom_id && (int) $lock->classroom_id !== $roomId) {
                    return true;
                }

                continue;
            }

            if ($lock->teacher_id && (int) $lock->teacher_id === $teacherId) {
                return true;
            }

            if ($lock->classroom_id && (int) $lock->classroom_id === $roomId) {
                return true;
            }

            if ($lock->course_group_id && $this->hardLockGroupMembersOverlap($lock, $members)) {
                return true;
            }

            if (! $lock->course_group_id && $this->hardLockScopeBlocksGroup($lock, $group)) {
                return true;
            }
        }

        return false;
    }

    private function hardLockGroupMembersOverlap(AcademicPmcLockedSlot $lock, Collection $candidateMembers): bool
    {
        if ($candidateMembers->isEmpty()) {
            return false;
        }

        $lockedMembers = $lock->courseGroup?->members
            ? $lock->courseGroup->members->where('status', 'active')->pluck('student_id')->filter()->unique()
            : collect();

        return $lockedMembers->isNotEmpty() && $candidateMembers->intersect($lockedMembers)->isNotEmpty();
    }

    private function hardLockScopeBlocksGroup(AcademicPmcLockedSlot $lock, AcademicPmcCourseGroup $group): bool
    {
        if ($lock->batch_id && (int) $lock->batch_id === (int) $group->batch_id) {
            return true;
        }

        if (! $lock->batch_id && $lock->program_id && (int) $lock->program_id === (int) $group->program_id) {
            return true;
        }

        return ! $lock->program_id
            && ! $lock->batch_id
            && ! $lock->teacher_id
            && ! $lock->classroom_id;
    }

    private function occupiedCountOnDay(array $occupied, string $type, int $id, int $day): int
    {
        return collect($occupied[$type][$id] ?? [])
            ->keys()
            ->filter(fn ($key) => str_starts_with((string) $key, $day . '-'))
            ->count();
    }

    private function hasAdjacentOccupiedSlot(array $occupied, string $type, int $id, int $day, int $slotId): bool
    {
        $slotOrders = TimetableSlot::where('is_active', true)->pluck('sort_order', 'id');
        $targetOrder = $slotOrders[$slotId] ?? null;
        if ($targetOrder === null) {
            return false;
        }

        foreach (array_keys($occupied[$type][$id] ?? []) as $key) {
            [$occupiedDay, $occupiedSlotId] = array_map('intval', explode('-', (string) $key) + [0, 0]);
            if ($occupiedDay !== $day) {
                continue;
            }

            $occupiedOrder = $slotOrders[$occupiedSlotId] ?? null;
            if ($occupiedOrder !== null && abs((int) $occupiedOrder - (int) $targetOrder) === 1) {
                return true;
            }
        }

        return false;
    }

    private function wouldCreateConsecutivePressure(array $occupied, string $type, int $id, int $day, int $slotId, int $durationSlots, int $maxConsecutive, Collection $slots): bool
    {
        $slotOrders = TimetableSlot::where('is_active', true)->pluck('sort_order', 'id');
        $orders = collect();

        foreach (array_keys($occupied[$type][$id] ?? []) as $key) {
            [$occupiedDay, $occupiedSlotId] = array_map('intval', explode('-', (string) $key) + [0, 0]);
            if ($occupiedDay === $day && isset($slotOrders[$occupiedSlotId])) {
                $orders->push((int) $slotOrders[$occupiedSlotId]);
            }
        }

        foreach ($this->blockSlotIds($slots, $slotId, $durationSlots) as $blockSlotId) {
            if (isset($slotOrders[$blockSlotId])) {
                $orders->push((int) $slotOrders[$blockSlotId]);
            }
        }

        $orders = $orders->unique()->sort()->values();
        $current = 0;
        $previous = null;
        foreach ($orders as $order) {
            $current = $previous !== null && ((int) $order === ((int) $previous + 1)) ? $current + 1 : 1;
            if ($current > $maxConsecutive) {
                return true;
            }
            $previous = $order;
        }

        return false;
    }

    private function markPlacementOccupied(array &$occupied, AcademicPmcTimetableGenerationItem $item, AcademicPmcCourseGroup $group): void
    {
        $slots = TimetableSlot::where('is_active', true)->where('is_break', false)->orderBy('sort_order')->get();
        foreach ($this->blockSlotIds($slots, (int) $item->timetable_slot_id, (int) $item->duration_slots) as $slotId) {
            $key = $item->day_of_week . '-' . $slotId;
            if ($item->teacher_id) {
                $occupied['teacher'][$item->teacher_id][$key] = true;
            }
            if ($item->classroom_id) {
                $occupied['room'][$item->classroom_id][$key] = true;
            }
            if ($item->course_group_id) {
                $occupied['group'][$item->course_group_id][$key] = true;
            }
            foreach ($group->members as $member) {
                $occupied['student'][$member->student_id][$key] = true;
            }
        }
    }

    private function resourceConflictBuckets(Collection $items): array
    {
        $activeSlots = TimetableSlot::where('is_active', true)->where('is_break', false)->orderBy('sort_order')->get();
        $buckets = [
            'faculty_clash' => [
                'type' => 'faculty_clash',
                'label' => 'Faculty',
                'affected_type' => 'teacher',
                'fix' => 'Move one class, change faculty, or approve a formal substitution.',
                'items' => [],
            ],
            'room_clash' => [
                'type' => 'room_clash',
                'label' => 'Room',
                'affected_type' => 'classroom',
                'fix' => 'Move one class to a different room or slot.',
                'items' => [],
            ],
            'group_clash' => [
                'type' => 'group_clash',
                'label' => 'Course group',
                'affected_type' => 'course_group',
                'fix' => 'Move one session for this section/group to a different slot.',
                'items' => [],
            ],
            'student_clash' => [
                'type' => 'student_clash',
                'label' => 'Student',
                'affected_type' => 'student',
                'fix' => 'Move one elective/core group to a different slot.',
                'items' => [],
            ],
        ];

        $scheduled = $items->where('status', 'scheduled');
        $membersByGroup = AcademicPmcCourseGroupMember::whereIn('course_group_id', $scheduled->pluck('course_group_id')->filter()->unique())
            ->where('status', 'active')
            ->get()
            ->groupBy('course_group_id');

        foreach ($scheduled as $item) {
            if (! $item->day_of_week || ! $item->timetable_slot_id) {
                continue;
            }

            $blockSlotIds = $this->blockSlotIds($activeSlots, (int) $item->timetable_slot_id, max(1, (int) ($item->duration_slots ?? 1)));
            if (empty($blockSlotIds)) {
                $blockSlotIds = [(int) $item->timetable_slot_id];
            }

            foreach ($blockSlotIds as $slotId) {
                $key = (int) $item->day_of_week . '-' . (int) $slotId;

                if ($item->teacher_id) {
                    $buckets['faculty_clash']['items'][$key][$item->teacher_id][] = $item->id;
                }
                if ($item->classroom_id) {
                    $buckets['room_clash']['items'][$key][$item->classroom_id][] = $item->id;
                }
                if ($item->course_group_id) {
                    $buckets['group_clash']['items'][$key][$item->course_group_id][] = $item->id;
                }

                foreach ($membersByGroup->get($item->course_group_id, collect()) as $member) {
                    $buckets['student_clash']['items'][$key][$member->student_id][] = $item->id;
                }
            }
        }

        return collect($buckets)
            ->map(function (array $bucket) {
                return collect($bucket['items'])->map(function (array $resourceItems, string $key) use ($bucket) {
                    [$day, $slotId] = array_map('intval', explode('-', $key));
                    $duplicates = collect($resourceItems)
                        ->filter(fn (array $itemIds) => count(array_unique($itemIds)) > 1)
                        ->all();

                    return [
                        'type' => $bucket['type'],
                        'label' => $bucket['label'],
                        'affected_type' => $bucket['affected_type'],
                        'fix' => $bucket['fix'],
                        'day' => $day,
                        'slot_id' => $slotId,
                        'duplicates' => $duplicates,
                    ];
                })->values();
            })
            ->collapse()
            ->filter(fn (array $bucket) => ! empty($bucket['duplicates']))
            ->values()
            ->all();
    }

    private function blockSlotIds(Collection $slots, int $startSlotId, int $durationSlots = 1): array
    {
        $ordered = TimetableSlot::where('is_active', true)->orderBy('sort_order')->get()->values();
        $startIndex = $ordered->search(fn ($slot) => (int) $slot->id === (int) $startSlotId);
        if ($startIndex === false || $durationSlots < 1) {
            return [];
        }

        $block = $ordered->slice($startIndex, $durationSlots)->values();
        if ($block->count() < $durationSlots || $block->contains(fn ($slot) => (bool) $slot->is_break)) {
            return [];
        }

        return $block->pluck('id')->map(fn ($id) => (int) $id)->all();
    }

    private function nextFeasibleSlot(Collection $slots, ?AcademicPmcFacultyPreference $preference, int $fallbackDay, int $slotIndex): array
    {
        $slot = $slots->get($slotIndex % max($slots->count(), 1));
        $availableDays = array_map('intval', $preference?->available_days ?: range(1, 6));
        $day = $availableDays[$slotIndex % max(count($availableDays), 1)] ?? $fallbackDay;

        $unavailable = collect($preference?->unavailable_slots ?: []);
        if ($slot && $unavailable->contains(fn ($blocked) => (int) ($blocked['day'] ?? 0) === (int) $day && (int) ($blocked['slot_id'] ?? 0) === (int) $slot->id)) {
            foreach ($slots as $candidate) {
                foreach ($availableDays as $candidateDay) {
                    if (! $unavailable->contains(fn ($blocked) => (int) ($blocked['day'] ?? 0) === (int) $candidateDay && (int) ($blocked['slot_id'] ?? 0) === (int) $candidate->id)) {
                        return [$candidateDay, $candidate];
                    }
                }
            }
        }

        return [$day, $slot];
    }

    private function bestRoomForGroup(Collection $rooms, AcademicPmcCourseGroup $group): ?Classroom
    {
        $requiresLab = str_contains($group->group_type, 'lab');
        return $rooms
            ->filter(fn ($room) => ($room->capacity ?? 0) >= $group->current_strength)
            ->when($requiresLab, fn ($collection) => $collection->filter(fn ($room) => $room->has_lab || $room->type === 'lab'))
            ->sortBy('capacity')
            ->first()
            ?: $rooms->sortBy('capacity')->first();
    }

    private function constraint(AcademicPmcTimetableGenerationRun $run, string $type, string $severity, string $title, string $description, ?string $affectedType, ?string $affectedKey, string $fix): AcademicPmcTimetableConstraint
    {
        return AcademicPmcTimetableConstraint::create([
            'generation_run_id' => $run->id,
            'constraint_type' => $type,
            'severity' => $severity,
            'title' => $title,
            'description' => $description,
            'affected_type' => $affectedType,
            'affected_key' => $affectedKey,
            'recommended_fix' => $fix,
            'source_route' => route('academics.pmc.timetable-planner.index'),
        ]);
    }

    private function studentCompactnessScore(Collection $items): int
    {
        $score = 100;
        foreach ($items->where('status', 'scheduled')->groupBy(fn ($item) => $item->course_group_id . '-' . $item->day_of_week) as $dayItems) {
            $orders = $this->expandedSlotOrdersForItems($dayItems);
            if ($orders->count() <= 1) {
                continue;
            }
            $gaps = 0;
            for ($i = 1; $i < $orders->count(); $i++) {
                if (($orders[$i] - $orders[$i - 1]) > 1) {
                    $gaps++;
                }
            }
            $score -= $gaps * 5;
        }

        return max(40, min(100, $score));
    }

    private function dayGapCount(Collection $items): int
    {
        $orders = $this->expandedSlotOrdersForItems($items);
        if ($orders->count() <= 1) {
            return 0;
        }

        $gaps = 0;
        for ($i = 1; $i < $orders->count(); $i++) {
            if (($orders[$i] - $orders[$i - 1]) > 1) {
                $gaps += max(1, (int) ($orders[$i] - $orders[$i - 1] - 1));
            }
        }

        return $gaps;
    }

    private function expandedSlotOrdersForItems(Collection $items): Collection
    {
        $slotOrders = TimetableSlot::where('is_active', true)->pluck('sort_order', 'id');
        $activeSlots = TimetableSlot::where('is_active', true)->orderBy('sort_order')->get();

        return $items
            ->flatMap(function ($item) use ($slotOrders, $activeSlots) {
                if (! $item->timetable_slot_id) {
                    return [];
                }

                $block = $this->blockSlotIds($activeSlots, (int) $item->timetable_slot_id, max(1, (int) ($item->duration_slots ?? 1)));
                if (empty($block)) {
                    $block = [(int) $item->timetable_slot_id];
                }

                return collect($block)
                    ->map(fn ($slotId) => $slotOrders[$slotId] ?? null)
                    ->filter(fn ($order) => $order !== null);
            })
            ->unique()
            ->sort()
            ->values();
    }

    private function roomUtilizationScore(Collection $items): int
    {
        $scheduled = $items->where('status', 'scheduled')->filter(fn ($item) => $item->classroom_id && $item->courseGroup);
        if ($scheduled->isEmpty()) {
            return 40;
        }

        $ratios = $scheduled->map(function ($item) {
            $capacity = max(1, (int) ($item->classroom?->capacity ?? 1));
            $strength = max(1, (int) ($item->courseGroup?->current_strength ?? 1));
            return min(1, $strength / $capacity);
        });

        return max(40, min(100, (int) round($ratios->avg() * 100)));
    }

    private function maxConsecutiveForItems(Collection $items): int
    {
        $max = 0;
        foreach ($items->groupBy('day_of_week') as $dayItems) {
            $slots = $this->expandedSlotOrdersForItems($dayItems);
            $current = 0;
            $previous = null;
            foreach ($slots as $slot) {
                $current = $previous !== null && ((int) $slot === ((int) $previous + 1)) ? $current + 1 : 1;
                $max = max($max, $current);
                $previous = $slot;
            }
        }

        return $max;
    }

    private function audit(User $actor, string $action, string $description, mixed $subject = null, array $metadata = []): void
    {
        DepartmentActivityLog::create([
            'department_id' => Department::where('code', 'ACAD')->value('id') ?: Department::query()->value('id'),
            'actor_user_id' => $actor->id,
            'action' => $action,
            'subject_type' => $subject ? get_class($subject) : null,
            'subject_id' => $subject?->id,
            'description' => $description,
            'metadata' => $metadata + ['version' => 'PMC OS v0.041'],
        ]);
    }

    private function authorizeDeanLifecycle(User $actor, string $action): void
    {
        if (! $actor->hasAnyRole(['admin', 'director', 'academic_department_owner', 'dean_academics'])) {
            throw new AuthorizationException("Only Dean/Academic leadership can {$action} timetable versions.");
        }
    }

    private function versionImpactSummary(TimetableVersion $version): array
    {
        return [
            'program_id' => $version->program_id,
            'batch_id' => $version->batch_id,
            'term_id' => $version->term_id,
            'affected_groups' => AcademicPmcCourseGroup::where('program_id', $version->program_id)->where('term_id', $version->term_id)->count(),
            'affected_students' => AcademicPmcCourseGroupMember::whereHas('courseGroup', fn ($q) => $q->where('program_id', $version->program_id)->where('term_id', $version->term_id))->distinct('student_id')->count('student_id'),
            'affected_faculty' => AcademicPmcGroupFacultyAssignment::whereHas('courseGroup', fn ($q) => $q->where('program_id', $version->program_id)->where('term_id', $version->term_id))->distinct('teacher_id')->count('teacher_id'),
        ];
    }

    private function logLifecycleNotification(TimetableVersion $version, string $type, string $title, string $recipientType, array $metadata = []): void
    {
        AcademicPmcTimetableNotification::create([
            'notification_type' => $type,
            'recipient_type' => $recipientType,
            'title' => $title,
            'message' => 'Program timetable version #' . $version->version_number . ' is now ' . $type . '.',
            'status' => 'queued',
            'source_type' => 'timetable_version',
            'source_key' => (string) $version->id,
            'metadata' => $metadata,
        ]);
    }

    private function defaultResolutionActionType(AcademicPmcTimetableConstraint $constraint): string
    {
        return match ($constraint->constraint_type) {
            'faculty_clash', 'faculty_day_unavailable', 'faculty_slot_unavailable' => 'change_faculty_or_slot',
            'room_clash', 'room_capacity_mismatch', 'room_type_mismatch' => 'change_room',
            'student_clash' => 'move_group_slot',
            'unscheduled_class' => 'schedule_missing_class',
            default => 'manual_resolution',
        };
    }

    private function csvInts(mixed $value): array
    {
        if (is_array($value)) {
            return array_values(array_filter(array_map('intval', $value)));
        }

        return array_values(array_filter(array_map('intval', explode(',', (string) $value))));
    }

    private function slotPairs(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }

        return collect(explode(',', (string) $value))
            ->map(function ($pair) {
                [$day, $slot] = array_pad(explode(':', trim($pair)), 2, null);
                return ['day' => (int) $day, 'slot_id' => (int) $slot];
            })
            ->filter(fn ($pair) => $pair['day'] > 0 && $pair['slot_id'] > 0)
            ->values()
            ->all();
    }
}
