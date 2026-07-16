<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\Admin;
use App\Http\Controllers\Student;
use App\Http\Controllers\Teacher;
use App\Http\Controllers\Parent as ParentController;
use App\Http\Controllers\ApplyController;
use App\Http\Controllers\Admission;
use App\Http\Controllers\Academic;
use App\Http\Controllers\Applicant\DashboardController as ApplicantDashboard;
use App\Http\Controllers\Applicant\ApplicationController as ApplicantApplication;
use App\Http\Controllers\Applicant\DocumentController as ApplicantDocument;
use App\Http\Controllers\Applicant\StatusController as ApplicantStatus;
use App\Http\Controllers\Applicant\PaymentController as ApplicantPayment;
use App\Http\Controllers\Applicant\RegistrationFeeController as ApplicantRegistrationFee;
use App\Http\Controllers\Departmental;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\StatusTrackerController;
use App\Http\Controllers\ApprovalController;
use App\Http\Controllers\ClaudeAnalysisController;
use App\Support\DashboardRedirect;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth'])->prefix('academics')->name('academics.')->group(function () {
    Route::get('command-center', [\App\Http\Controllers\Academics\CommandCenterController::class, 'index'])->name('command-center.index');
    Route::get('workspaces/{workspace}', [\App\Http\Controllers\Academics\CommandCenterController::class, 'workspace'])->name('workspaces.show');
    Route::get('attention/{queue}', [\App\Http\Controllers\Academics\CommandCenterController::class, 'queue'])->name('attention.queue');
    Route::prefix('pmc')->name('pmc.')->group(function () {
        Route::get('/', [\App\Http\Controllers\Academics\PmcOperatingController::class, 'index'])->name('index');
        Route::get('command', [\App\Http\Controllers\Academics\PmcOperatingController::class, 'command'])->name('command');
        Route::get('workbench', [\App\Http\Controllers\Academics\PmcOperatingController::class, 'workbench'])->name('workbench');
        Route::post('work-items', [\App\Http\Controllers\Academics\PmcOperatingController::class, 'storeWorkItem'])->name('work-items.store');
        Route::patch('work-items/{item}', [\App\Http\Controllers\Academics\PmcOperatingController::class, 'updateWorkItem'])->name('work-items.update');
        Route::get('curriculum-governance', [\App\Http\Controllers\Academics\PmcOperatingController::class, 'curriculumGovernance'])->name('curriculum-governance');
        Route::get('faculty-workload', [\App\Http\Controllers\Academics\PmcOperatingController::class, 'facultyWorkload'])->name('faculty-workload');
        Route::get('timetable-control', [\App\Http\Controllers\Academics\PmcOperatingController::class, 'timetableControl'])->name('timetable-control');
        Route::get('student-success', [\App\Http\Controllers\Academics\PmcOperatingController::class, 'studentSuccess'])->name('student-success');
        Route::get('reviews', [\App\Http\Controllers\Academics\PmcOperatingController::class, 'reviews'])->name('reviews');
        Route::post('reviews', [\App\Http\Controllers\Academics\PmcOperatingController::class, 'storeReview'])->name('reviews.store');
        Route::post('saved-views', [\App\Http\Controllers\Academics\PmcOperatingController::class, 'storeSavedView'])->name('saved-views.store');
        Route::get('export/{report}', [\App\Http\Controllers\Academics\PmcOperatingController::class, 'export'])->name('export');
        Route::get('programs', [\App\Http\Controllers\Academics\PmcOperatingController::class, 'programs'])->name('programs');
        Route::get('curriculum-readiness', [\App\Http\Controllers\Academics\PmcOperatingController::class, 'curriculumReadiness'])->name('curriculum-readiness');
        Route::get('faculty-allocation', [\App\Http\Controllers\Academics\PmcOperatingController::class, 'facultyAllocation'])->name('faculty-allocation');
        Route::get('timetable-readiness', [\App\Http\Controllers\Academics\PmcOperatingController::class, 'timetableReadiness'])->name('timetable-readiness');
        Route::get('student-monitoring', [\App\Http\Controllers\Academics\PmcOperatingController::class, 'studentMonitoring'])->name('student-monitoring');
        Route::get('reports', [\App\Http\Controllers\Academics\PmcOperatingController::class, 'reports'])->name('reports');

        Route::post('v004/records', [\App\Http\Controllers\Academics\PmcOperatingController::class, 'storeV004Record'])->name('v004.records.store');
        Route::patch('v004/records/{record}', [\App\Http\Controllers\Academics\PmcOperatingController::class, 'updateV004Record'])->name('v004.records.update');
        Route::post('v004/records/{record}/work-item', [\App\Http\Controllers\Academics\PmcOperatingController::class, 'createWorkItemFromRecord'])->name('v004.records.work-item');
        Route::patch('v004/approvals/{approval}', [\App\Http\Controllers\Academics\PmcOperatingController::class, 'decideApproval'])->name('v004.approvals.decide');
        Route::post('v004/automation/refresh', [\App\Http\Controllers\Academics\PmcOperatingController::class, 'refreshAutomation'])->name('v004.automation.refresh');
        Route::post('curriculum-validations/refresh', [\App\Http\Controllers\Academics\PmcOperatingController::class, 'refreshCurriculumValidations'])->name('curriculum-validations.refresh');

        Route::prefix('planning')->name('planning.')->group(function () {
            Route::get('/', [\App\Http\Controllers\Academics\PmcOperatingController::class, 'v004Surface'])->defaults('surface', 'planning')->name('index');
            Route::post('/', [\App\Http\Controllers\Academics\PmcOperatingController::class, 'storePlanningCycle'])->name('store');
            Route::patch('cycles/{cycle}', [\App\Http\Controllers\Academics\PmcOperatingController::class, 'updatePlanningCycleStatus'])->name('cycles.update');
        });
        Route::prefix('semester-readiness')->name('semester-readiness.')->group(function () {
            Route::get('/', [\App\Http\Controllers\Academics\PmcOperatingController::class, 'v004Surface'])->defaults('surface', 'semester-readiness')->name('index');
            Route::patch('items/{item}', [\App\Http\Controllers\Academics\PmcOperatingController::class, 'updateReadinessItem'])->name('items.update');
            Route::post('items/{item}/work-item', [\App\Http\Controllers\Academics\PmcOperatingController::class, 'createWorkItemFromReadiness'])->name('items.work-item');
        });
        Route::prefix('academic-calendar')->name('academic-calendar.')->group(function () {
            Route::get('/', [\App\Http\Controllers\Academics\PmcOperatingController::class, 'v004Surface'])->defaults('surface', 'academic-calendar')->name('index');
        });
        Route::prefix('curriculum-governance-v004')->name('curriculum-governance.')->group(function () {
            Route::get('/', [\App\Http\Controllers\Academics\PmcOperatingController::class, 'v004Surface'])->defaults('surface', 'curriculum-governance-v004')->name('index');
        });
        Route::prefix('syllabus-versions')->name('syllabus-versions.')->group(function () {
            Route::get('/', [\App\Http\Controllers\Academics\PmcOperatingController::class, 'v004Surface'])->defaults('surface', 'syllabus-versions')->name('index');
        });
        Route::prefix('curriculum-rollout')->name('curriculum-rollout.')->group(function () {
            Route::get('/', [\App\Http\Controllers\Academics\PmcOperatingController::class, 'v004Surface'])->defaults('surface', 'curriculum-rollout')->name('index');
        });
        Route::prefix('faculty-allocation-v004')->name('faculty-allocation-v004.')->group(function () {
            Route::get('/', [\App\Http\Controllers\Academics\PmcOperatingController::class, 'v004Surface'])->defaults('surface', 'faculty-allocation-v004')->name('index');
        });
        Route::prefix('workload-rules')->name('workload-rules.')->group(function () {
            Route::get('/', [\App\Http\Controllers\Academics\PmcOperatingController::class, 'v004Surface'])->defaults('surface', 'workload-rules')->name('index');
        });
        Route::prefix('faculty-shortage')->name('faculty-shortage.')->group(function () {
            Route::get('/', [\App\Http\Controllers\Academics\PmcOperatingController::class, 'v004Surface'])->defaults('surface', 'faculty-shortage')->name('index');
        });
        Route::prefix('timetable-governance')->name('timetable-governance.')->group(function () {
            Route::get('/', [\App\Http\Controllers\Academics\PmcOperatingController::class, 'v004Surface'])->defaults('surface', 'timetable-governance')->name('index');
        });
        Route::prefix('timetable-conflicts')->name('timetable-conflicts.')->group(function () {
            Route::get('/', [\App\Http\Controllers\Academics\PmcOperatingController::class, 'v004Surface'])->defaults('surface', 'timetable-conflicts')->name('index');
        });
        Route::prefix('substitution-control')->name('substitution-control.')->group(function () {
            Route::get('/', [\App\Http\Controllers\Academics\PmcOperatingController::class, 'v004Surface'])->defaults('surface', 'substitution-control')->name('index');
        });
        Route::prefix('course-delivery')->name('course-delivery.')->group(function () {
            Route::get('/', [\App\Http\Controllers\Academics\PmcOperatingController::class, 'v004Surface'])->defaults('surface', 'course-delivery')->name('index');
            Route::post('refresh', [\App\Http\Controllers\Academics\PmcOperatingController::class, 'refreshCourseDeliveryCheckpoints'])->name('refresh');
            Route::post('checkpoints/{checkpoint}/remedial-actions', [\App\Http\Controllers\Academics\PmcOperatingController::class, 'storeRemedialAction'])->name('remedial-actions.store');
        });
        Route::prefix('delivery-risk')->name('delivery-risk.')->group(function () {
            Route::get('/', [\App\Http\Controllers\Academics\PmcOperatingController::class, 'v004Surface'])->defaults('surface', 'delivery-risk')->name('index');
        });
        Route::prefix('remedial-planning')->name('remedial-planning.')->group(function () {
            Route::get('/', [\App\Http\Controllers\Academics\PmcOperatingController::class, 'v004Surface'])->defaults('surface', 'remedial-planning')->name('index');
            Route::patch('actions/{action}', [\App\Http\Controllers\Academics\PmcOperatingController::class, 'updateRemedialAction'])->name('actions.update');
        });
        Route::prefix('student-success-v004')->name('student-success-v004.')->group(function () {
            Route::get('/', [\App\Http\Controllers\Academics\PmcOperatingController::class, 'v004Surface'])->defaults('surface', 'student-success-v004')->name('index');
            Route::post('refresh', [\App\Http\Controllers\Academics\PmcOperatingController::class, 'refreshStudentSuccessSignals'])->name('refresh');
            Route::post('plans/{plan}/interventions', [\App\Http\Controllers\Academics\PmcOperatingController::class, 'storeStudentIntervention'])->name('interventions.store');
            Route::post('plans/{plan}/parent-escalations', [\App\Http\Controllers\Academics\PmcOperatingController::class, 'storeParentEscalation'])->name('parent-escalations.store');
        });
        Route::prefix('interventions')->name('interventions.')->group(function () {
            Route::get('/', [\App\Http\Controllers\Academics\PmcOperatingController::class, 'v004Surface'])->defaults('surface', 'interventions')->name('index');
            Route::patch('{intervention}', [\App\Http\Controllers\Academics\PmcOperatingController::class, 'updateStudentIntervention'])->name('update');
        });
        Route::prefix('mentor-governance')->name('mentor-governance.')->group(function () {
            Route::get('/', [\App\Http\Controllers\Academics\PmcOperatingController::class, 'v004Surface'])->defaults('surface', 'mentor-governance')->name('index');
        });
        Route::prefix('parent-escalations')->name('parent-escalations.')->group(function () {
            Route::get('/', [\App\Http\Controllers\Academics\PmcOperatingController::class, 'v004Surface'])->defaults('surface', 'parent-escalations')->name('index');
        });
        Route::prefix('review-templates')->name('review-templates.')->group(function () {
            Route::get('/', [\App\Http\Controllers\Academics\PmcOperatingController::class, 'v004Reviews'])->name('index');
        });
        Route::prefix('meeting-minutes')->name('meeting-minutes.')->group(function () {
            Route::get('/', [\App\Http\Controllers\Academics\PmcOperatingController::class, 'v004Reviews'])->name('index');
            Route::patch('{minutes}/approve', [\App\Http\Controllers\Academics\PmcOperatingController::class, 'approvePmcMinutes'])->name('approve');
        });
        Route::prefix('decisions')->name('decisions.')->group(function () {
            Route::get('/', [\App\Http\Controllers\Academics\PmcOperatingController::class, 'v004Reviews'])->name('index');
        });
        Route::prefix('action-governance')->name('action-governance.')->group(function () {
            Route::get('/', [\App\Http\Controllers\Academics\PmcOperatingController::class, 'v004Reviews'])->name('index');
            Route::post('actions/{item}/dependencies', [\App\Http\Controllers\Academics\PmcOperatingController::class, 'storePmcActionDependency'])->name('dependencies.store');
            Route::post('actions/{item}/evidence', [\App\Http\Controllers\Academics\PmcOperatingController::class, 'storePmcActionEvidence'])->name('evidence.store');
            Route::patch('actions/{item}/verify', [\App\Http\Controllers\Academics\PmcOperatingController::class, 'verifyPmcActionClosure'])->name('actions.verify');
        });
        Route::prefix('approvals')->name('approvals.')->group(function () {
            Route::get('/', [\App\Http\Controllers\Academics\PmcOperatingController::class, 'v004Approvals'])->name('index');
        });
        Route::prefix('attention')->name('attention.')->group(function () {
            Route::get('/', [\App\Http\Controllers\Academics\PmcOperatingController::class, 'v004Surface'])->defaults('surface', 'planning')->name('index');
        });
        Route::prefix('automation')->name('automation.')->group(function () {
            Route::get('/', [\App\Http\Controllers\Academics\PmcOperatingController::class, 'v004Automation'])->name('index');
        });
        Route::prefix('analytics')->name('analytics.')->group(function () {
            Route::get('/', [\App\Http\Controllers\Academics\PmcOperatingController::class, 'v004Analytics'])->name('index');
        });
        Route::prefix('scheduled-reports')->name('scheduled-reports.')->group(function () {
            Route::get('/', [\App\Http\Controllers\Academics\PmcOperatingController::class, 'v004Analytics'])->name('index');
        });
        Route::prefix('export-logs')->name('export-logs.')->group(function () {
            Route::get('/', [\App\Http\Controllers\Academics\PmcOperatingController::class, 'v004Analytics'])->name('index');
        });
        Route::prefix('policy-audit')->name('policy-audit.')->group(function () {
            Route::get('/', [\App\Http\Controllers\Academics\PmcOperatingController::class, 'v004PolicyAudit'])->name('index');
        });
        Route::get('timetable-os', [\App\Http\Controllers\Academics\PmcOperatingController::class, 'v041Dashboard'])->name('timetable-os.index');
        Route::get('timetable-launch', [\App\Http\Controllers\Academics\PmcOperatingController::class, 'v110TimetableLaunch'])->name('timetable-launch.index');
        Route::get('canonical-sessions/{item}', [\App\Http\Controllers\Academics\PmcOperatingController::class, 'v110CanonicalSession'])->name('canonical-sessions.show');
        Route::get('timetable-clashes', [\App\Http\Controllers\Academics\PmcOperatingController::class, 'v110TimetableClashes'])->name('timetable-clashes.index');
        Route::get('timetable-clashes/export', [\App\Http\Controllers\Academics\PmcOperatingController::class, 'v110ExportTimetableClashes'])->name('timetable-clashes.export');
        Route::get('post-publish-operations', [\App\Http\Controllers\Academics\PmcOperatingController::class, 'v110PostPublishOperations'])->name('post-publish-operations.index');
        Route::get('official-timetable', [\App\Http\Controllers\Academics\PmcOperatingController::class, 'v044OfficialTimetable'])->name('official-timetable.index');
        Route::get('data-reconciliation', [\App\Http\Controllers\Academics\PmcOperatingController::class, 'v092DataReconciliation'])->name('data-reconciliation.index');
        Route::get('data-reconciliation/export', [\App\Http\Controllers\Academics\PmcOperatingController::class, 'v095ExportDataReconciliation'])->name('data-reconciliation.export');
        Route::get('data-reconciliation/runs/export', [\App\Http\Controllers\Academics\PmcOperatingController::class, 'v102ExportDataReconciliationRuns'])->name('data-reconciliation.runs.export');
        Route::get('data-reconciliation/audit/export', [\App\Http\Controllers\Academics\PmcOperatingController::class, 'v107ExportDataReconciliationAudit'])->name('data-reconciliation.audit.export');
        Route::get('v041/{surface}/export', [\App\Http\Controllers\Academics\PmcOperatingController::class, 'v041ExportSurface'])->name('v041.surface.export');
        Route::patch('data-reconciliation/runs/{run}/mark-failed', [\App\Http\Controllers\Academics\PmcOperatingController::class, 'v104MarkReconciliationRunFailed'])->name('data-reconciliation.runs.mark-failed');
        Route::post('data-reconciliation/refresh', [\App\Http\Controllers\Academics\PmcOperatingController::class, 'v092RefreshDataReconciliation'])->name('data-reconciliation.refresh');
        Route::post('data-reconciliation/{check}/repair', [\App\Http\Controllers\Academics\PmcOperatingController::class, 'v093RepairDataReconciliation'])->name('data-reconciliation.repair');
        Route::get('faculty-availability-requests', [\App\Http\Controllers\Academics\PmcOperatingController::class, 'v046FacultyAvailability'])->name('faculty-availability-requests.index');
        Route::post('faculty-availability-requests', [\App\Http\Controllers\Academics\PmcOperatingController::class, 'v046SubmitFacultyAvailability'])->name('faculty-availability-requests.store');
        Route::patch('faculty-availability-requests/{availability}', [\App\Http\Controllers\Academics\PmcOperatingController::class, 'v046DecideFacultyAvailability'])->name('faculty-availability-requests.decide');
        Route::post('faculty-load-reviews/refresh', [\App\Http\Controllers\Academics\PmcOperatingController::class, 'v047RefreshFacultyLoadReviews'])->name('faculty-load-reviews.refresh');
        Route::patch('faculty-load-reviews/{review}', [\App\Http\Controllers\Academics\PmcOperatingController::class, 'v047DecideFacultyLoadReview'])->name('faculty-load-reviews.decide');
        Route::post('room-readiness-reviews/refresh', [\App\Http\Controllers\Academics\PmcOperatingController::class, 'v048RefreshRoomReadinessReviews'])->name('room-readiness-reviews.refresh');
        Route::patch('room-readiness-reviews/{review}', [\App\Http\Controllers\Academics\PmcOperatingController::class, 'v048DecideRoomReadinessReview'])->name('room-readiness-reviews.decide');
        Route::post('course-allocation/bulk-core', [\App\Http\Controllers\Academics\PmcOperatingController::class, 'v041StoreAllocationBatch'])->name('course-allocation.bulk-core');
        Route::post('elective-allocation/process', [\App\Http\Controllers\Academics\PmcOperatingController::class, 'v042AllocateElectives'])->name('elective-allocation.process');
        Route::post('course-allocation-exceptions', [\App\Http\Controllers\Academics\PmcOperatingController::class, 'v050RequestCourseAllocationException'])->name('course-allocation-exceptions.store');
        Route::patch('course-allocation-exceptions/{exception}', [\App\Http\Controllers\Academics\PmcOperatingController::class, 'v050DecideCourseAllocationException'])->name('course-allocation-exceptions.decide');
        Route::post('course-groups', [\App\Http\Controllers\Academics\PmcOperatingController::class, 'v041StoreGroup'])->name('course-groups.store');
        Route::post('course-groups/auto-build', [\App\Http\Controllers\Academics\PmcOperatingController::class, 'v042AutoBuildGroups'])->name('course-groups.auto-build');
        Route::post('course-group-adjustments', [\App\Http\Controllers\Academics\PmcOperatingController::class, 'v051RequestCourseGroupAdjustment'])->name('course-group-adjustments.store');
        Route::patch('course-group-adjustments/{adjustment}', [\App\Http\Controllers\Academics\PmcOperatingController::class, 'v051DecideCourseGroupAdjustment'])->name('course-group-adjustments.decide');
        Route::post('sections', [\App\Http\Controllers\Academics\PmcOperatingController::class, 'v041StoreGroup'])->name('sections.store');
        Route::post('section-faculty-allocation', [\App\Http\Controllers\Academics\PmcOperatingController::class, 'v041AssignFaculty'])->name('section-faculty-allocation.assign');
        Route::post('section-faculty-allocation/{assignment}/acknowledgements', [\App\Http\Controllers\Academics\PmcOperatingController::class, 'v052RequestFacultyAssignmentAcknowledgement'])->name('faculty-assignment-acknowledgements.request');
        Route::patch('faculty-assignment-acknowledgements/{acknowledgement}/respond', [\App\Http\Controllers\Academics\PmcOperatingController::class, 'v052RespondFacultyAssignmentAcknowledgement'])->name('faculty-assignment-acknowledgements.respond');
        Route::patch('faculty-assignment-acknowledgements/{acknowledgement}/review', [\App\Http\Controllers\Academics\PmcOperatingController::class, 'v052ReviewFacultyAssignmentAcknowledgement'])->name('faculty-assignment-acknowledgements.review');
        Route::post('locked-slots', [\App\Http\Controllers\Academics\PmcOperatingController::class, 'v041StoreLockedSlot'])->name('locked-slots.store');
        Route::post('timetable-generator', [\App\Http\Controllers\Academics\PmcOperatingController::class, 'v041Generate'])->name('timetable-generator.generate');
        Route::post('timetable-generator/{run}/validate', [\App\Http\Controllers\Academics\PmcOperatingController::class, 'v042ValidateGeneration'])->name('timetable-generator.validate');
        Route::post('timetable-generator/{run}/impact-preview', [\App\Http\Controllers\Academics\PmcOperatingController::class, 'v069RefreshGenerationImpact'])->name('timetable-generator.impact-preview');
        Route::post('timetable-generator-items/{item}/apply-alternative', [\App\Http\Controllers\Academics\PmcOperatingController::class, 'v066ApplySolverAlternative'])->name('timetable-generator-items.apply-alternative');
        Route::post('timetable-generator-items/{item}/move', [\App\Http\Controllers\Academics\PmcOperatingController::class, 'v068MoveGeneratedItem'])->name('timetable-generator-items.move');
        Route::post('timetable-generator/{run}/publish', [\App\Http\Controllers\Academics\PmcOperatingController::class, 'v043PublishRun'])->name('timetable-generator.publish');
        Route::post('timetable-constraints/{constraint}/resolution-actions', [\App\Http\Controllers\Academics\PmcOperatingController::class, 'v045CreateResolutionAction'])->name('timetable-constraints.resolution-actions.store');
        Route::patch('timetable-resolution-actions/{action}', [\App\Http\Controllers\Academics\PmcOperatingController::class, 'v045CloseResolutionAction'])->name('timetable-resolution-actions.close');
        Route::post('timetable-versions-v041/{version}/freeze', [\App\Http\Controllers\Academics\PmcOperatingController::class, 'v043FreezeVersion'])->name('timetable-versions-v041.freeze');
        Route::post('timetable-versions-v041/{version}/unfreeze', [\App\Http\Controllers\Academics\PmcOperatingController::class, 'v043UnfreezeVersion'])->name('timetable-versions-v041.unfreeze');
        Route::post('timetable-versions-v041/{version}/rollback', [\App\Http\Controllers\Academics\PmcOperatingController::class, 'v043RollbackVersion'])->name('timetable-versions-v041.rollback');
        Route::post('timetable-change-requests', [\App\Http\Controllers\Academics\PmcOperatingController::class, 'v041RequestChange'])->name('timetable-change-requests.store');
        Route::patch('timetable-change-requests/{change}', [\App\Http\Controllers\Academics\PmcOperatingController::class, 'v041DecideChange'])->name('timetable-change-requests.decide');
        Route::post('substitution-intelligence', [\App\Http\Controllers\Academics\PmcOperatingController::class, 'v041RecommendSubstitution'])->name('substitution-intelligence.recommend');
        Route::post('timetable-notifications', [\App\Http\Controllers\Academics\PmcOperatingController::class, 'v041LogNotification'])->name('timetable-notifications.store');
        Route::patch('timetable-notifications/{notification}', [\App\Http\Controllers\Academics\PmcOperatingController::class, 'v074UpdateNotificationStatus'])->name('timetable-notifications.update-status');
        Route::post('timetable-notifications/{notification}/retry', [\App\Http\Controllers\Academics\PmcOperatingController::class, 'v075RetryNotification'])->name('timetable-notifications.retry');
        Route::patch('student-course-basket-acknowledgements/{acknowledgement}', [\App\Http\Controllers\Academics\PmcOperatingController::class, 'v090ReviewStudentBasketAcknowledgement'])->name('student-course-basket-acknowledgements.review');
        foreach ([
            'course-allocation',
            'elective-allocation',
            'student-course-baskets',
            'sections',
            'course-groups',
            'group-memberships',
            'section-faculty-allocation',
            'faculty-preferences',
            'load-planning',
            'area-chair-recommendations',
            'locked-slots',
            'timetable-readiness-v041',
            'timetable-generator',
            'timetable-suggestions',
            'timetable-quality',
            'timetable-planner',
            'timetable-versions-v041',
            'timetable-impact',
            'timetable-freeze',
            'substitution-intelligence',
            'timetable-change-requests',
            'timetable-reports',
        ] as $pmcTimetableSurface) {
            Route::get($pmcTimetableSurface, [\App\Http\Controllers\Academics\PmcOperatingController::class, 'v041Surface'])
                ->defaults('surface', $pmcTimetableSurface)
                ->name($pmcTimetableSurface . '.index');
        }
    });
    Route::prefix('coe')->name('coe.')->group(function () {
        Route::get('/', [\App\Http\Controllers\Academics\CoeOperatingController::class, 'index'])->name('index');
        Route::get('exam-readiness', [\App\Http\Controllers\Academics\CoeOperatingController::class, 'examReadiness'])->name('exam-readiness');
        Route::get('marks-results', [\App\Http\Controllers\Academics\CoeOperatingController::class, 'marksResults'])->name('marks-results');
        Route::get('hall-ticket-readiness', [\App\Http\Controllers\Academics\CoeOperatingController::class, 'hallTicketReadiness'])->name('hall-ticket-readiness');
        Route::get('transcripts', [\App\Http\Controllers\Academics\CoeOperatingController::class, 'transcripts'])->name('transcripts');
        Route::get('appeals-anomalies', [\App\Http\Controllers\Academics\CoeOperatingController::class, 'appealsAnomalies'])->name('appeals-anomalies');
        Route::get('reports', [\App\Http\Controllers\Academics\CoeOperatingController::class, 'reports'])->name('reports');
    });
    Route::prefix('iqac')->name('iqac.')->group(function () {
        Route::get('/', [\App\Http\Controllers\Academics\IqacOperatingController::class, 'index'])->name('index');
        Route::get('obe-readiness', [\App\Http\Controllers\Academics\IqacOperatingController::class, 'obeReadiness'])->name('obe-readiness');
        Route::get('attainment-monitoring', [\App\Http\Controllers\Academics\IqacOperatingController::class, 'attainmentMonitoring'])->name('attainment-monitoring');
        Route::get('feedback-quality', [\App\Http\Controllers\Academics\IqacOperatingController::class, 'feedbackQuality'])->name('feedback-quality');
        Route::get('audit-compliance', [\App\Http\Controllers\Academics\IqacOperatingController::class, 'auditCompliance'])->name('audit-compliance');
        Route::get('reports', [\App\Http\Controllers\Academics\IqacOperatingController::class, 'reports'])->name('reports');
    });
    Route::prefix('program-leadership')->name('program-leadership.')->group(function () {
        Route::get('/', [\App\Http\Controllers\Academics\ProgramLeadershipController::class, 'index'])->name('index');
        Route::get('portfolio', [\App\Http\Controllers\Academics\ProgramLeadershipController::class, 'portfolio'])->name('portfolio');
        Route::get('course-delivery', [\App\Http\Controllers\Academics\ProgramLeadershipController::class, 'courseDelivery'])->name('course-delivery');
        Route::get('student-success', [\App\Http\Controllers\Academics\ProgramLeadershipController::class, 'studentSuccess'])->name('student-success');
        Route::get('quality-signals', [\App\Http\Controllers\Academics\ProgramLeadershipController::class, 'qualitySignals'])->name('quality-signals');
        Route::get('reports', [\App\Http\Controllers\Academics\ProgramLeadershipController::class, 'reports'])->name('reports');
    });
    Route::prefix('course-delivery')->name('course-delivery.')->group(function () {
        Route::get('/', [\App\Http\Controllers\Academics\CourseDeliveryController::class, 'index'])->name('index');
        Route::get('course-load', [\App\Http\Controllers\Academics\CourseDeliveryController::class, 'courseLoad'])->name('course-load');
        Route::get('session-delivery', [\App\Http\Controllers\Academics\CourseDeliveryController::class, 'sessionDelivery'])->name('session-delivery');
        Route::get('attendance-interventions', [\App\Http\Controllers\Academics\CourseDeliveryController::class, 'attendanceInterventions'])->name('attendance-interventions');
        Route::get('course-engagement', [\App\Http\Controllers\Academics\CourseDeliveryController::class, 'courseEngagement'])->name('course-engagement');
        Route::get('mentor-actions', [\App\Http\Controllers\Academics\CourseDeliveryController::class, 'mentorActions'])->name('mentor-actions');
        Route::get('reports', [\App\Http\Controllers\Academics\CourseDeliveryController::class, 'reports'])->name('reports');
    });
    Route::prefix('dean-os')->name('dean-os.')->group(function () {
        Route::get('/', [\App\Http\Controllers\Academics\DeanOsController::class, 'index'])->name('index');
        Route::get('attention/{queue}', [\App\Http\Controllers\Academics\DeanOsController::class, 'attention'])->name('attention');
        Route::get('branch-health', [\App\Http\Controllers\Academics\DeanOsController::class, 'branchHealth'])->name('branch-health');
        Route::get('program-risk', [\App\Http\Controllers\Academics\DeanOsController::class, 'programRisk'])->name('program-risk');
        Route::get('reviews', [\App\Http\Controllers\Academics\DeanOsController::class, 'reviews'])->name('reviews');
        Route::post('reviews', [\App\Http\Controllers\Academics\DeanOsController::class, 'storeReview'])->name('reviews.store');
        Route::post('actions', [\App\Http\Controllers\Academics\DeanOsController::class, 'storeAction'])->name('actions.store');
        Route::patch('actions/{action}', [\App\Http\Controllers\Academics\DeanOsController::class, 'updateAction'])->name('actions.update');
        Route::get('handoff', [\App\Http\Controllers\Academics\DeanOsController::class, 'handoff'])->name('handoff');
        Route::get('calendar', [\App\Http\Controllers\Academics\DeanOsController::class, 'calendar'])->name('calendar');
        Route::get('reports', [\App\Http\Controllers\Academics\DeanOsController::class, 'reports'])->name('reports');
        Route::get('export/{report}', [\App\Http\Controllers\Academics\DeanOsController::class, 'export'])->name('export');
        Route::get('planning', [\App\Http\Controllers\Academics\DeanOsController::class, 'planning'])->name('planning.index');
        Route::post('planning', [\App\Http\Controllers\Academics\DeanOsController::class, 'storePlanning'])->name('planning.store');
        Route::patch('planning/{cycle}/approve', [\App\Http\Controllers\Academics\DeanOsController::class, 'approvePlanning'])->name('planning.approve');
        Route::post('semester-readiness/{item}/action', [\App\Http\Controllers\Academics\DeanOsController::class, 'actionFromReadiness'])->name('semester-readiness.action');
        Route::get('semester-readiness', [\App\Http\Controllers\Academics\DeanOsController::class, 'planning'])->name('semester-readiness.index');
        Route::get('academic-calendar-approval', [\App\Http\Controllers\Academics\DeanOsController::class, 'planning'])->name('academic-calendar-approval.index');
        Route::get('teaching-load-approval', [\App\Http\Controllers\Academics\DeanOsController::class, 'planning'])->name('teaching-load-approval.index');
        Route::get('review-templates', [\App\Http\Controllers\Academics\DeanOsController::class, 'reviewTemplates'])->name('review-templates.index');
        Route::post('meeting-minutes/{meeting}', [\App\Http\Controllers\Academics\DeanOsController::class, 'storeMinutes'])->name('meeting-minutes.store');
        Route::patch('meeting-minutes/{minute}/approve', [\App\Http\Controllers\Academics\DeanOsController::class, 'approveMinutes'])->name('meeting-minutes.approve');
        Route::post('decision-register', [\App\Http\Controllers\Academics\DeanOsController::class, 'storeDecision'])->name('decision-register.store');
        Route::get('action-rules', [\App\Http\Controllers\Academics\DeanOsController::class, 'actionsIndex'])->name('action-rules.index');
        Route::get('actions-governance', [\App\Http\Controllers\Academics\DeanOsController::class, 'actionsIndex'])->name('actions.index');
        Route::post('action-evidence/{action}', [\App\Http\Controllers\Academics\DeanOsController::class, 'storeActionEvidence'])->name('action-evidence.store');
        Route::get('action-escalations', [\App\Http\Controllers\Academics\DeanOsController::class, 'actionsIndex'])->name('action-escalations.index');
        Route::get('risk-settings', [\App\Http\Controllers\Academics\DeanOsController::class, 'riskSettings'])->name('risk-settings.index');
        Route::post('risk-history/capture', [\App\Http\Controllers\Academics\DeanOsController::class, 'captureRiskSnapshot'])->name('risk-history.capture');
        Route::get('risk-history', [\App\Http\Controllers\Academics\DeanOsController::class, 'riskSettings'])->name('risk-history.index');
        Route::post('risk-mitigation', [\App\Http\Controllers\Academics\DeanOsController::class, 'storeRiskMitigation'])->name('risk-mitigation.store');
        Route::get('approval-cockpit', [\App\Http\Controllers\Academics\DeanOsController::class, 'approvalCockpit'])->name('approval-cockpit.index');
        Route::patch('approval-cockpit/{item}', [\App\Http\Controllers\Academics\DeanOsController::class, 'decideApproval'])->name('approval-cockpit.decide');
        Route::get('faculty-workload', [\App\Http\Controllers\Academics\DeanOsController::class, 'operatingSurface'])->defaults('surface', 'faculty-workload')->name('faculty-workload.index');
        Route::get('faculty-performance', [\App\Http\Controllers\Academics\DeanOsController::class, 'operatingSurface'])->defaults('surface', 'faculty-performance')->name('faculty-performance.index');
        Route::get('mentoring-governance', [\App\Http\Controllers\Academics\DeanOsController::class, 'operatingSurface'])->defaults('surface', 'mentoring-governance')->name('mentoring-governance.index');
        Route::get('student-success', [\App\Http\Controllers\Academics\DeanOsController::class, 'operatingSurface'])->defaults('surface', 'student-success')->name('student-success.index');
        Route::get('interventions', [\App\Http\Controllers\Academics\DeanOsController::class, 'operatingSurface'])->defaults('surface', 'interventions')->name('interventions.index');
        Route::get('parent-escalations', [\App\Http\Controllers\Academics\DeanOsController::class, 'operatingSurface'])->defaults('surface', 'student-success')->name('parent-escalations.index');
        Route::get('curriculum-governance', [\App\Http\Controllers\Academics\DeanOsController::class, 'operatingSurface'])->defaults('surface', 'curriculum-governance')->name('curriculum-governance.index');
        Route::get('syllabus-versions', [\App\Http\Controllers\Academics\DeanOsController::class, 'operatingSurface'])->defaults('surface', 'syllabus-versions')->name('syllabus-versions.index');
        Route::get('compliance-mapping', [\App\Http\Controllers\Academics\DeanOsController::class, 'operatingSurface'])->defaults('surface', 'compliance-mapping')->name('compliance-mapping.index');
        Route::get('exam-readiness', [\App\Http\Controllers\Academics\DeanOsController::class, 'operatingSurface'])->defaults('surface', 'exam-readiness')->name('exam-readiness.index');
        Route::get('quality-command', [\App\Http\Controllers\Academics\DeanOsController::class, 'operatingSurface'])->defaults('surface', 'quality-command')->name('quality-command.index');
        Route::get('audit-evidence', [\App\Http\Controllers\Academics\DeanOsController::class, 'operatingSurface'])->defaults('surface', 'audit-evidence')->name('audit-evidence.index');
        Route::get('obe-action-plans', [\App\Http\Controllers\Academics\DeanOsController::class, 'operatingSurface'])->defaults('surface', 'obe-action-plans')->name('obe-action-plans.index');
        Route::get('induction', [\App\Http\Controllers\Academics\DeanOsController::class, 'operatingSurface'])->defaults('surface', 'induction')->name('induction.index');
        Route::get('onboarding', [\App\Http\Controllers\Academics\DeanOsController::class, 'operatingSurface'])->defaults('surface', 'onboarding')->name('onboarding.index');
        Route::get('analytics', [\App\Http\Controllers\Academics\DeanOsController::class, 'analytics'])->name('analytics.index');
        Route::get('scheduled-reports', [\App\Http\Controllers\Academics\DeanOsController::class, 'analytics'])->name('scheduled-reports.index');
        Route::patch('scheduled-reports/{pack}/generate', [\App\Http\Controllers\Academics\DeanOsController::class, 'generateReportPack'])->name('scheduled-reports.generate');
        Route::post('saved-views', [\App\Http\Controllers\Academics\DeanOsController::class, 'storeSavedView'])->name('saved-views.store');
        Route::get('planning-calendar', [\App\Http\Controllers\Academics\DeanOsController::class, 'planningCalendar'])->name('planning-calendar.index');
        Route::get('policy-audit', [\App\Http\Controllers\Academics\DeanOsController::class, 'policyAudit'])->name('policy-audit.index');
    });
    Route::get('governance', [\App\Http\Controllers\Academics\GovernanceController::class, 'index'])->name('governance.index');
    Route::get('hierarchy', [\App\Http\Controllers\Academics\GovernanceController::class, 'hierarchy'])->name('hierarchy.index');
    Route::get('scopes', [\App\Http\Controllers\Academics\GovernanceController::class, 'scopes'])->name('scopes.index');
    Route::post('scopes', [\App\Http\Controllers\Academics\GovernanceController::class, 'storeScope'])->name('scopes.store');
    Route::patch('scopes/{scope}/deactivate', [\App\Http\Controllers\Academics\GovernanceController::class, 'deactivateScope'])->name('scopes.deactivate');
    Route::get('permission-matrix', [\App\Http\Controllers\Academics\GovernanceController::class, 'permissionMatrix'])->name('permission-matrix.index');
});


// -- Academic routes ---------------------------------------------------------
Route::middleware(['auth', 'role:dean_academics|program_chair|exam_cell|hod|accounts_officer|admin'])->prefix('academic')->name('academic.')->group(function () {
    // Phase 5: Academic Transcripts
    Route::middleware('department.feature:ACAD,academic.reports')->group(function () {
        Route::get('transcripts', [Academic\TranscriptController::class, 'index'])->name('transcripts.index');
        Route::get('transcripts/{student}/pdf', [Academic\TranscriptController::class, 'generatePdf'])->name('transcripts.pdf');
        Route::get('transcripts/{student}', [Academic\TranscriptController::class, 'show'])->name('transcripts.show');
    });

    // B2: Term Promotions
    Route::middleware('department.feature:ACAD,academic.approvals')->group(function () {
        Route::get('term-promotions', [Academic\TermPromotionController::class, 'index'])->name('term-promotions.index');
        Route::post('term-promotions/generate', [Academic\TermPromotionController::class, 'generate'])->name('term-promotions.generate');
        Route::get('term-promotions/{termPromotion}', [Academic\TermPromotionController::class, 'show'])->name('term-promotions.show');
        Route::get('term-promotions/{termPromotion}/edit', [Academic\TermPromotionController::class, 'edit'])->name('term-promotions.edit');
        Route::put('term-promotions/{termPromotion}', [Academic\TermPromotionController::class, 'update'])->name('term-promotions.update');
        Route::post('term-promotions/{termPromotion}/approve', [Academic\TermPromotionController::class, 'approve'])->name('term-promotions.approve');
        Route::post('term-promotions/{termPromotion}/reject', [Academic\TermPromotionController::class, 'reject'])->name('term-promotions.reject');
        Route::post('term-promotions/bulk-approve', [Academic\TermPromotionController::class, 'bulkApprove'])->name('term-promotions.bulk-approve');
    });

    // B3: Scholarships
    Route::resource('scholarships', Academic\ScholarshipController::class);

    // B3: Fee Demands - static routes BEFORE resource wildcard
    Route::post('fee-demands/generate-demands', [Academic\FeeDemandController::class, 'generateDemands'])->name('fee-demands.generate');
    Route::post('fee-demands/apply-penalties', [Academic\FeeDemandController::class, 'applyPenalties'])->name('fee-demands.apply-penalties');
    Route::resource('fee-demands', Academic\FeeDemandController::class);
    Route::post('fee-demands/{feeDemand}/mark-paid', [Academic\FeeDemandController::class, 'markAsPaid'])->name('fee-demands.mark-paid');

    // B5: Academic Calendar
    Route::middleware('department.feature:ACAD,academic.dashboard')->group(function () {
        Route::resource('academic-calendars', Academic\AcademicCalendarController::class);
        Route::get('academic-calendars-events', [Academic\AcademicCalendarController::class, 'getEvents'])->name('academic-calendars.events');
    });

    // Phase 3: Curriculum Changes
    Route::middleware('department.feature:ACAD,academic.curriculum')->group(function () {
        Route::get('curriculum-changes', [Academic\CurriculumChangeController::class, 'index'])->name('curriculum-changes.index');
        Route::get('curriculum-changes/create', [Academic\CurriculumChangeController::class, 'create'])->name('curriculum-changes.create');
        Route::post('curriculum-changes', [Academic\CurriculumChangeController::class, 'store'])->name('curriculum-changes.store');
        Route::get('curriculum-changes/{curriculumChange}', [Academic\CurriculumChangeController::class, 'show'])->name('curriculum-changes.show');
        Route::post('curriculum-changes/{curriculumChange}/approve', [Academic\CurriculumChangeController::class, 'approve'])->name('curriculum-changes.approve');
        Route::post('curriculum-changes/{curriculumChange}/reject', [Academic\CurriculumChangeController::class, 'reject'])->name('curriculum-changes.reject');
    });

    // OBE Framework (Course Outcomes, Program Outcomes, CO-PO Matrix, Attainment)
    Route::prefix('obe')->name('obe.')->middleware('department.feature:ACAD,academic.curriculum')->group(function () {
        // Course Outcomes
        Route::get('course-outcomes',             [Academic\ObeController::class, 'coIndex'])->name('co.index');
        Route::post('course-outcomes',            [Academic\ObeController::class, 'coStore'])->name('co.store');
        Route::put('course-outcomes/{co}',        [Academic\ObeController::class, 'coUpdate'])->name('co.update');
        Route::delete('course-outcomes/{co}',     [Academic\ObeController::class, 'coDestroy'])->name('co.destroy');
        // Program Outcomes
        Route::get('program-outcomes',            [Academic\ObeController::class, 'poIndex'])->name('po.index');
        Route::post('program-outcomes',           [Academic\ObeController::class, 'poStore'])->name('po.store');
        Route::put('program-outcomes/{po}',       [Academic\ObeController::class, 'poUpdate'])->name('po.update');
        Route::delete('program-outcomes/{po}',    [Academic\ObeController::class, 'poDestroy'])->name('po.destroy');
        Route::post('program-specific-outcomes',  [Academic\ObeController::class, 'psoStore'])->name('pso.store');
        Route::delete('program-specific-outcomes/{pso}', [Academic\ObeController::class, 'psoDestroy'])->name('pso.destroy');
        // CO-PO Matrix
        Route::get('matrix',                      [Academic\ObeController::class, 'matrixIndex'])->name('matrix');
        Route::post('matrix/save',                [Academic\ObeController::class, 'matrixSave'])->name('matrix.save');
        // Attainment
        Route::get('attainment',                  [Academic\ObeController::class, 'attainmentIndex'])->name('attainment');
        Route::post('attainment/recalculate',     [Academic\ObeController::class, 'recalculate'])->name('attainment.recalculate');
        // Surveys (indirect assessment)
        Route::get('surveys',                     [Academic\ObeController::class, 'surveyIndex'])->name('surveys.index');
        Route::post('surveys',                    [Academic\ObeController::class, 'surveyStore'])->name('surveys.store');
        Route::post('surveys/{survey}/toggle',    [Academic\ObeController::class, 'surveyToggle'])->name('surveys.toggle');
    });
});


// -- Dean Academics ----------------------------------------------------------
Route::middleware(['auth', 'role:dean_academics|admin'])->prefix('dean')->name('dean.')->group(function () {
    Route::get('dashboard',  [Departmental\DeanController::class, 'dashboard'])->name('dashboard');
    Route::get('programs',   [Departmental\DeanController::class, 'programs'])->name('programs');
    Route::get('students',   [Departmental\DeanController::class, 'students'])->name('students');
    Route::get('academics',  [Departmental\DeanController::class, 'academics'])->name('academics');
    Route::get('attendance', [Departmental\DeanController::class, 'attendance'])->name('attendance');
    // P5-5: Approval Workflow Routes for Dean
    Route::get('approvals', [Departmental\DeanController::class, 'approvals'])->name('approvals');
    Route::post('approvals/{approval}/approve', [Departmental\DeanController::class, 'approve'])->name('approve');
    Route::post('approvals/{approval}/reject', [Departmental\DeanController::class, 'reject'])->name('reject');
});

// -- Program Chair / HOD ------------------------------------------------------
Route::middleware(['auth', 'role:program_chair|hod|dean_academics|admin'])->prefix('program-chair')->name('chair.')->group(function () {
    Route::get('dashboard',  [Departmental\ProgramChairController::class, 'dashboard'])
        ->middleware('department.feature:ACAD,academic.dashboard')
        ->name('dashboard');
    Route::get('students', [Departmental\ProgramChairController::class, 'students'])
        ->middleware('department.feature:ACAD,academic.reports')
        ->name('students');
    Route::get('curriculum', [Departmental\ProgramChairController::class, 'curriculum'])
        ->middleware('department.feature:ACAD,academic.curriculum')
        ->name('curriculum');
    Route::get('timetable', [Departmental\ProgramChairController::class, 'timetable'])
        ->middleware('department.feature:ACAD,academic.timetable')
        ->name('timetable');
    Route::get('exams', [Departmental\ProgramChairController::class, 'exams'])
        ->middleware('department.feature:ACAD,academic.reports')
        ->name('exams');
    Route::middleware('department.feature:ACAD,academic.reports')->group(function () {
        Route::get('workload', [Departmental\ProgramChairController::class, 'workloadReport'])->name('workload');
        Route::get('workload/export', [Departmental\ProgramChairController::class, 'workloadExport'])->name('workload.export');
        Route::get('capacity', [Departmental\ProgramChairController::class, 'capacityReport'])->name('capacity');
        Route::get('rooms', [Departmental\ProgramChairController::class, 'roomUtilization'])->name('rooms');
        Route::get('constraints', [Departmental\ProgramChairController::class, 'softConstraints'])->name('constraints');
        Route::get('load-balance', [Departmental\ProgramChairController::class, 'loadBalance'])->name('load-balance');
        Route::get('analytics', [Departmental\ProgramChairController::class, 'analytics'])->name('analytics');
    });
    // P5-5: Approval Workflow Routes for Program Chair
    Route::middleware('department.feature:ACAD,academic.approvals')->group(function () {
        Route::get('approvals', [Departmental\ProgramChairController::class, 'approvals'])->name('approvals');
        Route::post('approvals/{approval}/approve', [Departmental\ProgramChairController::class, 'approve'])->name('approve');
        Route::post('approvals/{approval}/reject', [Departmental\ProgramChairController::class, 'reject'])->name('reject');
    });

    // PMC Sprint - Curriculum Management
    Route::middleware('department.feature:ACAD,academic.curriculum')->group(function () {
        Route::get('curriculum/assignments', [Departmental\PmcCurriculumController::class, 'assignments'])->name('curriculum.assignments');
        Route::post('curriculum/assign-faculty', [Departmental\PmcCurriculumController::class, 'assignFaculty'])->name('curriculum.assign-faculty');
        Route::delete('curriculum/assignments/{assignment}', [Departmental\PmcCurriculumController::class, 'unassignFaculty'])->name('curriculum.unassign-faculty');
        Route::get('curriculum/electives', [Departmental\PmcCurriculumController::class, 'electives'])->name('curriculum.electives');
        Route::post('curriculum/electives/window', [Departmental\PmcCurriculumController::class, 'createWindow'])->name('curriculum.electives.window');
        Route::post('curriculum/electives/window/{window}/status', [Departmental\PmcCurriculumController::class, 'updateWindowStatus'])->name('curriculum.electives.window.status');
        Route::get('curriculum/assessment', [Departmental\PmcCurriculumController::class, 'assessmentSetup'])->name('curriculum.assessment');
        Route::post('curriculum/assessment', [Departmental\PmcCurriculumController::class, 'saveAssessmentComponent'])->name('curriculum.assessment.save');
        Route::get('curriculum', [Departmental\PmcCurriculumController::class, 'index'])->name('curriculum.index');
        Route::post('curriculum/subject', [Departmental\PmcCurriculumController::class, 'addSubject'])->name('curriculum.add-subject');
        Route::delete('curriculum/subject/{programSubject}', [Departmental\PmcCurriculumController::class, 'removeSubject'])->name('curriculum.remove-subject');
    });

    // PMC Sprint - Timetable
    Route::middleware('department.feature:ACAD,academic.timetable')->group(function () {
        Route::get('timetable/builder', [Departmental\PmcTimetableController::class, 'builder'])->name('timetable.builder');
        Route::post('timetable/slot', [Departmental\PmcTimetableController::class, 'saveSlot'])->name('timetable.save-slot');
        Route::post('timetable/publish', [Departmental\PmcTimetableController::class, 'publish'])->name('timetable.publish');
        Route::post('timetable/check-conflict', [Departmental\PmcTimetableController::class, 'checkConflict'])->name('timetable.check-conflict');
        Route::get('timetable/substitutions', [Departmental\PmcTimetableController::class, 'substitutions'])->name('timetable.substitutions');
        Route::post('timetable/substitutions', [Departmental\PmcTimetableController::class, 'createSubstitution'])->name('timetable.substitutions.store');
        Route::get('timetable/availability', [Departmental\PmcTimetableController::class, 'teacherAvailability'])->name('timetable.availability');
        Route::post('timetable/availability', [Departmental\PmcTimetableController::class, 'saveAvailability'])->name('timetable.availability.save');
        Route::get('timetable/import', [Departmental\PmcTimetableController::class, 'importForm'])->name('timetable.import');
        Route::post('timetable/validate-import', [Departmental\PmcTimetableController::class, 'validateImport'])->name('timetable.validate-import');
        Route::post('timetable/do-import', [Departmental\PmcTimetableController::class, 'doImport'])->name('timetable.do-import');
        Route::get('timetable/download-sample', [Departmental\PmcTimetableController::class, 'downloadSample'])->name('timetable.download-sample');
        Route::get('timetable/copy', [Departmental\PmcTimetableController::class, 'copyForm'])->name('timetable.copy');
        Route::post('timetable/preview-copy', [Departmental\PmcTimetableController::class, 'previewCopy'])->name('timetable.preview-copy');
        Route::post('timetable/execute-copy', [Departmental\PmcTimetableController::class, 'executeCopy'])->name('timetable.execute-copy');
        Route::post('timetable/export-batch-pdf', [Departmental\PmcTimetableController::class, 'exportBatchPdf'])->name('timetable.export-batch-pdf');
        Route::post('timetable/export-teacher-pdf', [Departmental\PmcTimetableController::class, 'exportTeacherPdf'])->name('timetable.export-teacher-pdf');
        Route::post('timetable/check-teacher-workload', [Departmental\PmcTimetableController::class, 'checkTeacherWorkload'])->name('timetable.check-teacher-workload');
        Route::get('timetable/teacher-workload-list', [Departmental\PmcTimetableController::class, 'teacherWorkloadList'])->name('timetable.teacher-workload-list');
        Route::post('timetable/suggest-teachers', [Departmental\PmcTimetableController::class, 'suggestTeachers'])->name('timetable.suggest-teachers');
        Route::post('timetable/check-slot-availability', [Departmental\PmcTimetableController::class, 'checkSlotAvailability'])->name('timetable.check-slot-availability');
        Route::get('timetable/available-slots', [Departmental\PmcTimetableController::class, 'getAvailableSlots'])->name('timetable.available-slots');
        Route::post('timetable/slot-suggestions', [Departmental\PmcTimetableController::class, 'getSuggestions'])->name('timetable.slot-suggestions');
        Route::post('timetable/auto-schedule', [Departmental\PmcTimetableController::class, 'suggestAutoSchedule'])->name('timetable.auto-schedule');
        Route::post('timetable/accept-auto-schedule', [Departmental\PmcTimetableController::class, 'acceptAutoScheduleSuggestions'])->name('timetable.accept-auto-schedule');
    });

    // PMC Sprint - Student oversight
    Route::middleware('department.feature:ACAD,academic.approvals')->group(function () {
        Route::get('students/at-risk', [Departmental\PmcStudentController::class, 'atRisk'])->name('students.at-risk');
        Route::get('students/at-risk/export', [Departmental\PmcStudentController::class, 'exportAtRisk'])->name('students.at-risk.export');
        Route::get('students/mentors', [Departmental\PmcStudentController::class, 'mentors'])->name('students.mentors');
        Route::post('students/mentors/assign', [Departmental\PmcStudentController::class, 'assignMentor'])->name('students.mentors.assign');
        Route::post('students/mentors/bulk', [Departmental\PmcStudentController::class, 'bulkAssignMentor'])->name('students.mentors.bulk');
        Route::get('students/leaves', [Departmental\PmcStudentController::class, 'leaves'])->name('students.leaves');
        Route::post('students/leaves/{leave}/approve', [Departmental\PmcStudentController::class, 'approveLeave'])->name('students.leaves.approve');
        Route::post('students/leaves/{leave}/reject', [Departmental\PmcStudentController::class, 'rejectLeave'])->name('students.leaves.reject');
        Route::get('students/condonations', [Departmental\PmcStudentController::class, 'condonations'])->name('students.condonations');
        Route::post('students/condonations/{condonation}/approve', [Departmental\PmcStudentController::class, 'approveCondonation'])->name('students.condonations.approve');
        Route::post('students/condonations/{condonation}/reject', [Departmental\PmcStudentController::class, 'rejectCondonation'])->name('students.condonations.reject');
        Route::get('students/grievances', [Departmental\PmcStudentController::class, 'grievances'])->name('students.grievances');
        Route::post('students/grievances/{grievance}', [Departmental\PmcStudentController::class, 'updateGrievance'])->name('students.grievances.update');
        Route::get('students/elective-override', [Departmental\PmcStudentController::class, 'electiveOverride'])->name('students.elective-override');
        Route::post('students/elective-override/{enrollment}', [Departmental\PmcStudentController::class, 'changeElective'])->name('students.elective-override.change');
        Route::get('students/promotions', [Departmental\PmcStudentController::class, 'promotions'])->name('students.promotions');
    });

    // PMC Sprint 2 - Faculty oversight
    Route::middleware('department.feature:ACAD,academic.reports')->group(function () {
        Route::get('faculty/workload', [Departmental\PmcFacultyController::class, 'workload'])->name('faculty.workload');
        Route::get('faculty/marks-tracker', [Departmental\PmcFacultyController::class, 'marksTracker'])->name('faculty.marks-tracker');
        Route::get('faculty/course-delivery', [Departmental\PmcFacultyController::class, 'courseDelivery'])->name('faculty.course-delivery');
        Route::get('faculty/feedback', [Departmental\PmcFacultyController::class, 'feedbackSummary'])->name('faculty.feedback');
    });

    // PMC Sprint 2 - Reports
    Route::middleware('department.feature:ACAD,academic.reports')->group(function () {
        Route::get('reports/subject-performance', [Departmental\PmcReportsController::class, 'subjectPerformance'])->name('reports.subject-performance');
        Route::get('reports/attendance-defaulters', [Departmental\PmcReportsController::class, 'attendanceDefaulters'])->name('reports.attendance-defaulters');
        Route::get('reports/term-summary', [Departmental\PmcReportsController::class, 'termSummary'])->name('reports.term-summary');
    });
});

// -- HOD (Head of Department) -------------------------------------------------
Route::middleware(['auth', 'role:hod|admin|director|dean_academics'])->prefix('hod')->name('hod.')->group(function () {
    Route::get('dashboard', [Departmental\HodController::class, 'dashboard'])
        ->middleware('department.feature:ACAD,academic.dashboard')
        ->name('dashboard');
    Route::middleware('department.feature:ACAD,academic.approvals')->group(function () {
        Route::get('approvals', [Departmental\HodController::class, 'approvals'])->name('approvals');
        Route::post('approvals/{approval}/approve', [Departmental\HodController::class, 'approve'])->name('approve');
        Route::post('approvals/{approval}/reject', [Departmental\HodController::class, 'reject'])->name('reject');
        Route::get('grievances', [Departmental\GrievanceManagementController::class, 'index'])->name('grievances.index');
        Route::get('grievances/{grievance}', [Departmental\GrievanceManagementController::class, 'show'])->name('grievances.show');
        Route::post('grievances/{grievance}/resolve', [Departmental\GrievanceManagementController::class, 'resolve'])->name('grievances.resolve');
        Route::post('grievances/{grievance}/escalate', [Departmental\GrievanceManagementController::class, 'escalate'])->name('grievances.escalate');
    });
    // Faculty management
    Route::middleware('department.feature:ACAD,academic.reports')->group(function () {
        Route::get('faculty', [Departmental\HodController::class, 'facultyRoster'])->name('faculty.roster');
        Route::get('faculty/roster', [Departmental\HodController::class, 'facultyRoster'])->name('faculty.roster.alias');
        Route::get('faculty/workload', [Departmental\HodController::class, 'facultyWorkload'])->name('faculty.workload');
        Route::get('department-performance', [Departmental\HodController::class, 'departmentPerformance'])->name('department-performance');
    });
    Route::middleware('department.feature:ACAD,academic.approvals')->group(function () {
        Route::get('leaves', [Departmental\HodController::class, 'leaves'])->name('leaves');
        Route::post('leaves/{leave}/review', [Departmental\HodController::class, 'reviewLeave'])->name('leaves.review');
    });
});

// -- Exam Cell ----------------------------------------------------------------
Route::middleware(['auth', 'role:exam_cell|dean_academics|admin'])->prefix('exam-cell')->name('exam-cell.')->group(function () {
    Route::get('dashboard', [Departmental\ExamCellController::class, 'dashboard'])
        ->middleware('department.feature:EXAM,exam.dashboard')
        ->name('dashboard');
    Route::middleware('department.feature:EXAM,exam.scheduling')->group(function () {
        Route::get('exams', [Departmental\ExamCellController::class, 'exams'])->name('exams');
        Route::get('exams/create', [Departmental\ExamCellController::class, 'createExam'])->name('exams.create');
        Route::post('exams', [Departmental\ExamCellController::class, 'storeExam'])->name('exams.store');
        Route::get('exams/{exam}/edit', [Departmental\ExamCellController::class, 'editExam'])->name('exams.edit');
        Route::put('exams/{exam}', [Departmental\ExamCellController::class, 'updateExam'])->name('exams.update');
        Route::delete('exams/{exam}', [Departmental\ExamCellController::class, 'destroyExam'])->name('exams.destroy');
    });
    Route::middleware('department.feature:EXAM,exam.marks_results')->group(function () {
        Route::get('results', [Departmental\ExamCellController::class, 'results'])->name('results');
        Route::get('results/{exam}/grade-sheet', [Departmental\ExamCellController::class, 'gradeSheet'])->name('grade-sheet');
        Route::post('results/{exam}/save-marks', [Departmental\ExamCellController::class, 'saveMarks'])->name('save-marks');
        Route::post('results/{exam}/publish', [Departmental\ExamCellController::class, 'publishResults'])->name('publish');
    });
    Route::middleware('department.feature:EXAM,exam.hall_tickets')->group(function () {
        Route::get('hall-tickets', [Departmental\ExamCellController::class, 'hallTickets'])->name('hall-tickets');
        Route::patch('registrations/{registration}', [Departmental\ExamCellController::class, 'reviewRegistration'])->name('registrations.review');
        Route::get('hall-tickets/{exam}/{student}/download', [Departmental\ExamCellController::class, 'downloadHallTicket'])->name('hall-ticket.download');
    });
    Route::middleware('department.feature:EXAM,exam.appeals_anomalies')->group(function () {
        Route::get('marks-appeals', [Departmental\ExamCellController::class, 'marksAppeals'])->name('marks-appeals');
        Route::post('marks-appeals/{appeal}/review', [Departmental\ExamCellController::class, 'reviewAppeal'])->name('marks-appeals.review');
        Route::get('anomalies', [Departmental\ExamAnomalyController::class, 'index'])->name('anomalies.index');
        Route::get('anomalies/create', [Departmental\ExamAnomalyController::class, 'create'])->name('anomalies.create');
        Route::post('anomalies', [Departmental\ExamAnomalyController::class, 'store'])->name('anomalies.store');
        Route::get('anomalies/{anomalyLog}', [Departmental\ExamAnomalyController::class, 'show'])->name('anomalies.show');
        Route::post('anomalies/{anomalyLog}/resolve', [Departmental\ExamAnomalyController::class, 'resolve'])->name('anomalies.resolve');
    });
});


// -- Director -----------------------------------------------------------------
Route::middleware(['auth', 'role:director|admin'])->prefix('director')->name('director.')->group(function () {
    Route::get('dashboard', [Departmental\DirectorController::class, 'dashboard'])->name('dashboard');
    Route::get('programs',  [Departmental\DirectorController::class, 'programs'])->name('programs');
    Route::get('reports',   [Departmental\DirectorController::class, 'reports'])->name('reports');
});

// -- Claude Analysis (with prompt caching) -----------------------------------
Route::middleware(['auth', 'role:admin|dean_academics|program_chair'])->prefix('api')->group(function () {
    Route::get('claude/student/{studentId}/analyze', [ClaudeAnalysisController::class, 'analyzeStudent'])->name('claude.analyze-student');
    Route::get('claude/applicant/{applicantId}/evaluate', [ClaudeAnalysisController::class, 'evaluateAdmission'])->name('claude.evaluate-applicant');
    Route::post('claude/curriculum/{programId}/recommend', [ClaudeAnalysisController::class, 'recommendCurriculum'])->name('claude.recommend-curriculum');
});


