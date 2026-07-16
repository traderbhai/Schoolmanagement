<?php

namespace App\Services;

use App\Models\AcademicPmcCourseAllocationBatch;
use App\Models\AcademicPmcCourseAllocationException;
use App\Models\AcademicPmcCourseGroup;
use App\Models\AcademicPmcCourseGroupAdjustment;
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
use App\Models\AcademicPmcTimetableVersionWorkflow;
use App\Models\AcademicPmcWorkloadRule;
use App\Models\AcademicYear;
use App\Models\Batch;
use App\Models\Department;
use App\Models\DepartmentActivityLog;
use App\Models\Program;
use App\Models\Semester;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Term;
use App\Models\TimetableVersion;
use App\Models\User;
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
        private PmcTimetableFacultyReadinessService $facultyReadinessService,
        private PmcTimetableAllocationOperationsService $allocationOperationsService,
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

    public function selectorOptionsForFilters(): array
    {
        return $this->readModels->selectorOptions();
    }

    private function selectorOptions(): array
    {
        return $this->readModels->selectorOptions();
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
        return $this->facultyReadinessService->facultyAvailabilitySurface($user, $filters);
    }

    public function facultyOwnAvailabilitySurface(User $user): array
    {
        return $this->facultyReadinessService->facultyOwnAvailabilitySurface($user);
    }

    public function submitFacultyAvailability(User $actor, array $data): AcademicPmcFacultyAvailabilityRequest
    {
        return $this->facultyReadinessService->submitFacultyAvailability(
            $actor,
            $data,
            fn (User $auditActor, string $action, string $description, mixed $subject = null, array $metadata = []) => $this->audit($auditActor, $action, $description, $subject, $metadata)
        );
    }

    public function decideFacultyAvailability(User $actor, AcademicPmcFacultyAvailabilityRequest $request, string $status, ?string $note): AcademicPmcFacultyAvailabilityRequest
    {
        return $this->facultyReadinessService->decideFacultyAvailability(
            $actor,
            $request,
            $status,
            $note,
            fn (User $auditActor, string $action, string $description, mixed $subject = null, array $metadata = []) => $this->audit($auditActor, $action, $description, $subject, $metadata)
        );
    }

    public function refreshFacultyLoadReviews(User $actor, array $data = []): array
    {
        return $this->facultyReadinessService->refreshFacultyLoadReviews(
            $actor,
            $data,
            fn (User $auditActor, string $action, string $description, mixed $subject = null, array $metadata = []) => $this->audit($auditActor, $action, $description, $subject, $metadata),
            fn (?AcademicPmcTimetableGenerationRun $run = null) => $this->syncFacultySuitabilityPublishCheck($run)
        );
    }

    public function decideFacultyLoadReview(User $actor, AcademicPmcFacultyLoadReview $review, string $status, ?string $note): AcademicPmcFacultyLoadReview
    {
        return $this->facultyReadinessService->decideFacultyLoadReview(
            $actor,
            $review,
            $status,
            $note,
            fn (User $auditActor, string $action, string $description, mixed $subject = null, array $metadata = []) => $this->audit($auditActor, $action, $description, $subject, $metadata),
            fn (?AcademicPmcTimetableGenerationRun $run = null) => $this->syncFacultySuitabilityPublishCheck($run)
        );
    }

    public function refreshRoomReadinessReviews(User $actor, array $data = []): array
    {
        return $this->facultyReadinessService->refreshRoomReadinessReviews(
            $actor,
            $data,
            fn (User $auditActor, string $action, string $description, mixed $subject = null, array $metadata = []) => $this->audit($auditActor, $action, $description, $subject, $metadata)
        );
    }

    public function decideRoomReadinessReview(User $actor, AcademicPmcRoomReadinessReview $review, string $status, ?string $note): AcademicPmcRoomReadinessReview
    {
        return $this->facultyReadinessService->decideRoomReadinessReview(
            $actor,
            $review,
            $status,
            $note,
            fn (User $auditActor, string $action, string $description, mixed $subject = null, array $metadata = []) => $this->audit($auditActor, $action, $description, $subject, $metadata)
        );
    }
    public function requestCourseAllocationException(User $actor, array $data): AcademicPmcCourseAllocationException
    {
        return $this->allocationOperationsService->requestCourseAllocationException($actor, $data, $this->auditCallback());
    }

    public function decideCourseAllocationException(User $actor, AcademicPmcCourseAllocationException $exception, string $status, ?string $note): AcademicPmcCourseAllocationException
    {
        return $this->allocationOperationsService->decideCourseAllocationException($actor, $exception, $status, $note, $this->auditCallback());
    }

    public function requestCourseGroupAdjustment(User $actor, array $data): AcademicPmcCourseGroupAdjustment
    {
        return $this->allocationOperationsService->requestCourseGroupAdjustment($actor, $data, $this->auditCallback());
    }

    public function requestFacultyAssignmentAcknowledgement(User $actor, AcademicPmcGroupFacultyAssignment $assignment): AcademicPmcFacultyAssignmentAcknowledgement
    {
        return $this->allocationOperationsService->requestFacultyAssignmentAcknowledgement($actor, $assignment, $this->auditCallback());
    }

    public function respondFacultyAssignmentAcknowledgement(User $actor, AcademicPmcFacultyAssignmentAcknowledgement $ack, string $responseType, ?string $note, array $constraints = []): AcademicPmcFacultyAssignmentAcknowledgement
    {
        return $this->allocationOperationsService->respondFacultyAssignmentAcknowledgement($actor, $ack, $responseType, $note, $constraints, $this->auditCallback());
    }

    public function reviewFacultyAssignmentAcknowledgement(User $actor, AcademicPmcFacultyAssignmentAcknowledgement $ack, string $status, ?string $note): AcademicPmcFacultyAssignmentAcknowledgement
    {
        return $this->allocationOperationsService->reviewFacultyAssignmentAcknowledgement($actor, $ack, $status, $note, $this->auditCallback());
    }

    public function decideCourseGroupAdjustment(User $actor, AcademicPmcCourseGroupAdjustment $adjustment, string $status, ?string $note): AcademicPmcCourseGroupAdjustment
    {
        return $this->allocationOperationsService->decideCourseGroupAdjustment($actor, $adjustment, $status, $note, $this->auditCallback());
    }

    public function bulkAllocateCore(User $actor, array $data): AcademicPmcCourseAllocationBatch
    {
        return $this->allocationOperationsService->bulkAllocateCore($actor, $data, $this->auditCallback());
    }

    public function allocateElectives(User $actor, array $data): array
    {
        return $this->allocationOperationsService->allocateElectives($actor, $data, $this->auditCallback());
    }

    public function autoBuildGroups(User $actor, array $data): AcademicPmcGroupBuildRun
    {
        return $this->allocationOperationsService->autoBuildGroups($actor, $data, $this->auditCallback());
    }

    public function createGroup(User $actor, array $data): AcademicPmcCourseGroup
    {
        return $this->allocationOperationsService->createGroup($actor, $data, $this->auditCallback());
    }

    public function assignFaculty(User $actor, array $data): AcademicPmcGroupFacultyAssignment
    {
        return $this->allocationOperationsService->assignFaculty($actor, $data, $this->auditCallback());
    }

    public function createLockedSlot(User $actor, array $data): AcademicPmcLockedSlot
    {
        return $this->allocationOperationsService->createLockedSlot($actor, $data, $this->auditCallback());
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

    private function auditCallback(): callable
    {
        return fn (User $auditActor, string $action, string $description, mixed $subject = null, array $metadata = []) => $this->audit($auditActor, $action, $description, $subject, $metadata);
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
