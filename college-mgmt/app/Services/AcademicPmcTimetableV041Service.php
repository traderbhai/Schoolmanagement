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
    public function __construct(
        private AcademicPmcAccessPolicyService $policy,
        private PmcTimetableReadModelService $readModels,
        private PmcTimetableDashboardReadModelService $dashboardReadModel,
        private PmcTimetableDataReconciliationService $dataReconciliationService,
        private PmcTimetableStudentPortalService $studentPortalService,
        private PmcTimetableBridgeSyncService $bridgeSync,
        private PmcTimetablePublishService $publishService,
        private PmcTimetableRevisionService $revisionService,
        private PmcTimetableReadinessGateService $readinessGate,
        private PmcTimetableGenerationService $generationService,
    ) {}

    public function dashboard(User $user): array
    {
        return $this->dashboardReadModel->dashboard($user, [
            'readiness' => $this->readinessChecklist($user),
            'launch_control' => $this->launchControl($user),
            'basket' => $this->courseBasketDiagnostics($user),
            'allocation_pressure' => $this->allocationPressureDiagnostics($user),
            'group' => $this->courseGroupDiagnostics($user),
            'faculty' => $this->facultyAllocationDiagnostics($user),
            'faculty_suitability' => $this->facultySuitabilityDiagnostics(null, $user),
            'readiness_input' => $this->readinessInputDiagnostics($user),
            'generation' => $this->generationValidationDiagnostics($user),
            'publish_readiness' => $this->publishFreezeReadinessDiagnostics($user),
            'substitution_emergency' => $this->substitutionEmergencyDiagnostics($user),
        ]);
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
        return $this->studentPortalService->studentScopedTimetable($user, $filters);
    }

    public function studentCourseBasketSelfService(User $user, array $filters = []): array
    {
        return $this->studentPortalService->studentCourseBasketSelfService($user, $filters);
    }

    public function submitStudentBasketAcknowledgement(User $user, array $data): AcademicPmcStudentBasketAcknowledgement
    {
        return $this->studentPortalService->submitStudentBasketAcknowledgement(
            $user,
            $data,
            fn (User $auditActor, string $action, string $description, mixed $subject = null, array $metadata = []) => $this->audit($auditActor, $action, $description, $subject, $metadata)
        );
    }

    public function studentElectiveChoicePortal(User $user, array $filters = []): array
    {
        return $this->studentPortalService->studentElectiveChoicePortal($user, $filters);
    }

    public function submitStudentElectiveChoices(User $user, array $data): void
    {
        $this->studentPortalService->submitStudentElectiveChoices(
            $user,
            $data,
            fn (User $auditActor, string $action, string $description, mixed $subject = null, array $metadata = []) => $this->audit($auditActor, $action, $description, $subject, $metadata)
        );
    }

    public function reviewStudentBasketAcknowledgement(User $actor, AcademicPmcStudentBasketAcknowledgement $ack, string $status, ?string $note): AcademicPmcStudentBasketAcknowledgement
    {
        return $this->studentPortalService->reviewStudentBasketAcknowledgement(
            $actor,
            $ack,
            $status,
            $note,
            fn (User $auditActor, string $action, string $description, mixed $subject = null, array $metadata = []) => $this->audit($auditActor, $action, $description, $subject, $metadata)
        );
    }
    public function refreshDataReconciliation(User $actor): array
    {
        return $this->dataReconciliationService->refreshDataReconciliation(
            $actor,
            fn (User $auditActor, string $action, string $description, mixed $subject = null, array $metadata = []) => $this->audit($auditActor, $action, $description, $subject, $metadata)
        );
    }

    public function dataReconciliationSurface(User $user, array $filters = []): array
    {
        return $this->dataReconciliationService->dataReconciliationSurface($user, $filters);
    }

    public function repairDataReconciliation(User $actor, AcademicPmcDataReconciliationCheck $check): array
    {
        return $this->dataReconciliationService->repairDataReconciliation(
            $actor,
            $check,
            fn (User $auditActor, string $action, string $description, mixed $subject = null, array $metadata = []) => $this->audit($auditActor, $action, $description, $subject, $metadata)
        );
    }

    public function exportDataReconciliation(User $actor, array $filters = []): StreamedResponse
    {
        return $this->dataReconciliationService->exportDataReconciliation($actor, $filters);
    }

    public function exportDataReconciliationRuns(User $actor, array $filters = []): StreamedResponse
    {
        return $this->dataReconciliationService->exportDataReconciliationRuns($actor, $filters);
    }

    public function exportDataReconciliationAudit(User $actor, array $filters = []): StreamedResponse
    {
        return $this->dataReconciliationService->exportDataReconciliationAudit($actor, $filters);
    }
    public function facultyScopedTimetable(User $user, array $filters = []): array
    {
        return $this->readModels->facultyScopedTimetable($user, $filters);
    }

    public function officialTimetableAudience(User $user, array $filters = []): array
    {
        return $this->readModels->officialTimetableAudience($user, $filters);
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
        return $this->readModels->selectorOptions();
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
        return $this->generationService->generate(
            $actor,
            $data,
            fn (AcademicPmcTimetableGenerationRun $run) => $this->refreshConstraintsAndQuality($run),
            fn (User $auditActor, string $action, string $description, mixed $subject = null, array $metadata = []) => $this->audit($auditActor, $action, $description, $subject, $metadata)
        );
    }

    public function refreshConstraintsAndQuality(AcademicPmcTimetableGenerationRun $run): AcademicPmcTimetableQualityScore
    {
        return $this->generationService->refreshConstraintsAndQuality(
            $run,
            fn (AcademicPmcTimetableGenerationRun $checkedRun, int $hard, int $soft, int $score) => $this->refreshPublishChecks($checkedRun, $hard, $soft, $score)
        );
    }

    public function refreshPublishChecks(AcademicPmcTimetableGenerationRun $run, int $hard, int $soft, int $score): void
    {
        $this->generationService->refreshPublishChecks($run, $hard, $soft, $score, $this->facultySuitabilityDiagnostics($run));
    }

    private function syncFacultySuitabilityPublishCheck(?AcademicPmcTimetableGenerationRun $run = null): void
    {
        if (! $run) {
            return;
        }

        $this->generationService->syncFacultySuitabilityPublishCheck($run, $this->facultySuitabilityDiagnostics($run));
    }

    public function applySolverAlternative(User $actor, AcademicPmcTimetableGenerationItem $item, int $alternativeIndex, ?string $decisionNote = null, bool $allowHardConflictOverride = false, ?string $overrideReason = null): AcademicPmcTimetableGenerationItem
    {
        return $this->generationService->applySolverAlternative(
            $actor,
            $item,
            $alternativeIndex,
            $decisionNote,
            $allowHardConflictOverride,
            $overrideReason,
            fn (AcademicPmcTimetableGenerationRun $run) => $this->refreshConstraintsAndQuality($run),
            fn (User $auditActor, string $action, string $description, mixed $subject = null, array $metadata = []) => $this->audit($auditActor, $action, $description, $subject, $metadata)
        );
    }

    public function moveGeneratedItem(User $actor, AcademicPmcTimetableGenerationItem $item, array $data, bool $allowHardConflictOverride = false, ?string $overrideReason = null): AcademicPmcTimetableGenerationItem
    {
        return $this->generationService->moveGeneratedItem(
            $actor,
            $item,
            $data,
            $allowHardConflictOverride,
            $overrideReason,
            fn (AcademicPmcTimetableGenerationRun $run) => $this->refreshConstraintsAndQuality($run),
            fn (User $auditActor, string $action, string $description, mixed $subject = null, array $metadata = []) => $this->audit($auditActor, $action, $description, $subject, $metadata)
        );
    }

    public function refreshGenerationImpactPreview(User $actor, AcademicPmcTimetableGenerationRun $run): Collection
    {
        return $this->publishService->refreshGenerationImpactPreview($actor, $run);
    }

    public function publishRun(User $actor, AcademicPmcTimetableGenerationRun $run, array $data): TimetableVersion
    {
        return $this->publishService->publishRun(
            $actor,
            $run,
            $data,
            fn (AcademicPmcTimetableGenerationRun $qualityRun) => $this->refreshConstraintsAndQuality($qualityRun)
        );
    }

    public function freezeVersion(User $actor, TimetableVersion $version, array $data): AcademicPmcTimetableVersionWorkflow
    {
        return $this->publishService->freezeVersion($actor, $version, $data);
    }

    public function unfreezeVersion(User $actor, TimetableVersion $version, array $data): AcademicPmcTimetableVersionWorkflow
    {
        return $this->publishService->unfreezeVersion($actor, $version, $data);
    }

    public function rollbackVersion(User $actor, TimetableVersion $version, array $data): TimetableVersion
    {
        return $this->publishService->rollbackVersion($actor, $version, $data);
    }

    private function archiveOperationalVersion(TimetableVersion $version): void
    {
        $this->publishService->archiveOperationalVersion($version);
    }

    private function officialPublishedVersionIds(): array
    {
        return $this->readModels->officialPublishedVersionIds();
    }

    private function officialPublishedGenerationRunIds(): array
    {
        return $this->readModels->officialPublishedGenerationRunIds();
    }

    private function officialTimetableItemsQuery(): Builder
    {
        return $this->readModels->officialTimetableItemsQuery();
    }

    private function parallelSlotGroups(Collection $items): Collection
    {
        return $this->readModels->parallelSlotGroups($items);
    }

    private function markRunItemsOfficial(AcademicPmcTimetableGenerationRun $run, TimetableVersion $version, User $actor): void
    {
        $this->bridgeSync->markRunItemsOfficial($run, $version, $actor);
    }

    private function syncRunToOperationalTimetable(AcademicPmcTimetableGenerationRun $run, TimetableVersion $version, User $actor): int
    {
        return $this->bridgeSync->syncRunToOperationalTimetable($run, $version, $actor);
    }

    public function createResolutionAction(User $actor, AcademicPmcTimetableConstraint $constraint, array $data): AcademicPmcTimetableResolutionAction
    {
        return $this->revisionService->createResolutionAction($actor, $constraint, $data);
    }

    public function closeResolutionAction(User $actor, AcademicPmcTimetableResolutionAction $action, array $data): AcademicPmcTimetableResolutionAction
    {
        return $this->revisionService->closeResolutionAction($actor, $action, $data);
    }

    public function requestChange(User $actor, array $data): AcademicPmcTimetableChangeRequest
    {
        return $this->revisionService->requestChange($actor, $data);
    }

    public function decideChange(User $actor, AcademicPmcTimetableChangeRequest $change, string $status, ?string $note): AcademicPmcTimetableChangeRequest
    {
        return $this->revisionService->decideChange($actor, $change, $status, $note);
    }

    public function recommendSubstitution(User $actor, array $data): AcademicPmcSubstitutionRecommendation
    {
        return $this->revisionService->recommendSubstitution($actor, $data);
    }

    public function logNotification(User $actor, array $data): AcademicPmcTimetableNotification
    {
        return $this->revisionService->logNotification($actor, $data);
    }

    public function updateNotificationStatus(User $actor, AcademicPmcTimetableNotification $notification, string $status, ?string $note = null): AcademicPmcTimetableNotification
    {
        return $this->revisionService->updateNotificationStatus($actor, $notification, $status, $note);
    }

    public function retryNotification(User $actor, AcademicPmcTimetableNotification $notification, ?string $note = null): AcademicPmcTimetableNotification
    {
        return $this->revisionService->retryNotification($actor, $notification, $note);
    }

    private function allocationSurface(User $user, array $filters): array
    {
        return $this->readModels->allocationSurface(
            $user,
            $filters,
            $this->allocationPressureDiagnostics($user),
            fn (Builder $query, array $surfaceFilters) => $this->filter($query, $surfaceFilters)
        );
    }

    private function studentBasketSurface(User $user, array $filters): array
    {
        return $this->readModels->studentBasketSurface(
            $user,
            $filters,
            $this->courseBasketDiagnostics($user),
            $this->allocationPressureDiagnostics($user),
            fn (Builder $query, array $surfaceFilters) => $this->filter($query, $surfaceFilters)
        );
    }

    private function groupSurface(User $user, array $filters): array
    {
        return $this->readModels->groupSurface(
            $user,
            $filters,
            $this->courseGroupDiagnostics($user),
            fn (Builder $query, array $surfaceFilters) => $this->filter($query, $surfaceFilters)
        );
    }

    private function facultySurface(User $user, string $surface, array $filters): array
    {
        return $this->readModels->facultySurface(
            $user,
            $surface,
            $filters,
            $this->facultyAllocationDiagnostics($user),
            $this->facultySuitabilityDiagnostics(null, $user)
        );
    }

    private function lockedSlotSurface(User $user, array $filters): array
    {
        return $this->readModels->lockedSlotSurface(
            $user,
            $filters,
            $this->readinessChecklist($user),
            $this->readinessInputDiagnostics($user)
        );
    }

    private function generatorSurface(User $user, string $surface, array $filters): array
    {
        return $this->readModels->generatorSurface(
            $user,
            $surface,
            $filters,
            $this->generationValidationDiagnostics($user)
        );
    }

    private function plannerSurface(User $user, array $filters): array
    {
        return $this->readModels->plannerSurface(
            $user,
            $filters,
            fn (Builder $query, array $surfaceFilters) => $this->applyTimetableItemSort($query, $surfaceFilters),
            fn (Builder $query, User $scopedUser, Builder $scopeQuery, array $directMap) => $this->constrainConstraintsByUserScope($query, $scopedUser, $scopeQuery, $directMap)
        );
    }

    private function versionSurface(User $user, array $filters): array
    {
        return $this->readModels->versionSurface(
            $user,
            $filters,
            $this->publishFreezeReadinessDiagnostics($user)
        );
    }

    private function substitutionSurface(User $user, array $filters): array
    {
        return $this->readModels->substitutionSurface(
            $user,
            $filters,
            $this->substitutionEmergencyDiagnostics($user)
        );
    }

    private function reportsSurface(User $user, array $filters): array
    {
        return $this->readModels->reportsSurface($user, $filters);
    }

    private function readinessChecklist(?User $user = null): array
    {
        return $this->readinessGate->readinessChecklist(
            fn (?AcademicPmcTimetableGenerationRun $run = null, ?User $scopedUser = null) => $this->facultySuitabilityDiagnostics($run, $scopedUser),
            fn (string $check, ?User $scopedUser = null) => $this->readinessChecklistScopedExists($check, $scopedUser),
            $user
        );
    }

    private function launchControl(?User $user = null): array
    {
        return $this->readinessGate->launchControl([
            'basket' => $this->courseBasketDiagnostics($user),
            'group' => $this->courseGroupDiagnostics($user),
            'faculty' => $this->facultyAllocationDiagnostics($user),
            'faculty_suitability' => $this->facultySuitabilityDiagnostics(null, $user),
            'readiness_inputs' => $this->readinessInputDiagnostics($user),
            'generation' => $this->generationValidationDiagnostics($user),
            'publish' => $this->publishFreezeReadinessDiagnostics(),
        ]);
    }

    private function readinessChecklistScopedExists(string $check, ?User $user = null): bool
    {
        if (! $user) {
            return match ($check) {
                'allocations' => AcademicPmcStudentCourseAllocation::whereIn('basket_status', ['approved', 'locked', 'allocated'])->exists(),
                'groups' => AcademicPmcCourseGroup::query()->exists(),
                'faculty_assignments' => AcademicPmcGroupFacultyAssignment::query()->exists(),
                'faculty_preferences' => AcademicPmcFacultyPreference::query()->exists(),
                'locked_slots' => AcademicPmcLockedSlot::query()->exists(),
                'no_hard_conflicts' => AcademicPmcTimetableConstraint::where('severity', 'hard')->count() === 0,
                default => false,
            };
        }

        return match ($check) {
            'allocations' => $this->applyScope(
                AcademicPmcStudentCourseAllocation::query(),
                $user,
                [],
                ['student' => ['program_id' => 'program', 'batch_id' => 'batch']]
            )->whereIn('basket_status', ['approved', 'locked', 'allocated'])->exists(),
            'groups' => $this->applyScope(
                AcademicPmcCourseGroup::query(),
                $user,
                ['program_id' => 'program', 'batch_id' => 'batch', 'term_id' => 'term']
            )->exists(),
            'faculty_assignments' => $this->applyScope(
                AcademicPmcGroupFacultyAssignment::query(),
                $user,
                [],
                ['courseGroup' => ['program_id' => 'program', 'batch_id' => 'batch', 'term_id' => 'term']]
            )->exists(),
            'faculty_preferences' => $this->applyScope(
                AcademicPmcFacultyPreference::query(),
                $user,
                ['program_id' => 'program', 'batch_id' => 'batch', 'term_id' => 'term']
            )->exists(),
            'locked_slots' => $this->applyScope(
                AcademicPmcLockedSlot::query(),
                $user,
                ['program_id' => 'program', 'batch_id' => 'batch', 'term_id' => 'term']
            )->exists(),
            'no_hard_conflicts' => AcademicPmcTimetableConstraint::where('severity', 'hard')->count() === 0,
            default => false,
        };
    }

    private function publishFreezeReadinessDiagnostics(?User $user = null): array
    {
        return $this->readinessGate->publishFreezeReadinessDiagnostics($user);
    }

    private function substitutionEmergencyDiagnostics(?User $user = null): array
    {
        return $this->readinessGate->substitutionEmergencyDiagnostics($user);
    }

    private function generationValidationDiagnostics(?User $user = null): array
    {
        return $this->generationService->generationValidationDiagnostics(
            $user,
            fn (AcademicPmcTimetableGenerationRun $run) => $this->syncFacultySuitabilityPublishCheck($run)
        );
    }

    private function readinessInputDiagnostics(?User $user = null): array
    {
        return $this->readinessGate->readinessInputDiagnostics(
            fn (Builder $query, ?User $scopeUser, array $directMap = [], array $relationMap = []) => $this->applyScope($query, $scopeUser, $directMap, $relationMap),
            $user
        );
    }

    private function facultyAllocationDiagnostics(?User $user = null): array
    {
        return $this->readinessGate->facultyAllocationDiagnostics(
            fn (Builder $query, ?User $scopeUser, array $directMap = [], array $relationMap = []) => $this->applyScope($query, $scopeUser, $directMap, $relationMap),
            fn (User $scopeUser) => $this->policy->canIgnorePmcScope($scopeUser),
            $user
        );
    }

    private function facultySuitabilityDiagnostics(?AcademicPmcTimetableGenerationRun $run = null, ?User $user = null): array
    {
        return $this->readinessGate->facultySuitabilityDiagnostics(
            fn (Builder $query, ?User $scopeUser, array $directMap = [], array $relationMap = []) => $this->applyScope($query, $scopeUser, $directMap, $relationMap),
            $run,
            $user
        );
    }

    private function courseGroupDiagnostics(?User $user = null): array
    {
        $scopeIds = $user && ! $this->policy->canIgnorePmcScope($user) ? [
            'program_id' => $this->policy->scopedProgramIds($user),
            'batch_id' => $this->policy->scopedBatchIds($user),
            'term_id' => $this->policy->scopedTermIds($user),
            'subject_id' => $this->policy->scopedSubjectIds($user),
        ] : null;

        return $this->readinessGate->courseGroupDiagnostics($scopeIds);
    }

    private function courseBasketDiagnostics(?User $user = null): array
    {
        return $this->readinessGate->courseBasketDiagnostics(
            fn (Builder $query, ?User $scopeUser, array $directMap = [], array $relationMap = []) => $this->applyScope($query, $scopeUser, $directMap, $relationMap),
            fn (User $scopeUser) => $this->policy->canIgnorePmcScope($scopeUser),
            $user
        );
    }

    private function allocationPressureDiagnostics(?User $user = null): array
    {
        return $this->readinessGate->allocationPressureDiagnostics($user);
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

    private function logLifecycleNotification(TimetableVersion $version, string $type, string $title, string $recipientType, array $metadata = []): void
    {
        $this->publishService->logLifecycleNotification($version, $type, $title, $recipientType, $metadata);
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
