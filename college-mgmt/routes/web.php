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

// ── Public Application Status Tracker ─────────────────────────────────────
Route::get('/track', [StatusTrackerController::class, 'index'])->name('public.status-tracker.index');
Route::post('/track', [StatusTrackerController::class, 'track'])->name('public.status-tracker.track');
Route::post('/admission/gateway/webhook', [Admission\GatewayPaymentController::class, 'webhook'])->name('admission.gateway.webhook');

// ── Public Application Routes ──────────────────────────────────────────────
Route::get('/apply', [ApplyController::class, 'index'])->name('apply');
Route::get('/apply/{program}', [ApplyController::class, 'show'])->name('apply.program');
Route::post('/apply/{program}', [ApplyController::class, 'register'])->name('apply.program.register');

// ── Applicant Routes ───────────────────────────────────────────────────────
Route::prefix('applicant')->name('applicant.')->middleware(['auth', 'role:applicant|admin'])->group(function () {
    Route::get('dashboard', [ApplicantDashboard::class, 'index'])->name('dashboard');
    Route::get('application', [ApplicantApplication::class, 'show'])->name('application.show');
    Route::get('checklist', [\App\Http\Controllers\Applicant\ChecklistController::class, 'index'])
        ->middleware('department.feature:ADM,admission.applicant_checklist')
        ->name('checklist');
    // Static route BEFORE parameterized
    Route::post('application/submit', [ApplicantApplication::class, 'submit'])->name('application.submit');
    Route::post('application/{section}', [ApplicantApplication::class, 'saveSection'])->name('application.section');
    Route::get('documents', [ApplicantDocument::class, 'index'])->name('documents.index');
    Route::post('documents/{requiredDocument}', [ApplicantDocument::class, 'store'])->name('documents.store');
    Route::delete('documents/{document}', [ApplicantDocument::class, 'destroy'])->name('documents.destroy');
    Route::get('status', [ApplicantStatus::class, 'index'])->name('status');
    Route::get('registration-fee', [ApplicantRegistrationFee::class, 'show'])->name('registration-fee.show');
    Route::post('registration-fee', [ApplicantRegistrationFee::class, 'store'])->name('registration-fee.store');
    // Fees - static route before parameterized
    Route::get('fees', [ApplicantPayment::class, 'index'])->name('fees.index');
    Route::get('fees/payment/{payment}', [ApplicantPayment::class, 'show'])->name('fees.show');
    Route::post('fees/payment/{payment}/gateway', [\App\Http\Controllers\Applicant\GatewayPaymentController::class, 'initiate'])
        ->middleware('department.feature:ADM,admission.gateway_payments')
        ->name('fees.gateway.initiate');
    Route::post('fees/{installment}', [ApplicantPayment::class, 'store'])->name('fees.store');
    // Offer Letters
    Route::get('offer-letters', [\App\Http\Controllers\Applicant\OfferLetterController::class, 'index'])->name('offer-letters.index');
    Route::get('offer-letters/{offerLetter}/pdf', [\App\Http\Controllers\Applicant\OfferLetterController::class, 'downloadPdf'])->name('offer-letters.pdf');
    Route::get('offer-letters/{offerLetter}', [\App\Http\Controllers\Applicant\OfferLetterController::class, 'show'])->name('offer-letters.show');
    Route::post('offer-letters/{offerLetter}/accept', [\App\Http\Controllers\Applicant\OfferLetterController::class, 'accept'])->name('offer-letters.accept');
    Route::post('offer-letters/{offerLetter}/decline', [\App\Http\Controllers\Applicant\OfferLetterController::class, 'decline'])->name('offer-letters.decline');
    Route::get('notifications', [\App\Http\Controllers\Applicant\NotificationPreferenceController::class, 'edit'])->name('notifications.edit');
    Route::put('notifications', [\App\Http\Controllers\Applicant\NotificationPreferenceController::class, 'update'])->name('notifications.update');
    Route::get('admission-operations', [\App\Http\Controllers\Applicant\AdmissionOperationsController::class, 'index'])->name('admission-operations.index');
    Route::post('admission-operations/reschedule', [\App\Http\Controllers\Applicant\AdmissionOperationsController::class, 'requestReschedule'])->name('admission-operations.reschedule');
    Route::post('admission-operations/consent', [\App\Http\Controllers\Applicant\AdmissionOperationsController::class, 'consent'])->name('admission-operations.consent');
});

// ── Notifications (all authenticated users) ────────────────────────────────
Route::middleware(['auth'])->prefix('notifications')->name('notifications.')->group(function () {
    Route::get('/', [NotificationController::class, 'index'])->name('index');
    Route::get('/unread-count', [NotificationController::class, 'getUnreadCount'])->name('unread-count');
    Route::post('/mark-all-read', [NotificationController::class, 'markAllAsRead'])->name('mark-all-read');
    Route::get('/{notification}', [NotificationController::class, 'show'])->name('show');
    Route::post('/{notification}/mark-read', [NotificationController::class, 'markAsRead'])->name('mark-read');
    Route::post('/{notification}/delete', [NotificationController::class, 'delete'])->name('delete');
});

Route::get('/', function () {
    if (auth()->check()) {
        return DashboardRedirect::forUser(auth()->user());
    }
    return view('welcome');
});

// Compatibility alias so Breeze tests and legacy redirects still work
Route::get('/dashboard', function () {
    return DashboardRedirect::forUser(auth()->user());
})->middleware(['auth'])->name('dashboard');

Route::middleware(['auth'])->prefix('admission/partners')->name('admission.partner-portal.')->group(function () {
    Route::get('dashboard', [Admission\PartnerController::class, 'portalDashboard'])->name('dashboard');
    Route::get('leads', [Admission\PartnerController::class, 'portalLeads'])->name('leads');
    Route::post('leads', [Admission\PartnerController::class, 'portalSubmitLead'])->name('leads.store');
});

Route::middleware(['auth'])->prefix('department-governance')->name('department-governance.')->group(function () {
    Route::get('/', [\App\Http\Controllers\DepartmentGovernanceController::class, 'index'])->name('index');
    Route::post('departments/{department}/features', [\App\Http\Controllers\DepartmentGovernanceController::class, 'updateFeature'])->name('features.update');
    Route::post('impersonation/{member}/start', [\App\Http\Controllers\DepartmentGovernanceController::class, 'startImpersonation'])->name('impersonation.start');
    Route::post('impersonation/stop', [\App\Http\Controllers\DepartmentGovernanceController::class, 'stopImpersonation'])->name('impersonation.stop');
});

Route::middleware(['auth'])->prefix('department-hierarchy')->name('department-hierarchy.')->group(function () {
    Route::get('/', [Admin\DepartmentHierarchyController::class, 'index'])->name('index');
    Route::post('/roles', [Admin\DepartmentHierarchyController::class, 'storeRole'])->name('roles.store');
    Route::post('/teams', [Admin\DepartmentHierarchyController::class, 'storeTeam'])->name('teams.store');
    Route::post('/members', [Admin\DepartmentHierarchyController::class, 'storeMember'])->name('members.store');
    Route::patch('/roles/{role}/deactivate', [Admin\DepartmentHierarchyController::class, 'deactivateRole'])->name('roles.deactivate');
    Route::patch('/teams/{team}/deactivate', [Admin\DepartmentHierarchyController::class, 'deactivateTeam'])->name('teams.deactivate');
    Route::patch('/members/{member}/deactivate', [Admin\DepartmentHierarchyController::class, 'deactivateMember'])->name('members.deactivate');
});

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

// ── Admin routes ────────────────────────────────────────────────────────────
Route::prefix('admin')->name('admin.')->middleware(['auth', 'role:admin|dean_academics|program_chair|exam_cell|hod|accounts_officer|cmc|director'])->group(function () {
    Route::get('dashboard', [Admin\DashboardController::class, 'index'])->name('dashboard');

    Route::resource('departments',   Admin\DepartmentController::class);
    Route::resource('courses',       Admin\CourseController::class);

    // Academic Structure - Programs, Batches, Terms, Specializations
    Route::resource('programs', Admin\ProgramController::class);
    Route::resource('batches', Admin\BatchController::class);
    Route::post('terms', [Admin\TermController::class, 'store'])->name('terms.store');
    Route::put('terms/{term}', [Admin\TermController::class, 'update'])->name('terms.update');
    Route::delete('terms/{term}', [Admin\TermController::class, 'destroy'])->name('terms.destroy');
    Route::patch('terms/{term}/set-current', [Admin\TermController::class, 'setCurrent'])->name('terms.set-current');
    Route::resource('programs.specializations', Admin\SpecializationController::class)->shallow()->only(['store', 'destroy']);
    Route::resource('subjects',      Admin\SubjectController::class);
    Route::resource('classrooms',    Admin\ClassroomController::class);
    Route::resource('academic-years', Admin\AcademicYearController::class);
    Route::resource('semesters',     Admin\SemesterController::class);
    Route::resource('teachers',      Admin\TeacherController::class);
    Route::get('students/export', [Admin\StudentController::class, 'export'])->name('students.export');
    Route::resource('students',      Admin\StudentController::class);
    Route::get('document-requests', [Admin\StudentDocumentRequestController::class, 'index'])->name('document-requests.index');
    Route::patch('document-requests/{documentRequest}/approve', [Admin\StudentDocumentRequestController::class, 'approve'])->name('document-requests.approve');
    Route::patch('document-requests/{documentRequest}/reject', [Admin\StudentDocumentRequestController::class, 'reject'])->name('document-requests.reject');
    Route::post('document-requests/{documentRequest}/fulfill', [Admin\StudentDocumentRequestController::class, 'fulfill'])->name('document-requests.fulfill');
    Route::get('document-requests/{documentRequest}/download', [Admin\StudentDocumentRequestController::class, 'download'])->name('document-requests.download');
    Route::get('fee-payment-requests', [Admin\FeeController::class, 'paymentRequests'])->name('fees.payment-requests.index');
    Route::patch('fee-payment-requests/{feePaymentRequest}/verify', [Admin\FeeController::class, 'verifyPaymentRequest'])->name('fees.payment-requests.verify');
    Route::patch('fee-payment-requests/{feePaymentRequest}/reject', [Admin\FeeController::class, 'rejectPaymentRequest'])->name('fees.payment-requests.reject');
    Route::get('fee-payment-requests/{feePaymentRequest}/proof', [Admin\FeeController::class, 'paymentRequestProof'])->name('fees.payment-requests.proof');
    Route::resource('timetable-slots', Admin\TimetableSlotController::class);
    Route::resource('notices',       Admin\NoticeController::class);
    Route::resource('parents',       Admin\ParentController::class);

    // Timetable
    Route::resource('timetable', Admin\TimetableController::class);
    Route::get('timetable-teacher-view', [Admin\TimetableController::class, 'teacherView'])
        ->name('timetable.teacher-view');

    // Attendance
    Route::get('attendance',        [Admin\AttendanceController::class, 'index'])->name('attendance.index');
    Route::get('attendance/entries', [Admin\AttendanceController::class, 'entriesJson'])->name('attendance.entries');
    Route::get('attendance/mark',   [Admin\AttendanceController::class, 'mark'])->name('attendance.mark');
    Route::post('attendance/store', [Admin\AttendanceController::class, 'store'])->name('attendance.store');
    Route::get('attendance/report', [Admin\AttendanceController::class, 'report'])->name('attendance.report');
    Route::get('attendance/export', [Admin\AttendanceController::class, 'export'])->name('attendance.export');

    // Exams
    Route::resource('exams', Admin\ExamController::class);
    Route::get('exams/{exam}/results',  [Admin\ExamController::class, 'enterResults'])->name('exams.results');
    Route::post('exams/{exam}/results', [Admin\ExamController::class, 'saveResults'])->name('exams.results.save');

    // Enrollments
    Route::resource('enrollments', Admin\EnrollmentController::class)->except(['show','edit','update']);
    Route::post('enrollments/bulk', [Admin\EnrollmentController::class, 'bulkEnroll'])->name('enrollments.bulk');

    // Results / Grade Report
    Route::get('results', [Admin\ResultController::class, 'index'])->name('results.index');

    // Admission Configuration (per-program setup)
    Route::get('admission-config/{program}', [Admin\AdmissionConfigController::class, 'index'])->name('admission-config.index');
    Route::get('admission-config/{program}/form', [Admin\AdmissionConfigController::class, 'editFormConfig'])->name('admission-config.form');
    Route::post('admission-config/{program}/form', [Admin\AdmissionConfigController::class, 'updateFormConfig'])->name('admission-config.form.update');
    Route::post('admission-config/{program}/documents', [Admin\AdmissionConfigController::class, 'storeDocument'])->name('admission-config.documents.store');
    Route::put('admission-config/documents/{document}', [Admin\AdmissionConfigController::class, 'updateDocument'])->name('admission-config.documents.update');
    Route::delete('admission-config/documents/{document}', [Admin\AdmissionConfigController::class, 'destroyDocument'])->name('admission-config.documents.destroy');
    Route::post('admission-config/{program}/documents/seed-defaults', [Admin\AdmissionConfigController::class, 'seedDefaultDocuments'])->name('admission-config.documents.seed');
    Route::post('admission-config/{program}/steps', [Admin\AdmissionConfigController::class, 'storeStep'])->name('admission-config.steps.store');
    Route::put('admission-config/steps/{step}', [Admin\AdmissionConfigController::class, 'updateStep'])->name('admission-config.steps.update');
    Route::delete('admission-config/steps/{step}', [Admin\AdmissionConfigController::class, 'destroyStep'])->name('admission-config.steps.destroy');
    Route::post('admission-config/steps/{step}/parameters', [Admin\AdmissionConfigController::class, 'storeParameter'])->name('admission-config.parameters.store');
    Route::delete('admission-config/parameters/{parameter}', [Admin\AdmissionConfigController::class, 'destroyParameter'])->name('admission-config.parameters.destroy');
    Route::post('admission-config/{program}/fee-installments', [Admin\AdmissionConfigController::class, 'storeFeeInstallment'])->name('admission-config.fee.store');
    Route::put('admission-config/fee-installments/{installment}', [Admin\AdmissionConfigController::class, 'updateFeeInstallment'])->name('admission-config.fee.update');
    Route::delete('admission-config/fee-installments/{installment}', [Admin\AdmissionConfigController::class, 'destroyFeeInstallment'])->name('admission-config.fee.destroy');

    Route::get('student-scholarships', [Admin\StudentScholarshipApplicationController::class, 'index'])->name('student-scholarships.index');
    Route::patch('student-scholarships/{application}/shortlist', [Admin\StudentScholarshipApplicationController::class, 'shortlist'])->name('student-scholarships.shortlist');
    Route::patch('student-scholarships/{application}/approve', [Admin\StudentScholarshipApplicationController::class, 'approve'])->name('student-scholarships.approve');
    Route::patch('student-scholarships/{application}/reject', [Admin\StudentScholarshipApplicationController::class, 'reject'])->name('student-scholarships.reject');
    Route::patch('student-scholarships/{application}/disburse', [Admin\StudentScholarshipApplicationController::class, 'disburse'])->name('student-scholarships.disburse');
    Route::get('student-scholarships/{application}/proof', [Admin\StudentScholarshipApplicationController::class, 'downloadProof'])->name('student-scholarships.proof');

    // Applicants (P2 portal)
    Route::get('applicants', [Admin\ApplicantController::class, 'index'])->name('applicants.index');
    Route::post('applicants', [Admin\ApplicantController::class, 'store'])->name('applicants.store');
    Route::get('applicants/{applicant}', [Admin\ApplicantController::class, 'show'])->name('applicants.show');
    Route::post('applicants/{applicant}/notes', [Admin\ApplicantController::class, 'saveNotes'])->name('applicants.notes');

    // Admissions
    Route::resource('admissions', Admin\AdmissionController::class);
    Route::patch('admissions/{admission}/status', [Admin\AdmissionController::class, 'updateStatus'])->name('admissions.status');
    Route::post('admissions/{admission}/convert', [Admin\AdmissionController::class, 'convertToStudent'])->name('admissions.convert');

    // Fees
    Route::get('fees/report', [Admin\FeeController::class, 'report'])->name('fees.report');
    Route::get('fees/export', [Admin\FeeController::class, 'export'])->name('fees.export');
    Route::get('fees/{payment}/receipt', [Admin\FeeController::class, 'receipt'])->name('fees.receipt');
    Route::resource('fees', Admin\FeeController::class);
    Route::get('fees-collect',   [Admin\FeeController::class, 'collectPayment'])->name('fees.collect');
    Route::post('fees-payment',  [Admin\FeeController::class, 'storePayment'])->name('fees.payment');

    // PDF downloads
    Route::get('reports/grade-card/{student}/{semester}', [Admin\ReportController::class, 'gradeCard'])->name('reports.grade-card');
    Route::get('reports/fee-receipt/{payment}',           [Admin\ReportController::class, 'feeReceipt'])->name('reports.fee-receipt');
    Route::get('reports/timetable/{semester}',            [Admin\ReportController::class, 'timetable'])->name('reports.timetable');

    // Leave Management
    Route::get('leaves', [Admin\LeaveController::class, 'index'])->name('leaves.index');
    Route::get('leaves/{leave}', [Admin\LeaveController::class, 'show'])->name('leaves.show');
    Route::patch('leaves/{leave}/approve', [Admin\LeaveController::class, 'approve'])->name('leaves.approve');
    Route::patch('leaves/{leave}/reject', [Admin\LeaveController::class, 'reject'])->name('leaves.reject');
    Route::delete('leaves/{leave}', [Admin\LeaveController::class, 'destroy'])->name('leaves.destroy');

    // Faculty Reports
    Route::get('faculty/workload', [Admin\FacultyReportController::class, 'workload'])->name('faculty.workload');

    // Placement
    Route::get('placements/export', [Admin\PlacementDriveController::class, 'exportPlacements'])->name('placements.export');
    Route::resource('companies', Admin\CompanyController::class);
    Route::resource('placement-drives', Admin\PlacementDriveController::class);
    Route::post('placement-drives/{drive}/apply', [Admin\PlacementDriveController::class, 'apply'])->name('placement-drives.apply');
    Route::patch('placements/{placement}/status', [Admin\PlacementDriveController::class, 'updateApplication'])->name('placements.update-status');

    // Bulk Mail
    Route::get('bulk-mail', [Admin\BulkMailController::class, 'index'])->name('bulk-mail.index');
    Route::get('bulk-mail/count', [Admin\BulkMailController::class, 'previewCount'])->name('bulk-mail.count');
    Route::post('bulk-mail/send', [Admin\BulkMailController::class, 'send'])->name('bulk-mail.send');

    // Email Logs
    Route::get('email-logs', [Admin\EmailLogController::class, 'index'])->name('email-logs.index');

    // Settings
    Route::get('settings', [Admin\SettingsController::class, 'index'])->name('settings');
    Route::get('settings/branding', [Admin\SettingsController::class, 'branding'])->name('settings.branding');
    Route::post('settings/branding', [Admin\SettingsController::class, 'update'])->name('settings.update');
    Route::get('api-docs', [Admin\SettingsController::class, 'apiDocs'])->name('api-docs');

    // Global Search
    Route::get('search', [Admin\SearchController::class, 'index'])->name('search');

    // Phase 8: Institution Analytics
    Route::get('analytics', [Admin\AnalyticsController::class, 'index'])->name('analytics');

    // Phase 5: Student Grievances (admin side)
    Route::get('grievances', [Admin\GrievanceController::class, 'index'])->name('grievances.index');
    Route::get('grievances/{grievance}', [Admin\GrievanceController::class, 'show'])->name('grievances.show');
    Route::patch('grievances/{grievance}', [Admin\GrievanceController::class, 'update'])->name('grievances.update');

    // Activity Log
    Route::get('activity-log', [Admin\ActivityLogController::class, 'index'])->name('activity-log');

    // Consolidated Student Report PDF
    Route::get('students/{student}/report', [Admin\ReportController::class, 'consolidatedReport'])->name('students.report');

    // P9: Role Assignments (Access Control)
    Route::get('role-assignments', [Admin\RoleAssignmentController::class, 'index'])->name('role-assignments.index');
    Route::get('role-assignments/create', [Admin\RoleAssignmentController::class, 'create'])->name('role-assignments.create');
    Route::post('role-assignments', [Admin\RoleAssignmentController::class, 'store'])->name('role-assignments.store');
    Route::delete('role-assignments/{assignment}', [Admin\RoleAssignmentController::class, 'destroy'])->name('role-assignments.destroy');

    // Phase 1: Role Hierarchy & Permissions (static routes before wildcards)
    Route::get('roles/hierarchy', [Admin\RolePermissionController::class, 'hierarchy'])->name('roles.hierarchy');
    Route::get('roles/permissions', [Admin\RolePermissionController::class, 'index'])->name('roles.permissions.index');
    Route::get('roles/feature-access', [Admin\RoleFeatureAccessController::class, 'index'])->name('roles.feature-access.index');
    Route::get('roles/{role}/permissions', [Admin\RolePermissionController::class, 'show'])->name('roles.permissions.show');
    Route::put('roles/{role}/permissions', [Admin\RolePermissionController::class, 'update'])->name('roles.permissions.update');
    Route::get('roles/{role}/feature-access/edit', [Admin\RoleFeatureAccessController::class, 'edit'])->name('roles.feature-access.edit');
    Route::put('roles/{role}/feature-access', [Admin\RoleFeatureAccessController::class, 'update'])->name('roles.feature-access.update');

    // User Role Management (static routes before wildcards)
    Route::get('users/roles', [Admin\UserRoleController::class, 'index'])->name('users.roles.index');
    Route::get('users/roles/create', [Admin\UserRoleController::class, 'create'])->name('users.roles.create');
    Route::post('users/roles', [Admin\UserRoleController::class, 'store'])->name('users.roles.store');
    Route::delete('users/roles/{userRole}', [Admin\UserRoleController::class, 'destroy'])->name('users.roles.destroy');
    Route::post('users/{user}/roles/expire-all', [Admin\UserRoleController::class, 'expireAll'])->name('users.roles.expire-all');

    // Reporting & Analytics
    Route::get('institutional-kpi', [Admin\InstitutionalKpiController::class, 'index'])->name('institutional-kpi');
    Route::get('aicte-report', [Admin\AicteReportController::class, 'index'])->name('aicte-report');
    Route::get('aicte-report/export-pdf', [Admin\AicteReportController::class, 'exportPdf'])->name('aicte-report.pdf');

    // Org Hierarchy Config
    Route::prefix('org-hierarchy')->name('org-hierarchy.')->group(function () {
        Route::get('/', [Admin\OrgHierarchyController::class, 'index'])->name('index');
        Route::post('/', [Admin\OrgHierarchyController::class, 'store'])->name('store');
        Route::patch('/{line}', [Admin\OrgHierarchyController::class, 'update'])->name('update');
        Route::delete('/{line}', [Admin\OrgHierarchyController::class, 'destroy'])->name('destroy');
    });
    Route::prefix('department-hierarchy')->name('department-hierarchy.')->group(function () {
        Route::get('/', fn () => redirect()->route('department-hierarchy.index'))->name('index');
        Route::post('/roles', [Admin\DepartmentHierarchyController::class, 'storeRole'])->name('roles.store');
        Route::post('/teams', [Admin\DepartmentHierarchyController::class, 'storeTeam'])->name('teams.store');
        Route::post('/members', [Admin\DepartmentHierarchyController::class, 'storeMember'])->name('members.store');
        Route::patch('/roles/{role}/deactivate', [Admin\DepartmentHierarchyController::class, 'deactivateRole'])->name('roles.deactivate');
        Route::patch('/teams/{team}/deactivate', [Admin\DepartmentHierarchyController::class, 'deactivateTeam'])->name('teams.deactivate');
        Route::patch('/members/{member}/deactivate', [Admin\DepartmentHierarchyController::class, 'deactivateMember'])->name('members.deactivate');
    });

    // Audit Log (static routes before wildcards)
    Route::get('audit-log', [Admin\AuditController::class, 'index'])->name('audit.index');
    Route::get('audit-log/search', [Admin\AuditController::class, 'search'])->name('audit.search');
    Route::get('audit-log/{log}', [Admin\AuditController::class, 'show'])->name('audit.show');

    // Hostel Management
    Route::middleware('department.feature:HOSTEL,hostel.rooms_allocations')->group(function () {
        Route::get('hostel', [Admin\HostelController::class, 'index'])->name('hostel.index');
        Route::post('hostel/blocks', [Admin\HostelController::class, 'blockStore'])->name('hostel.blocks.store');
        Route::get('hostel/blocks/{block}/edit', [Admin\HostelController::class, 'blockEdit'])->name('hostel.blocks.edit');
        Route::put('hostel/blocks/{block}', [Admin\HostelController::class, 'blockUpdate'])->name('hostel.blocks.update');
        Route::get('hostel/blocks/{block}/rooms', [Admin\HostelController::class, 'rooms'])->name('hostel.rooms');
        Route::post('hostel/blocks/{block}/rooms', [Admin\HostelController::class, 'roomStore'])->name('hostel.rooms.store');
        Route::put('hostel/blocks/{block}/rooms/{room}', [Admin\HostelController::class, 'roomUpdate'])->name('hostel.rooms.update');
        Route::get('hostel/allocations', [Admin\HostelController::class, 'allocations'])->name('hostel.allocations');
        Route::get('hostel/allocations/export', [Admin\HostelController::class, 'exportAllocations'])->name('hostel.allocations.export');
        Route::post('hostel/allocations', [Admin\HostelController::class, 'allocationStore'])->name('hostel.allocations.store');
        Route::post('hostel/allocations/{allocation}/vacate', [Admin\HostelController::class, 'allocationVacate'])->name('hostel.allocations.vacate');
        Route::post('hostel/allocations/{allocation}/transfer', [Admin\HostelController::class, 'allocationTransfer'])->name('hostel.allocations.transfer');
    });
    Route::middleware('department.feature:HOSTEL,hostel.fees')->group(function () {
        Route::get('hostel/fees', [Admin\HostelController::class, 'fees'])->name('hostel.fees');
        Route::get('hostel/fees/export', [Admin\HostelController::class, 'exportFees'])->name('hostel.fees.export');
        Route::post('hostel/fees/generate', [Admin\HostelController::class, 'feeGenerate'])->name('hostel.fees.generate');
        Route::post('hostel/fees/{demand}/paid', [Admin\HostelController::class, 'feeMarkPaid'])->name('hostel.fees.paid');
        Route::post('hostel/fees/{demand}/waive', [Admin\HostelController::class, 'feeWaive'])->name('hostel.fees.waive');
    });
    Route::middleware('department.feature:HOSTEL,hostel.outpasses')->group(function () {
        Route::get('hostel/outpasses', [Admin\HostelController::class, 'outpasses'])->name('hostel.outpasses');
        Route::get('hostel/outpasses/export', [Admin\HostelController::class, 'exportOutpasses'])->name('hostel.outpasses.export');
        Route::post('hostel/outpasses/{op}/approve', [Admin\HostelController::class, 'outpassApprove'])->name('hostel.outpasses.approve');
        Route::post('hostel/outpasses/{op}/reject', [Admin\HostelController::class, 'outpassReject'])->name('hostel.outpasses.reject');
        Route::post('hostel/outpasses/{op}/return', [Admin\HostelController::class, 'outpassReturn'])->name('hostel.outpasses.return');
    });
    Route::middleware('department.feature:HOSTEL,hostel.complaints')->group(function () {
        Route::get('hostel/complaints', [Admin\HostelController::class, 'complaints'])->name('hostel.complaints');
        Route::get('hostel/complaints/export', [Admin\HostelController::class, 'exportComplaints'])->name('hostel.complaints.export');
        Route::put('hostel/complaints/{complaint}', [Admin\HostelController::class, 'complaintUpdate'])->name('hostel.complaints.update');
    });

    // Transport Management
    Route::middleware('department.feature:TRANSPORT,transport.routes_stops')->group(function () {
        Route::get('transport', [Admin\TransportController::class, 'index'])->name('transport.index');
        Route::get('transport/routes/export', [Admin\TransportController::class, 'exportRoutes'])->name('transport.routes.export');
        Route::post('transport/routes', [Admin\TransportController::class, 'routeStore'])->name('transport.routes.store');
        Route::post('transport/stops', [Admin\TransportController::class, 'stopStore'])->name('transport.stops.store');
    });
    Route::get('transport/vehicles/export', [Admin\TransportController::class, 'exportVehicles'])
        ->middleware('department.feature:TRANSPORT,transport.vehicles')
        ->name('transport.vehicles.export');
    Route::post('transport/vehicles', [Admin\TransportController::class, 'vehicleStore'])
        ->middleware('department.feature:TRANSPORT,transport.vehicles')
        ->name('transport.vehicles.store');
    Route::patch('transport/vehicles/{vehicle}', [Admin\TransportController::class, 'vehicleUpdate'])
        ->middleware('department.feature:TRANSPORT,transport.vehicles')
        ->name('transport.vehicles.update');
    Route::middleware('department.feature:TRANSPORT,transport.student_assignments')->group(function () {
        Route::get('transport/assignments/export', [Admin\TransportController::class, 'exportAssignments'])->name('transport.assignments.export');
        Route::post('transport/assignments', [Admin\TransportController::class, 'assignmentStore'])->name('transport.assignments.store');
        Route::post('transport/assignments/{assignment}/end', [Admin\TransportController::class, 'assignmentEnd'])->name('transport.assignments.end');
    });

    // Asset Management
    Route::get('assets', [Admin\AssetController::class, 'index'])->name('assets.index');
    Route::get('assets/export', [Admin\AssetController::class, 'exportAssets'])->name('assets.export');
    Route::get('assets/assignments/export', [Admin\AssetController::class, 'exportAssignments'])->name('assets.assignments.export');
    Route::get('assets/stock-items/export', [Admin\AssetController::class, 'exportStockItems'])->name('assets.stock-items.export');
    Route::get('assets/stock-movements/export', [Admin\AssetController::class, 'exportMovements'])->name('assets.stock-movements.export');
    Route::post('assets/categories', [Admin\AssetController::class, 'categoryStore'])->name('assets.categories.store');
    Route::post('assets', [Admin\AssetController::class, 'assetStore'])->name('assets.store');
    Route::post('assets/stock-items', [Admin\AssetController::class, 'stockItemStore'])->name('assets.stock-items.store');
    Route::post('assets/stock-items/{item}/receive', [Admin\AssetController::class, 'stockReceive'])->name('assets.stock-items.receive');
    Route::post('assets/stock-items/{item}/issue', [Admin\AssetController::class, 'stockIssue'])->name('assets.stock-items.issue');
    Route::post('assets/{asset}/assign', [Admin\AssetController::class, 'assign'])->name('assets.assign');
    Route::post('assets/assignments/{assignment}/return', [Admin\AssetController::class, 'returnAssignment'])->name('assets.assignments.return');

    // Library Management
    Route::middleware('department.feature:LIB,library.catalog')->group(function () {
        Route::get('library', [Admin\LibraryController::class, 'index'])->name('library.index');
        Route::get('library/books', [Admin\LibraryController::class, 'books'])->name('library.books');
        Route::get('library/books/export', [Admin\LibraryController::class, 'exportBooks'])->name('library.books.export');
        Route::post('library/books', [Admin\LibraryController::class, 'bookStore'])->name('library.books.store');
        Route::get('library/books/{book}', [Admin\LibraryController::class, 'bookShow'])->name('library.books.show');
        Route::put('library/books/{book}', [Admin\LibraryController::class, 'bookUpdate'])->name('library.books.update');
    });
    Route::middleware('department.feature:LIB,library.circulation')->group(function () {
        Route::post('library/issue', [Admin\LibraryController::class, 'issueBook'])->name('library.issue');
        Route::post('library/issues/{issue}/return', [Admin\LibraryController::class, 'returnBook'])->name('library.issues.return');
        Route::get('library/issues', [Admin\LibraryController::class, 'issues'])->name('library.issues');
        Route::get('library/issues/export', [Admin\LibraryController::class, 'exportIssues'])->name('library.issues.export');
        Route::get('library/reservations', [Admin\LibraryController::class, 'reservations'])->name('library.reservations');
        Route::get('library/reservations/export', [Admin\LibraryController::class, 'exportReservations'])->name('library.reservations.export');
        Route::post('library/reservations/{reservation}/fulfill', [Admin\LibraryController::class, 'fulfillReservation'])->name('library.reservations.fulfill');
        Route::post('library/reservations/{reservation}/cancel', [Admin\LibraryController::class, 'cancelReservation'])->name('library.reservations.cancel');
        Route::get('library/fines', [Admin\LibraryController::class, 'fineCollection'])->name('library.fines');
        Route::get('library/fines/export', [Admin\LibraryController::class, 'exportFines'])->name('library.fines.export');
        Route::post('library/fines/{issue}/pay', [Admin\LibraryController::class, 'finePay'])->name('library.fines.pay');
    });
    Route::middleware('department.feature:LIB,library.memberships')->group(function () {
        Route::get('library/memberships', [Admin\LibraryController::class, 'memberships'])->name('library.memberships');
        Route::get('library/memberships/export', [Admin\LibraryController::class, 'exportMemberships'])->name('library.memberships.export');
        Route::post('library/memberships', [Admin\LibraryController::class, 'membershipStore'])->name('library.memberships.store');
    });
});

// ── Academic routes ─────────────────────────────────────────────────────────
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

// ── Admission Team routes ───────────────────────────────────────────────────
Route::middleware(['auth', 'role:admission_director|admission_head|admission_manager|jr_admission_manager|admission_counsellor|admission_telecaller|admission_officer|admin'])->prefix('admission')->name('admission.')->group(function () {
    Route::get('dashboard', [Admission\DashboardController::class, 'index'])->name('dashboard');
    Route::get('workbench', [Admission\WorkbenchController::class, 'index'])
        ->middleware('department.feature:ADM,admission.workbench')
        ->name('workbench');
    Route::get('attention', [Admission\AttentionController::class, 'index'])
        ->middleware('department.feature:ADM,admission.workbench')
        ->name('attention.index');
    Route::get('command-center', Admission\CommandCenterController::class)->name('command-center.index');
    Route::get('counsellor-workspace', Admission\CounsellorWorkspaceController::class)->name('counsellor-workspace.index');
    Route::get('manager-workspace', Admission\ManagerWorkspaceController::class)->name('manager-workspace.index');
    Route::get('communication', [Admission\CommunicationController::class, 'index'])->name('communication.index');
    Route::post('communication/templates', [Admission\CommunicationController::class, 'storeTemplate'])->name('communication.templates.store');
    Route::post('communication/send', [Admission\CommunicationController::class, 'send'])->name('communication.send');
    Route::post('communication/dispatch', [Admission\CommunicationController::class, 'dispatch'])->name('communication.dispatch');
    Route::get('call-queue', [Admission\CallQueueController::class, 'index'])->name('call-queue.index');
    Route::post('call-queue/log', [Admission\CallQueueController::class, 'log'])->name('call-queue.log');
    Route::get('pipeline', [Admission\PipelineController::class, 'index'])->name('pipeline.index');
    Route::post('pipeline/move', [Admission\PipelineController::class, 'move'])->name('pipeline.move');
    Route::get('automations', [Admission\AutomationController::class, 'index'])->name('automations.index');
    Route::post('automations', [Admission\AutomationController::class, 'store'])->name('automations.store');
    Route::post('automations/run', [Admission\AutomationController::class, 'run'])->name('automations.run');
    Route::get('lead-scoring', [Admission\LeadScoringController::class, 'index'])->name('scoring.index');
    Route::post('lead-scoring/recalculate', [Admission\LeadScoringController::class, 'recalculate'])->name('scoring.recalculate');
    Route::get('journeys', [Admission\JourneyController::class, 'index'])->name('journeys.index');
    Route::post('journeys', [Admission\JourneyController::class, 'store'])->name('journeys.store');
    Route::get('journeys/applicants/{applicant}/preview', [Admission\JourneyController::class, 'preview'])->name('journeys.applicants.preview');
    Route::get('partners', [Admission\PartnerController::class, 'index'])->name('partners.index');
    Route::post('partners', [Admission\PartnerController::class, 'store'])->name('partners.store');
    Route::patch('partners/{partner}/approve', [Admission\PartnerController::class, 'approve'])->name('partners.approve');
    Route::post('partners/{partner}/leads', [Admission\PartnerController::class, 'submitLead'])->name('partners.leads.store');
    Route::get('data-quality', [Admission\DataQualityController::class, 'index'])->name('data-quality.index');
    Route::post('data-quality/scan', [Admission\DataQualityController::class, 'scan'])->name('data-quality.scan');
    Route::patch('data-quality/{flag}/resolve', [Admission\DataQualityController::class, 'resolve'])->name('data-quality.resolve');
    Route::get('forecasting', [Admission\ForecastingController::class, 'index'])->name('forecasting.index');
    Route::post('forecasting/snapshot', [Admission\ForecastingController::class, 'snapshot'])->name('forecasting.snapshot');
    Route::get('approvals', [Admission\ApprovalController::class, 'index'])->name('approvals.index');
    Route::post('approvals/request', [Admission\ApprovalController::class, 'request'])->name('approvals.request');
    Route::patch('approvals/{approval}/approve', [Admission\ApprovalController::class, 'approve'])->name('approvals.approve');
    Route::patch('approvals/{approval}/reject', [Admission\ApprovalController::class, 'reject'])->name('approvals.reject');
    Route::get('reminders', [Admission\ReminderController::class, 'index'])->name('reminders.index');
    Route::post('reminders', [Admission\ReminderController::class, 'store'])->name('reminders.store');
    Route::post('reminders/cadence', [Admission\ReminderController::class, 'cadence'])->name('reminders.cadence');
    Route::post('reminders/{reminder}/send', [Admission\ReminderController::class, 'send'])->name('reminders.send');
    Route::post('reminders/{reminder}/complete', [Admission\ReminderController::class, 'complete'])->name('reminders.complete');
    Route::post('reminders/{reminder}/pause', [Admission\ReminderController::class, 'pause'])->name('reminders.pause');
    Route::post('reminders/{reminder}/resume', [Admission\ReminderController::class, 'resume'])->name('reminders.resume');
    Route::get('assessment-panels', [Admission\AssessmentPanelController::class, 'index'])->name('assessment-panels.index');
    Route::post('assessment-panels', [Admission\AssessmentPanelController::class, 'store'])->name('assessment-panels.store');
    Route::get('assessment-operations', [Admission\AssessmentOperationController::class, 'index'])->name('assessment-operations.index');
    Route::post('assessment-operations/assign', [Admission\AssessmentOperationController::class, 'assign'])->name('assessment-operations.assign');
    Route::post('assessment-operations/scores/{score}/finalize', [Admission\AssessmentOperationController::class, 'finalize'])->name('assessment-operations.scores.finalize');
    Route::post('assessment-operations/scores/{score}/override', [Admission\AssessmentOperationController::class, 'override'])->name('assessment-operations.scores.override');
    Route::get('assessment-control-room', Admission\AssessmentControlRoomController::class)->name('assessment-control-room.index');
    Route::get('integrations', [Admission\IntegrationController::class, 'index'])->name('integrations.index');
    Route::post('integrations/test', [Admission\IntegrationController::class, 'test'])->name('integrations.test');
    Route::post('integrations/{log}/retry', [Admission\IntegrationController::class, 'retry'])->name('integrations.retry');
    Route::post('integration-webhooks/{provider}', [Admission\IntegrationWebhookController::class, 'store'])->name('integration-webhooks.store');
    Route::get('assessment-schedule-conflicts', [Admission\AssessmentScheduleConflictController::class, 'index'])->name('assessment-schedule-conflicts.index');
    Route::post('assessment-schedule-conflicts/{panel}/refresh', [Admission\AssessmentScheduleConflictController::class, 'refresh'])->name('assessment-schedule-conflicts.refresh');
    Route::get('assessment-bulk-assignment', [Admission\AssessmentBulkAssignmentController::class, 'index'])->name('assessment-bulk-assignment.index');
    Route::post('assessment-bulk-assignment', [Admission\AssessmentBulkAssignmentController::class, 'store'])->name('assessment-bulk-assignment.store');
    Route::get('assessment-normalization', [Admission\AssessmentNormalizationController::class, 'index'])->name('assessment-normalization.index');
    Route::post('assessment-normalization/run', [Admission\AssessmentNormalizationController::class, 'run'])->name('assessment-normalization.run');
    Route::get('assessment-rubrics', [Admission\AssessmentRubricController::class, 'index'])->name('assessment-rubrics.index');
    Route::post('assessment-rubrics', [Admission\AssessmentRubricController::class, 'store'])->name('assessment-rubrics.store');
    Route::get('evaluator-scoring', [Admission\EvaluatorScoringController::class, 'index'])->name('evaluator-scoring.index');
    Route::post('evaluator-scoring/{assignment}', [Admission\EvaluatorScoringController::class, 'save'])->name('evaluator-scoring.save');
    Route::post('evaluator-scoring/{assignment}/lifecycle', [Admission\EvaluatorScoringController::class, 'lifecycle'])->name('evaluator-scoring.lifecycle');
    Route::get('counsellor-desk', Admission\CounsellorDeskController::class)->name('counsellor-desk.index');
    Route::get('counsellor-performance', [Admission\CounsellorPerformanceController::class, 'index'])->name('counsellor-performance.index');
    Route::post('counsellor-performance/{counsellor}/coach', [Admission\CounsellorPerformanceController::class, 'coach'])->name('counsellor-performance.coach');
    Route::get('script-compliance', [Admission\ScriptComplianceController::class, 'index'])->name('script-compliance.index');
    Route::post('script-compliance', [Admission\ScriptComplianceController::class, 'store'])->name('script-compliance.store');
    Route::get('objection-analytics', [Admission\ObjectionAnalyticsController::class, 'index'])->name('objection-analytics.index');
    Route::post('objection-analytics', [Admission\ObjectionAnalyticsController::class, 'store'])->name('objection-analytics.store');
    Route::get('parent-journeys', [Admission\ParentJourneyController::class, 'index'])->name('parent-journeys.index');
    Route::post('parent-journeys/{journey}/reminder', [Admission\ParentJourneyController::class, 'reminder'])->name('parent-journeys.reminder');
    Route::get('conversation-timeline/{subjectType}/{subjectId}', [Admission\ConversationTimelineController::class, 'show'])->name('conversation-timeline.show');
    Route::get('counsellor-playbooks', [Admission\CounsellorPlaybookController::class, 'index'])->name('counsellor-playbooks.index');
    Route::post('counsellor-playbooks', [Admission\CounsellorPlaybookController::class, 'store'])->name('counsellor-playbooks.store');
    Route::get('route-access-audit', Admission\RouteAccessAuditController::class)->name('route-access-audit.index');
    Route::get('automation-simulation', [Admission\AutomationSimulationController::class, 'index'])->name('automation-simulation.index');
    Route::post('automation-simulation/simulate', [Admission\AutomationSimulationController::class, 'simulate'])->name('automation-simulation.simulate');
    Route::post('automation-simulation/run', [Admission\AutomationSimulationController::class, 'run'])->name('automation-simulation.run');
    Route::get('saved-views', [Admission\SavedViewController::class, 'index'])->name('saved-views.index');
    Route::post('saved-views', [Admission\SavedViewController::class, 'store'])->name('saved-views.store');
    Route::get('accessibility-audit', Admission\AccessibilityAuditController::class)->name('accessibility-audit.index');
    Route::get('v037-exports/{type}', Admission\V037ExportController::class)->name('v037.exports');
    Route::get('calling-desk', [Admission\CallingDeskController::class, 'index'])->name('calling-desk.index');
    Route::post('calling-desk/outcome', [Admission\CallingDeskController::class, 'outcome'])->name('calling-desk.outcome');
    Route::post('call-attempts/skip', [Admission\CallingDeskController::class, 'skip'])->name('call-attempts.skip');
    Route::get('assessment-scheduling', [Admission\AssessmentSchedulingController::class, 'index'])->name('assessment-slots.index');
    Route::post('assessment-slots', [Admission\AssessmentSchedulingController::class, 'storeSlot'])->name('assessment-slots.store');
    Route::post('assessment-slots/assign', [Admission\AssessmentSchedulingController::class, 'assignSlot'])->name('assessment-slots.assign');
    Route::post('assessment-slots/bulk-assign', [Admission\AssessmentSchedulingController::class, 'bulkAssignSlot'])->name('assessment-slots.bulk-assign');
    Route::post('assessment-slots/check-in', [Admission\AssessmentSchedulingController::class, 'checkIn'])->name('assessment-slots.check-in');
    Route::post('assessment-reschedule-requests/review', [Admission\AssessmentSchedulingController::class, 'reviewReschedule'])->name('assessment-reschedule-requests.review');
    Route::post('assessment-evaluator-invitations/respond', [Admission\AssessmentSchedulingController::class, 'evaluatorResponse'])->name('assessment-evaluator-invitations.respond');
    Route::post('assessment-evaluator-invitations/replace', [Admission\AssessmentSchedulingController::class, 'replaceEvaluator'])->name('assessment-evaluator-invitations.replace');
    Route::post('gd-groups/build', [Admission\AssessmentSchedulingController::class, 'buildGd'])->name('gd-groups.build');
    Route::post('assessment-submissions', [Admission\AssessmentSchedulingController::class, 'submission'])->name('assessment-submissions.store');
    Route::get('selection-committee', [Admission\SelectionCommitteeController::class, 'index'])->name('selection-committee.index');
    Route::post('selection-committee/decide', [Admission\SelectionCommitteeController::class, 'decide'])->name('selection-committee.decide');
    Route::get('offer-seat-control', [Admission\OfferSeatControlController::class, 'index'])->name('offer-rounds.index');
    Route::get('offer-rounds', fn () => redirect()->route('admission.offer-rounds.index'))->name('offer-rounds.redirect');
    Route::post('offer-rounds', [Admission\OfferSeatControlController::class, 'createRound'])->name('offer-rounds.store');
    Route::post('offer-rounds/{roundId}/publish', [Admission\OfferSeatControlController::class, 'publishRound'])->name('offer-rounds.publish');
    Route::post('waitlist', [Admission\OfferSeatControlController::class, 'addWaitlist'])->name('waitlist.store');
    Route::post('seat-control/{holdId}/release', [Admission\OfferSeatControlController::class, 'releaseSeat'])->name('seat-control.release');
    Route::post('deferrals', [Admission\OfferSeatControlController::class, 'requestDeferral'])->name('deferrals.store');
    Route::post('deferrals/{deferralId}/approve', [Admission\OfferSeatControlController::class, 'approveDeferral'])->name('deferrals.approve');
    Route::post('joining-kit/{applicantId}/ensure', [Admission\OfferSeatControlController::class, 'ensureJoiningKit'])->name('joining-kit.ensure');
    Route::get('communication-safety', [Admission\CommunicationSafetyController::class, 'index'])->name('communication-safety.index');
    Route::post('consent-center', [Admission\CommunicationSafetyController::class, 'consent'])->name('consent-center.store');
    Route::post('template-approvals/{templateId}/request', [Admission\CommunicationSafetyController::class, 'requestApproval'])->name('template-approvals.request');
    Route::post('template-approvals/{approvalId}/approve', [Admission\CommunicationSafetyController::class, 'approveTemplate'])->name('template-approvals.approve');
    Route::post('communication-safety/preview', [Admission\CommunicationSafetyController::class, 'preview'])->name('communication-safety.preview');
    Route::get('integration-health', [Admission\IntegrationHealthController::class, 'index'])->name('integration-health.index');
    Route::post('integration-health/check', [Admission\IntegrationHealthController::class, 'check'])->name('integration-health.check');
    Route::post('integration-health/retry/{retryId}', [Admission\IntegrationHealthController::class, 'retry'])->name('integration-health.retry');
    Route::get('quick-search', [Admission\QuickSearchController::class, 'index'])->name('quick-search.index');
    Route::get('handoff', [Admission\HandoffController::class, 'index'])->name('handoff.index');
    Route::post('handoff/{applicantId}/refresh', [Admission\HandoffController::class, 'refresh'])->name('handoff.refresh');
    Route::post('handoff/{handoffId}/mark-handed-off', [Admission\HandoffController::class, 'markHandedOff'])->name('handoff.mark-handed-off');
    Route::post('handoff/{handoffId}/return', [Admission\HandoffController::class, 'returnForCorrection'])->name('handoff.return');
    Route::get('v039-exports/{type}', Admission\V039ExportController::class)->name('v039.exports');
    Route::get('calendar', Admission\AdmissionCalendarController::class)->name('calendar.index');
    Route::get('walk-ins', [Admission\WalkInController::class, 'index'])->name('walk-ins.index');
    Route::post('walk-ins', [Admission\WalkInController::class, 'store'])->name('walk-ins.store');
    Route::post('walk-ins/{walkIn}/convert', [Admission\WalkInController::class, 'convert'])->name('walk-ins.convert');
    Route::get('manager-reviews', [Admission\ManagerReviewController::class, 'index'])->name('manager-reviews.index');
    Route::patch('manager-reviews/{review}/resolve', [Admission\ManagerReviewController::class, 'resolve'])->name('manager-reviews.resolve');
    Route::get('assignment-rules', [Admission\AssignmentRuleController::class, 'index'])->name('assignment-rules.index');
    Route::post('assignment-rules', [Admission\AssignmentRuleController::class, 'store'])->name('assignment-rules.store');
    Route::patch('assignment-rules/{rule}/toggle', [Admission\AssignmentRuleController::class, 'toggle'])->name('assignment-rules.toggle');
    Route::get('workflow-config', [Admission\WorkflowConfigController::class, 'index'])->name('workflow-config.index');
    Route::post('workflow-config', [Admission\WorkflowConfigController::class, 'storeConfig'])->name('workflow-config.store');
    Route::post('workflow-config/tags', [Admission\WorkflowConfigController::class, 'storeTag'])->name('workflow-config.tags.store');
    Route::get('process-templates', [Admission\ProcessTemplateController::class, 'index'])
        ->middleware('department.feature:ADM,admission.process_templates')
        ->name('process-templates.index');
    Route::post('process-templates', [Admission\ProcessTemplateController::class, 'store'])
        ->middleware('department.feature:ADM,admission.process_templates')
        ->name('process-templates.store');
    Route::post('process-templates/{template}/stages', [Admission\ProcessTemplateController::class, 'storeStage'])
        ->middleware('department.feature:ADM,admission.process_templates')
        ->name('process-templates.stages.store');
    Route::post('applicants/bulk-action', [Admission\ApplicantCrmController::class, 'bulkAction'])->name('applicants.bulk-action');
    Route::post('applicants/bulk/assign', [Admission\AssignmentController::class, 'bulkAssignApplicants'])
        ->middleware('department.feature:ADM,admission.assignment')
        ->name('applicants.bulk-assign');
    Route::post('applicants/bulk/tags', [Admission\TagController::class, 'bulkTagApplicants'])->name('applicants.bulk-tags');
    Route::get('applicants/export-csv', [Admission\ApplicantCrmController::class, 'exportCsv'])
        ->middleware('department.feature:ADM,admission.reporting_exports')
        ->name('applicants.export-csv');
    Route::get('applicants', [Admission\ApplicantCrmController::class, 'index'])->name('applicants.index');
    Route::get('applicants/{applicant}', [Admission\ApplicantCrmController::class, 'show'])->name('applicants.show');
    Route::post('applicants/{applicant}/assign', [Admission\AssignmentController::class, 'assignApplicant'])
        ->middleware('department.feature:ADM,admission.assignment')
        ->name('applicants.assign');
    Route::post('applicants/{applicant}/delegate', [Admission\AssignmentController::class, 'delegateApplicant'])
        ->middleware('department.feature:ADM,admission.assignment')
        ->name('applicants.delegate');
    Route::post('applicants/{applicant}/pause-sla', [Admission\AssignmentController::class, 'pauseApplicantSla'])
        ->middleware('department.feature:ADM,admission.assignment')
        ->name('applicants.pause-sla');
    Route::post('applicants/{applicant}/tags', [Admission\TagController::class, 'tagApplicant'])->name('applicants.tags.store');
    Route::post('applicants/{applicant}/status', [Admission\ApplicantCrmController::class, 'updateStatus'])->name('applicants.status');
    Route::post('applicants/{applicant}/counselling-log', [Admission\ApplicantCrmController::class, 'storeCounsellingLog'])->name('applicants.counselling-log');
    Route::post('applicants/{applicant}/notes', [Admission\ApplicantCrmController::class, 'storeNote'])->name('applicants.notes');

    // Application Windows (static routes before {window} parameterized)
    Route::get('application-windows/{program}', [Admission\ApplicationWindowController::class, 'index'])->name('application-windows.index');
    Route::get('application-windows/{program}/create', [Admission\ApplicationWindowController::class, 'create'])->name('application-windows.create');
    Route::post('application-windows/{program}', [Admission\ApplicationWindowController::class, 'store'])->name('application-windows.store');
    Route::get('application-windows/edit/{window}', [Admission\ApplicationWindowController::class, 'edit'])->name('application-windows.edit');
    Route::put('application-windows/{window}', [Admission\ApplicationWindowController::class, 'update'])->name('application-windows.update');
    Route::delete('application-windows/{window}', [Admission\ApplicationWindowController::class, 'destroy'])->name('application-windows.destroy');
    Route::patch('application-windows/{window}/toggle', [Admission\ApplicationWindowController::class, 'toggleActive'])->name('application-windows.toggle');

    // Leads/Enquiries (static routes before {lead} parameterized)
    Route::get('leads', [Admission\LeadController::class, 'index'])->name('leads.index');
    Route::post('leads', [Admission\LeadController::class, 'store'])->name('leads.store');
    Route::post('leads/bulk/update-status', [Admission\LeadController::class, 'bulkUpdateStatus'])->name('leads.bulk-status');
    Route::post('leads/bulk/assign', [Admission\AssignmentController::class, 'bulkAssignLeads'])
        ->middleware('department.feature:ADM,admission.assignment')
        ->name('leads.bulk-assign');
    Route::post('leads/bulk/tags', [Admission\TagController::class, 'bulkTagLeads'])->name('leads.bulk-tags');
    Route::get('leads/analytics/dashboard', [Admission\LeadController::class, 'analytics'])->name('leads.analytics');
    Route::get('leads/import', [Admission\LeadImportController::class, 'showImportForm'])->name('leads.import');
    Route::post('leads/import', [Admission\LeadImportController::class, 'import'])->name('leads.import.post');
    Route::get('leads/export-csv', [Admission\LeadController::class, 'exportCsv'])
        ->middleware('department.feature:ADM,admission.reporting_exports')
        ->name('leads.export-csv');
    Route::get('leads/follow-ups/calendar', [Admission\LeadFollowUpController::class, 'calendar'])->name('leads.follow-ups.calendar');
    Route::patch('leads/follow-ups/{followUp}/complete', [Admission\LeadFollowUpController::class, 'complete'])->name('leads.follow-ups.complete');
    Route::get('leads/{lead}', [Admission\LeadController::class, 'show'])->name('leads.show');
    Route::post('leads/{lead}/merge', [Admission\LeadMergeController::class, 'merge'])->name('leads.merge');
    Route::post('leads/{lead}/delegate', [Admission\AssignmentController::class, 'delegateLead'])
        ->middleware('department.feature:ADM,admission.assignment')
        ->name('leads.delegate');
    Route::post('leads/{lead}/pause-sla', [Admission\AssignmentController::class, 'pauseLeadSla'])
        ->middleware('department.feature:ADM,admission.assignment')
        ->name('leads.pause-sla');
    Route::post('leads/{lead}/tags', [Admission\TagController::class, 'tagLead'])->name('leads.tags.store');
    Route::post('leads/{lead}/contact', [Admission\LeadController::class, 'contactLead'])->name('leads.contact');
    Route::post('leads/{lead}/interested', [Admission\LeadController::class, 'markInterested'])->name('leads.interested');
    Route::post('leads/{lead}/not-interested', [Admission\LeadController::class, 'markNotInterested'])->name('leads.not-interested');
    Route::post('leads/{lead}/convert', [Admission\LeadController::class, 'convert'])->name('leads.convert');
    Route::post('leads/{lead}/workbench-assign', [Admission\AssignmentController::class, 'assignLead'])
        ->middleware('department.feature:ADM,admission.assignment')
        ->name('leads.workbench-assign');
    Route::post('leads/{lead}/assign', [Admission\LeadFollowUpController::class, 'assign'])->name('leads.assign');
    Route::post('leads/{lead}/follow-ups', [Admission\LeadFollowUpController::class, 'store'])->name('leads.follow-ups.store');

    // Document Verification Queue (static routes BEFORE {document} parameterized)
    Route::get('documents/queue', [Admission\DocumentVerificationController::class, 'pendingQueue'])
        ->middleware('department.feature:ADM,admission.document_verification')
        ->name('documents.queue');
    Route::get('documents/queue/export', [Admission\DocumentVerificationController::class, 'exportPendingQueue'])
        ->middleware('department.feature:ADM,admission.document_verification')
        ->name('documents.queue.export');
    Route::post('documents/bulk-verify', [Admission\DocumentVerificationController::class, 'bulkVerify'])
        ->middleware('department.feature:ADM,admission.document_verification')
        ->name('documents.bulk-verify');
    Route::post('documents/{document}/verify', [Admission\DocumentVerificationController::class, 'verify'])
        ->middleware('department.feature:ADM,admission.document_verification')
        ->name('documents.verify');
    Route::post('documents/{document}/reject', [Admission\DocumentVerificationController::class, 'reject'])
        ->middleware('department.feature:ADM,admission.document_verification')
        ->name('documents.reject');
    Route::get('documents/{document}/download', [Admission\DocumentVerificationController::class, 'downloadDocument'])->name('documents.download');
    Route::get('documents/{document}/preview', [Admission\DocumentVerificationController::class, 'previewDocument'])->name('documents.preview');

    // Selection Sessions (static routes before parameterized)
    Route::get('sessions', [Admission\SelectionSessionController::class, 'index'])->name('sessions.index');
    Route::get('sessions/create', [Admission\SelectionSessionController::class, 'create'])->name('sessions.create');
    Route::post('sessions', [Admission\SelectionSessionController::class, 'store'])->name('sessions.store');
    Route::post('sessions/{session}/assign', [Admission\SelectionSessionController::class, 'assignApplicants'])->name('sessions.assign');
    Route::post('sessions/{session}/attendance', [Admission\SelectionSessionController::class, 'markAttendance'])->name('sessions.attendance');
    Route::post('sessions/{session}/complete', [Admission\SelectionSessionController::class, 'completeSession'])->name('sessions.complete');
    Route::delete('sessions/{session}/applicants/{applicant}', [Admission\SelectionSessionController::class, 'removeApplicant'])->name('sessions.remove-applicant');
    Route::get('sessions/{session}', [Admission\SelectionSessionController::class, 'show'])->name('sessions.show');
    Route::get('sessions/{session}/edit', [Admission\SelectionSessionController::class, 'edit'])->name('sessions.edit');
    Route::put('sessions/{session}', [Admission\SelectionSessionController::class, 'update'])->name('sessions.update');
    Route::delete('sessions/{session}', [Admission\SelectionSessionController::class, 'destroy'])->name('sessions.destroy');

    // Scoring
    Route::get('sessions/{session}/scores', [Admission\ScoringController::class, 'sessionScoreSheet'])->name('sessions.scores');
    Route::post('sessions/{session}/scores', [Admission\ScoringController::class, 'saveScores'])->name('sessions.scores.save');
    Route::get('applicants/{applicant}/scorecard', [Admission\ScoringController::class, 'applicantScorecard'])->name('applicants.scorecard');

    // Merit List (static routes before {entry} parameter)
    Route::get('merit-list/{program}', [Admission\MeritListController::class, 'index'])->name('merit-list.index');
    Route::post('merit-list/{program}/generate', [Admission\MeritListController::class, 'generate'])->name('merit-list.generate');
    Route::get('merit-list/{program}/show', [Admission\MeritListController::class, 'show'])->name('merit-list.show');
    Route::get('merit-list/{program}/export', [Admission\MeritListController::class, 'exportMeritList'])
        ->middleware('department.feature:ADM,admission.reporting_exports')
        ->name('merit-list.export');
    Route::post('merit-list/{program}/bulk-decide', [Admission\MeritListController::class, 'bulkDecide'])->name('merit-list.bulk-decide');
    Route::post('merit-list/entries/{entry}/decide', [Admission\MeritListController::class, 'updateDecision'])->name('merit-list.decide');

    // Offer Letters (static routes before {offerLetter} parameter)
    Route::post('offer-letters/bulk-generate', [Admission\OfferLetterController::class, 'bulkGenerateFromMeritList'])->name('admission.offer-letters.bulk-generate');
    Route::get('offer-letters/{program}', [Admission\OfferLetterController::class, 'index'])->name('offer-letters.index');
    Route::post('offer-letters/{program}/generate', [Admission\OfferLetterController::class, 'generate'])->name('offer-letters.generate');
    Route::post('offer-letters/{program}/bulk-generate', [Admission\OfferLetterController::class, 'bulkGenerate'])->name('offer-letters.bulk-generate');
    Route::get('offer-letters/view/{offerLetter}', [Admission\OfferLetterController::class, 'show'])->name('offer-letters.show');
    Route::get('offer-letters/export/{offerLetter}', [Admission\OfferLetterController::class, 'exportPdf'])
        ->middleware('department.feature:ADM,admission.reporting_exports')
        ->name('offer-letters.export');
    Route::post('offer-letters/{offerLetter}/accept', [Admission\OfferLetterController::class, 'accept'])->name('offer-letters.accept');
    Route::post('offer-letters/{offerLetter}/decline', [Admission\OfferLetterController::class, 'decline'])->name('offer-letters.decline');

    // Payment Verification (static routes before parameterized)
    Route::get('payments/queue', [Admission\PaymentVerificationController::class, 'pendingQueue'])
        ->middleware('department.feature:ADM,admission.payment_verification')
        ->name('payments.queue');
    Route::get('payments/queue/export', [Admission\PaymentVerificationController::class, 'exportPendingQueue'])
        ->middleware('department.feature:ADM,admission.payment_verification')
        ->name('payments.queue.export');
    Route::get('payments/{program}', [Admission\PaymentVerificationController::class, 'index'])
        ->middleware('department.feature:ADM,admission.payment_verification')
        ->name('payments.index');
    Route::get('applicants/{applicant}/payments', [Admission\PaymentVerificationController::class, 'applicantPayments'])
        ->middleware('department.feature:ADM,admission.payment_verification')
        ->name('applicants.payments');
    Route::post('payments/{payment}/verify', [Admission\PaymentVerificationController::class, 'verify'])
        ->middleware('department.feature:ADM,admission.payment_verification')
        ->name('payments.verify');
    Route::post('payments/{payment}/reject', [Admission\PaymentVerificationController::class, 'reject'])
        ->middleware('department.feature:ADM,admission.payment_verification')
        ->name('payments.reject');
    Route::get('payments/{payment}/proof', [Admission\PaymentVerificationController::class, 'downloadProof'])->name('payments.proof');

    // Enrollment Confirmation (static routes BEFORE {applicant} parameterized)
    Route::get('enrollment', [Admission\EnrollmentController::class, 'index'])->name('enrollment.index');
    Route::get('enrollment/confirmation/{confirmation}', [Admission\EnrollmentController::class, 'show'])->name('enrollment.show');
    Route::get('enrollment/confirmation/{confirmation}/letter', [Admission\EnrollmentController::class, 'printLetter'])->name('enrollment.letter');
    Route::get('enrollment/{applicant}/create', [Admission\EnrollmentController::class, 'create'])->name('enrollment.create');
    Route::post('enrollment/{applicant}', [Admission\EnrollmentController::class, 'store'])->name('enrollment.store');

    // Fee Installments (static routes before parameterized)
    Route::get('fee-installments/{program}', [Admission\FeeInstallmentController::class, 'index'])->name('fee-installments.index');
    Route::get('fee-installments/{program}/create', [Admission\FeeInstallmentController::class, 'create'])->name('fee-installments.create');
    Route::post('fee-installments/{program}', [Admission\FeeInstallmentController::class, 'store'])->name('fee-installments.store');
    Route::get('fee-installments/{program}/duplicate', [Admission\FeeInstallmentController::class, 'duplicateForm'])->name('fee-installments.duplicate-form');
    Route::post('fee-installments/{program}/duplicate', [Admission\FeeInstallmentController::class, 'duplicate'])->name('fee-installments.duplicate');
    Route::get('fee-installments/edit/{feeInstallment}', [Admission\FeeInstallmentController::class, 'edit'])->name('fee-installments.edit');
    Route::put('fee-installments/{feeInstallment}', [Admission\FeeInstallmentController::class, 'update'])->name('fee-installments.update');
    Route::delete('fee-installments/{feeInstallment}', [Admission\FeeInstallmentController::class, 'destroy'])->name('fee-installments.destroy');

    // Selection Process Steps & Scoring Parameters
    Route::get('selection-process/{program}/steps', [Admission\SelectionProcessController::class, 'steps'])->name('selection-process.steps');
    Route::get('selection-process/{program}/steps/create', [Admission\SelectionProcessController::class, 'createStep'])->name('selection-process.steps.create');
    Route::post('selection-process/{program}/steps', [Admission\SelectionProcessController::class, 'storeStep'])->name('selection-process.steps.store');
    Route::get('selection-process/steps/{step}/edit', [Admission\SelectionProcessController::class, 'editStep'])->name('selection-process.steps.edit');
    Route::put('selection-process/steps/{step}', [Admission\SelectionProcessController::class, 'updateStep'])->name('selection-process.steps.update');
    Route::delete('selection-process/steps/{step}', [Admission\SelectionProcessController::class, 'destroyStep'])->name('selection-process.steps.destroy');
    Route::get('selection-process/steps/{step}/parameters', [Admission\SelectionProcessController::class, 'parameters'])->name('selection-process.parameters');
    Route::get('selection-process/steps/{step}/parameters/create', [Admission\SelectionProcessController::class, 'createParameter'])->name('selection-process.parameters.create');
    Route::post('selection-process/steps/{step}/parameters', [Admission\SelectionProcessController::class, 'storeParameter'])->name('selection-process.parameters.store');
    Route::get('selection-process/parameters/{parameter}/edit', [Admission\SelectionProcessController::class, 'editParameter'])->name('selection-process.parameters.edit');
    Route::put('selection-process/parameters/{parameter}', [Admission\SelectionProcessController::class, 'updateParameter'])->name('selection-process.parameters.update');
    Route::delete('selection-process/parameters/{parameter}', [Admission\SelectionProcessController::class, 'destroyParameter'])->name('selection-process.parameters.destroy');

    // Seat Matrix
    Route::get('seat-matrices/{program}', [Admission\SeatMatrixController::class, 'index'])->name('seat-matrices.index');
    Route::get('seat-matrices/{program}/create', [Admission\SeatMatrixController::class, 'create'])->name('seat-matrices.create');
    Route::post('seat-matrices/{program}', [Admission\SeatMatrixController::class, 'store'])->name('seat-matrices.store');
    Route::get('seat-matrices/{seatMatrix}/edit', [Admission\SeatMatrixController::class, 'edit'])->name('seat-matrices.edit');
    Route::put('seat-matrices/{seatMatrix}', [Admission\SeatMatrixController::class, 'update'])->name('seat-matrices.update');
    Route::delete('seat-matrices/{seatMatrix}', [Admission\SeatMatrixController::class, 'destroy'])->name('seat-matrices.destroy');

    // P1-1 and P1-2 lead routes moved to static-before-parameterized section above

    // P1-3: Applicant Category & Entrance Exam
    Route::get('applicants/{applicant}/category', [Admission\ApplicantCategoryController::class, 'edit'])->name('applicants.category.edit');
    Route::put('applicants/{applicant}/category', [Admission\ApplicantCategoryController::class, 'update'])->name('applicants.category.update');

    // P1-4: Registration Fee Payment Gate
    Route::get('applicants/{applicant}/registration-fee', [Admission\RegistrationFeeController::class, 'show'])->name('applicants.registration-fee.show');
    Route::post('applicants/{applicant}/registration-fee', [Admission\RegistrationFeeController::class, 'store'])->name('applicants.registration-fee.store');

    // P1-5: Call Letter PDF
    Route::get('applicants/{applicant}/call-letter', [Admission\CallLetterController::class, 'generate'])->name('applicants.call-letter');

    // P2-A: Fee Receipt PDF + Printable Application PDF
    Route::get('payments/{payment}/receipt', [Admission\FeeReceiptController::class, 'receipt'])->name('payments.receipt');
    Route::get('applicants/{applicant}/application-pdf', [Admission\ApplicationPdfController::class, 'generate'])->name('applicants.application-pdf');

    // P3-1: Admission Reporting Dashboard
    Route::get('reports', [Admission\ReportingController::class, 'index'])
        ->middleware('department.feature:ADM,admission.reporting_exports')
        ->name('reports.index');

    // P3-2: Session Call Letter Dispatch
    Route::post('sessions/{session}/dispatch-call-letters', [Admission\SelectionSessionController::class, 'dispatchCallLetters'])->name('sessions.dispatch-call-letters');

    // P3-3: Waitlist Management
    Route::get('waitlist/{program}', [Admission\WaitlistController::class, 'index'])->name('waitlist.index');
    Route::post('waitlist/{entry}/promote', [Admission\WaitlistController::class, 'promote'])->name('waitlist.promote');

    // P3-4: Bulk Communication
    Route::get('bulk-communication', [Admission\BulkCommunicationController::class, 'index'])->name('bulk-communication.index');
    Route::post('bulk-communication/send', [Admission\BulkCommunicationController::class, 'send'])->name('bulk-communication.send');

    // P2-B: Refund Management
    Route::get('refunds', [Admission\RefundController::class, 'index'])->name('refunds.index');
    Route::get('refunds/{applicant}/create', [Admission\RefundController::class, 'create'])->name('refunds.create');
    Route::post('refunds/{applicant}', [Admission\RefundController::class, 'store'])->name('refunds.store');
    Route::get('refunds/{refund}/show', [Admission\RefundController::class, 'show'])->name('refunds.show');
    Route::patch('refunds/{refund}/approve', [Admission\RefundController::class, 'approve'])->name('refunds.approve');
    Route::patch('refunds/{refund}/reject', [Admission\RefundController::class, 'reject'])->name('refunds.reject');
    Route::patch('refunds/{refund}/process', [Admission\RefundController::class, 'process'])->name('refunds.process');

    // P4-3: CSV Exports (leads/export-csv and applicants/export-csv stay before their wildcard routes)
    Route::get('merit-list/{program}/export-csv', [Admission\MeritListController::class, 'exportCsv'])
        ->middleware('department.feature:ADM,admission.reporting_exports')
        ->name('merit-list.export-csv');

    // P6: Scholarship Management
    Route::get('scholarship-schemes', [Admission\ScholarshipSchemeController::class, 'index'])->name('scholarship-schemes.index');
    Route::get('scholarship-schemes/create', [Admission\ScholarshipSchemeController::class, 'create'])->name('scholarship-schemes.create');
    Route::post('scholarship-schemes', [Admission\ScholarshipSchemeController::class, 'store'])->name('scholarship-schemes.store');
    Route::get('scholarship-schemes/{scholarshipScheme}/edit', [Admission\ScholarshipSchemeController::class, 'edit'])->name('scholarship-schemes.edit');
    Route::put('scholarship-schemes/{scholarshipScheme}', [Admission\ScholarshipSchemeController::class, 'update'])->name('scholarship-schemes.update');
    Route::post('scholarship-schemes/{scholarshipScheme}/toggle', [Admission\ScholarshipSchemeController::class, 'toggle'])->name('scholarship-schemes.toggle');
    Route::post('applicants/{applicant}/scholarships', [Admission\ApplicantScholarshipController::class, 'store'])->name('applicants.scholarships.store');
    Route::delete('scholarships/{scholarship}', [Admission\ApplicantScholarshipController::class, 'destroy'])->name('scholarships.destroy');
    Route::post('scholarships/{scholarship}/disburse', [Admission\ApplicantScholarshipController::class, 'disburse'])->name('scholarships.disburse');
    Route::get('scholarship-disbursements', [Admission\ApplicantScholarshipController::class, 'disbursementQueue'])->name('scholarship-disbursements.index');

    // P8-3: Analytics PDF Export
    Route::get('reports/export-pdf', [Admission\ReportingController::class, 'exportPdf'])
        ->middleware('department.feature:ADM,admission.reporting_exports')
        ->name('reports.export-pdf');
});

// ── Teacher routes ──────────────────────────────────────────────────────────
Route::prefix('teacher')->name('teacher.')->middleware(['auth', 'role:teacher|admin'])->group(function () {
    Route::get('dashboard', [Teacher\DashboardController::class, 'index'])->name('dashboard');

    // Attendance
    Route::get('attendance/mark',    [Teacher\AttendanceController::class, 'mark'])->name('attendance.mark');
    Route::post('attendance/store',  [Teacher\AttendanceController::class, 'store'])->name('attendance.store');

    // Exams & Results
    Route::get('exams',                    [Teacher\ExamController::class, 'index'])->name('exams.index');
    Route::get('exams/{exam}/results',     [Teacher\ExamController::class, 'enterResults'])->name('exams.results');
    Route::post('exams/{exam}/results',    [Teacher\ExamController::class, 'saveResults'])->name('exams.results.save');

    // Students
    Route::get('students', [Teacher\StudentController::class, 'index'])->name('students.index');

    // Leave
    Route::get('leaves', [Teacher\LeaveController::class, 'index'])->name('leaves.index');
    Route::get('leaves/create', [Teacher\LeaveController::class, 'create'])->name('leaves.create');
    Route::post('leaves', [Teacher\LeaveController::class, 'store'])->name('leaves.store');
    Route::delete('leaves/{leave}', [Teacher\LeaveController::class, 'destroy'])->name('leaves.destroy');

    // Profile
    Route::get('profile', [Teacher\ProfileController::class, 'show'])->name('profile');
    Route::put('profile', [Teacher\ProfileController::class, 'update'])->name('profile.update');

    // Timetable
    Route::get('timetable', [Teacher\TimetableController::class, 'index'])->name('timetable.index');
    Route::get('pmc-timetable', [\App\Http\Controllers\Academics\PmcOperatingController::class, 'v044FacultyTimetable'])->name('pmc-timetable.index');
    Route::get('pmc-availability', [\App\Http\Controllers\Academics\PmcOperatingController::class, 'v046MyAvailability'])->name('pmc-availability.index');
    Route::post('pmc-availability', [\App\Http\Controllers\Academics\PmcOperatingController::class, 'v046SubmitFacultyAvailability'])->name('pmc-availability.store');

    // Study Materials
    Route::get('materials',          [Teacher\MaterialController::class, 'index'])->name('materials.index');
    Route::get('materials/create',   [Teacher\MaterialController::class, 'create'])->name('materials.create');
    Route::post('materials',         [Teacher\MaterialController::class, 'store'])->name('materials.store');
    Route::delete('materials/{material}', [Teacher\MaterialController::class, 'destroy'])->name('materials.destroy');

    // Assignments
    Route::get('assignments',               [Teacher\AssignmentController::class, 'index'])->name('assignments.index');
    Route::get('assignments/create',        [Teacher\AssignmentController::class, 'create'])->name('assignments.create');
    Route::post('assignments',              [Teacher\AssignmentController::class, 'store'])->name('assignments.store');
    Route::get('assignments/{assignment}/submissions', [Teacher\AssignmentController::class, 'submissions'])->name('assignments.submissions');
    Route::post('assignments/submissions/{submission}/grade', [Teacher\AssignmentController::class, 'grade'])->name('assignments.grade');

    // Mentor dashboard
    Route::get('mentor',                        [Teacher\MentorController::class, 'index'])->name('mentor.index');
    Route::get('mentor/{student}',              [Teacher\MentorController::class, 'mentee'])->name('mentor.mentee');
    Route::post('mentor/{student}/message',     [Teacher\MentorController::class, 'sendMessage'])->name('mentor.message');
    Route::post('mentor/{student}/meeting',     [Teacher\MentorController::class, 'scheduleMeeting'])->name('mentor.meeting');

    // Subject Announcements
    Route::get('announcements',              [Teacher\AnnouncementController::class, 'index'])->name('announcements.index');
    Route::post('announcements',             [Teacher\AnnouncementController::class, 'store'])->name('announcements.store');
    Route::delete('announcements/{announcement}', [Teacher\AnnouncementController::class, 'destroy'])->name('announcements.destroy');

    // Course Feedback (read-only view)
    Route::get('feedback', [Teacher\FeedbackViewController::class, 'index'])->name('feedback.index');
});

// ── Student routes ──────────────────────────────────────────────────────────
Route::prefix('student')->name('student.')->middleware(['auth', 'role:student|admin'])->group(function () {
    Route::get('dashboard',  [Student\DashboardController::class, 'index'])->name('dashboard');
    Route::get('attendance', [Student\AttendanceController::class, 'index'])->name('attendance');
    Route::get('results',    [Student\ResultController::class, 'index'])->name('results');
    Route::get('fees',       [Student\FeeController::class, 'index'])->name('fees');
    Route::get('transport', [Student\TransportController::class, 'index'])
        ->middleware('department.feature:TRANSPORT,transport.student_assignments')
        ->name('transport.index');
    Route::get('profile',    [Student\ProfileController::class, 'index'])->name('profile');
    Route::patch('profile',  [Student\ProfileController::class, 'update'])->name('profile.update');
    Route::get('notices',    [Student\NoticeController::class, 'index'])->name('notices');
    Route::get('notices/{notice}', [Student\NoticeController::class, 'show'])->name('notices.show');

    // PDF self-download
    Route::get('reports/grade-card/{semester}', [Student\ReportController::class, 'gradeCard'])->name('reports.grade-card');
    Route::get('reports/fee-receipt/{payment}', [Student\ReportController::class, 'feeReceipt'])->name('reports.fee-receipt');

    // Placements
    Route::middleware('department.feature:CMC,cmc.companies_drives')->group(function () {
        Route::get('placements/my-applications', [Student\PlacementController::class, 'myApplications'])->name('placements.applications');
        Route::get('placements', [Student\PlacementController::class, 'index'])->name('placements');
        Route::post('placements/{drive}/apply', [Student\PlacementController::class, 'apply'])->name('placements.apply');
    });

    // P9-2: Subject Registration
    Route::get('subjects', [\App\Http\Controllers\Student\SubjectRegistrationController::class, 'index'])->name('subjects.index');
    Route::post('subjects', [\App\Http\Controllers\Student\SubjectRegistrationController::class, 'store'])->name('subjects.store');
    Route::delete('subjects/{enrollment}', [\App\Http\Controllers\Student\SubjectRegistrationController::class, 'destroy'])->name('subjects.drop');
    // P9-3: Timetable
    Route::get('timetable', [\App\Http\Controllers\Student\TimetableController::class, 'index'])->name('timetable');
    Route::get('pmc-timetable', [\App\Http\Controllers\Academics\PmcOperatingController::class, 'v044StudentTimetable'])->name('pmc-timetable');
    Route::get('pmc-course-basket', [\App\Http\Controllers\Academics\PmcOperatingController::class, 'v090StudentCourseBasket'])->name('pmc-course-basket');
    Route::post('pmc-course-basket/acknowledgements', [\App\Http\Controllers\Academics\PmcOperatingController::class, 'v090SubmitStudentBasketAcknowledgement'])->name('pmc-course-basket.acknowledge');
    Route::get('pmc-elective-choices', [\App\Http\Controllers\Academics\PmcOperatingController::class, 'v091StudentElectiveChoices'])->name('pmc-elective-choices');
    Route::post('pmc-elective-choices', [\App\Http\Controllers\Academics\PmcOperatingController::class, 'v091SubmitStudentElectiveChoices'])->name('pmc-elective-choices.store');

    // Phase 5: Official Academic Transcript
    Route::get('transcript/download', [Student\TranscriptController::class, 'download'])->name('transcript.download');

    // Phase 5: Exam Admit Cards (static before wildcard)
    Route::get('admit-cards', [\App\Http\Controllers\Student\AdmitCardController::class, 'index'])->name('admit-cards.index');
    Route::get('admit-cards/{exam}/download', [\App\Http\Controllers\Student\AdmitCardController::class, 'download'])->name('admit-cards.download');

    // Phase 3: Student Grievances (static routes before wildcard)
    Route::get('grievances/create', [Student\GrievanceController::class, 'create'])->name('grievances.create');
    Route::post('grievances', [Student\GrievanceController::class, 'store'])->name('grievances.store');
    Route::get('grievances', [Student\GrievanceController::class, 'index'])->name('grievances.index');
    Route::get('grievances/{grievance}', [Student\GrievanceController::class, 'show'])->name('grievances.show');

    // Notification Preferences
    Route::get('notifications', [\App\Http\Controllers\Student\NotificationPreferenceController::class, 'edit'])->name('notifications.edit');
    Route::put('notifications', [\App\Http\Controllers\Student\NotificationPreferenceController::class, 'update'])->name('notifications.update');

    // Sprint 2: Attendance condonation
    Route::get('condonation', [\App\Http\Controllers\Student\AttendanceCondonationController::class, 'index'])->name('condonation.index');
    Route::get('condonation/create', [\App\Http\Controllers\Student\AttendanceCondonationController::class, 'create'])->name('condonation.create');
    Route::post('condonation', [\App\Http\Controllers\Student\AttendanceCondonationController::class, 'store'])->name('condonation.store');

    // Sprint 2: Fee payment proof submission
    Route::get('fee-payment', [\App\Http\Controllers\Student\FeePaymentRequestController::class, 'index'])->name('fee-payment.index');
    Route::get('fee-payment/create', [\App\Http\Controllers\Student\FeePaymentRequestController::class, 'create'])->name('fee-payment.create');
    Route::post('fee-payment', [\App\Http\Controllers\Student\FeePaymentRequestController::class, 'store'])->name('fee-payment.store');

    // Sprint 2: Document requests
    Route::get('documents', [\App\Http\Controllers\Student\DocumentRequestController::class, 'index'])->name('documents.index');
    Route::get('documents/request', [\App\Http\Controllers\Student\DocumentRequestController::class, 'create'])->name('documents.create');
    Route::post('documents', [\App\Http\Controllers\Student\DocumentRequestController::class, 'store'])->name('documents.store');
    Route::get('documents/{documentRequest}/download', [\App\Http\Controllers\Student\DocumentRequestController::class, 'download'])->name('documents.download');

    // Sprint 2: Grievance follow-up comments + close
    Route::post('grievances/{grievance}/comment', [\App\Http\Controllers\Student\GrievanceController::class, 'addComment'])->name('grievances.comment');
    Route::post('grievances/{grievance}/close', [\App\Http\Controllers\Student\GrievanceController::class, 'close'])->name('grievances.close');

    // Sprint 1: Attendance drill-down
    Route::get('attendance/{subject}/sessions', [\App\Http\Controllers\Student\AttendanceController::class, 'sessions'])->name('attendance.sessions');

    // Sprint 1: Study Materials
    Route::get('courses/{subject}/materials', [\App\Http\Controllers\Student\StudyMaterialController::class, 'index'])->name('materials.index');

    // Sprint 1: Assignments
    Route::get('assignments', [\App\Http\Controllers\Student\AssignmentController::class, 'index'])->name('assignments.index');
    Route::get('assignments/{assignment}', [\App\Http\Controllers\Student\AssignmentController::class, 'show'])->name('assignments.show');
    Route::post('assignments/{assignment}/submit', [\App\Http\Controllers\Student\AssignmentController::class, 'submit'])->name('assignments.submit');

    // Sprint 1: Quizzes
    Route::get('quizzes', [\App\Http\Controllers\Student\QuizController::class, 'index'])->name('quizzes.index');
    Route::get('quizzes/{quiz}', [\App\Http\Controllers\Student\QuizController::class, 'show'])->name('quizzes.show');
    Route::post('quizzes/{quiz}/start', [\App\Http\Controllers\Student\QuizController::class, 'start'])->name('quizzes.start');
    Route::post('quizzes/{quiz}/submit', [\App\Http\Controllers\Student\QuizController::class, 'submitAttempt'])->name('quizzes.submit');
    Route::get('quizzes/{quiz}/result', [\App\Http\Controllers\Student\QuizController::class, 'result'])->name('quizzes.result');

    // Sprint 1: Leave Applications
    Route::get('leave', [\App\Http\Controllers\Student\LeaveController::class, 'index'])->name('leave.index');
    Route::get('leave/create', [\App\Http\Controllers\Student\LeaveController::class, 'create'])->name('leave.create');
    Route::post('leave', [\App\Http\Controllers\Student\LeaveController::class, 'store'])->name('leave.store');

    // Sprint 1: Academic Calendar
    Route::get('calendar', [\App\Http\Controllers\Student\AcademicCalendarController::class, 'index'])->name('calendar.index');

    // Sprint 1: Subject Announcements (per subject)
    Route::get('courses/{subject}/announcements', [\App\Http\Controllers\Student\SubjectAnnouncementController::class, 'index'])->name('announcements.index');

    // Sprint 1: Course Content Hub (materials + announcements overview)
    Route::get('courses', [\App\Http\Controllers\Student\CourseHubController::class, 'index'])->name('courses.index');
    Route::get('courses/{subject}', [\App\Http\Controllers\Student\CourseHubController::class, 'show'])->name('courses.show');

    // Sprint 3: Exam registration
    Route::get('exam-registration', [\App\Http\Controllers\Student\ExamRegistrationController::class, 'index'])->name('exam-reg.index');
    Route::post('exam-registration/{exam}/register', [\App\Http\Controllers\Student\ExamRegistrationController::class, 'register'])->name('exam-reg.register');

    // Sprint 3: Marks appeals
    Route::get('appeals/create', [\App\Http\Controllers\Student\MarksAppealController::class, 'create'])->name('appeals.create');
    Route::post('appeals', [\App\Http\Controllers\Student\MarksAppealController::class, 'store'])->name('appeals.store');
    Route::get('appeals', [\App\Http\Controllers\Student\MarksAppealController::class, 'index'])->name('appeals.index');

    // Sprint 3: Scholarships
    Route::get('scholarships', [\App\Http\Controllers\Student\ScholarshipController::class, 'index'])->name('scholarships.index');
    Route::post('scholarships/{scheme}/apply', [\App\Http\Controllers\Student\ScholarshipController::class, 'apply'])->name('scholarships.apply');

    // Sprint 3: Mentor
    Route::get('mentor', [\App\Http\Controllers\Student\MentorController::class, 'index'])->name('mentor.index');
    Route::post('mentor/meeting', [\App\Http\Controllers\Student\MentorController::class, 'requestMeeting'])->name('mentor.meeting');
    Route::post('mentor/message', [\App\Http\Controllers\Student\MentorController::class, 'sendMessage'])->name('mentor.message');

    // Sprint 3: Course feedback
    Route::get('feedback', [\App\Http\Controllers\Student\CourseFeedbackController::class, 'index'])->name('feedback.index');
    Route::get('feedback/{subject}', [\App\Http\Controllers\Student\CourseFeedbackController::class, 'create'])->name('feedback.create');
    Route::post('feedback/{subject}', [\App\Http\Controllers\Student\CourseFeedbackController::class, 'store'])->name('feedback.store');

    // Sprint 3: Resume builder
    Route::get('resume', [\App\Http\Controllers\Student\ResumeController::class, 'index'])->name('resume.index');
    Route::post('resume', [\App\Http\Controllers\Student\ResumeController::class, 'save'])->name('resume.save');

    // Sprint 3: Career events
    Route::middleware('department.feature:CMC,cmc.companies_drives')->group(function () {
        Route::get('career-events', [\App\Http\Controllers\Student\CareerEventController::class, 'index'])->name('career-events.index');
        Route::post('career-events/{event}/register', [\App\Http\Controllers\Student\CareerEventController::class, 'register'])->name('career-events.register');
        Route::delete('career-events/{event}/register', [\App\Http\Controllers\Student\CareerEventController::class, 'cancel'])->name('career-events.cancel');
    });

    // Sprint 4
    Route::get('discussions/{subject}', [\App\Http\Controllers\Student\DiscussionController::class, 'index'])->name('discussions.index');
    Route::post('discussions/{subject}', [\App\Http\Controllers\Student\DiscussionController::class, 'store'])->name('discussions.store');
    Route::get('discussions/{subject}/{discussion}', [\App\Http\Controllers\Student\DiscussionController::class, 'show'])->name('discussions.show');
    Route::post('discussions/{subject}/{discussion}/reply', [\App\Http\Controllers\Student\DiscussionController::class, 'reply'])->name('discussions.reply');
    Route::post('discussions/{subject}/{discussion}/resolve', [\App\Http\Controllers\Student\DiscussionController::class, 'markResolved'])->name('discussions.resolve');
    Route::get('internships', [\App\Http\Controllers\Student\InternshipViewController::class, 'index'])
        ->middleware('department.feature:CMC,cmc.internships')
        ->name('internships.index');
    Route::get('alumni', [\App\Http\Controllers\Student\AlumniController::class, 'index'])
        ->middleware('department.feature:CMC,cmc.alumni')
        ->name('alumni.index');
    Route::get('promotion-status', [\App\Http\Controllers\Student\PromotionStatusController::class, 'index'])->name('promotion.index');
    Route::get('academic-summary', [\App\Http\Controllers\Student\AcademicSummaryController::class, 'index'])->name('summary.index');

    // Hostel Outpass
    Route::middleware('department.feature:HOSTEL,hostel.outpasses')->group(function () {
        Route::get('hostel/outpass', [\App\Http\Controllers\Student\HostelController::class, 'outpassIndex'])->name('hostel.outpass');
        Route::post('hostel/outpass', [\App\Http\Controllers\Student\HostelController::class, 'outpassStore'])->name('hostel.outpass.store');
    });
    Route::middleware('department.feature:HOSTEL,hostel.complaints')->group(function () {
        Route::get('hostel/complaints', [\App\Http\Controllers\Student\HostelController::class, 'complaintsIndex'])->name('hostel.complaints.index');
        Route::post('hostel/complaints', [\App\Http\Controllers\Student\HostelController::class, 'complaintStore'])->name('hostel.complaints.store');
    });

    // Library
    Route::get('library', [\App\Http\Controllers\Student\LibraryController::class, 'index'])
        ->middleware('department.feature:LIB,library.catalog')
        ->name('library.index');
    Route::post('library/reservations', [\App\Http\Controllers\Student\LibraryController::class, 'reserve'])
        ->middleware('department.feature:LIB,library.catalog')
        ->name('library.reservations.store');
    Route::post('library/reservations/{reservation}/cancel', [\App\Http\Controllers\Student\LibraryController::class, 'cancelReservation'])
        ->middleware('department.feature:LIB,library.catalog')
        ->name('library.reservations.cancel');
});

// ── Parent routes ────────────────────────────────────────────────────────────
Route::middleware(['auth', 'role:parent|admin'])->prefix('parent')->name('parent.')->group(function () {
    Route::get('dashboard', [ParentController\DashboardController::class, 'index'])->name('dashboard');
    Route::get('children',  [ParentController\DashboardController::class, 'children'])->name('children');
    Route::get('children/{student}/attendance', [ParentController\DashboardController::class, 'attendance'])->name('children.attendance');
    Route::get('children/{student}/results',    [ParentController\DashboardController::class, 'results'])->name('children.results');
    Route::get('children/{student}/fees',       [ParentController\DashboardController::class, 'fees'])->name('children.fees');
    Route::get('notices', [ParentController\DashboardController::class, 'notices'])->name('notices');
});

// ── Dean Academics ──────────────────────────────────────────────────────────
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

// ── Program Chair / HOD ──────────────────────────────────────────────────────
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

// ── HOD (Head of Department) ─────────────────────────────────────────────────
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

// ── Exam Cell ────────────────────────────────────────────────────────────────
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

// ── Accounts ─────────────────────────────────────────────────────────────────
Route::middleware(['auth', 'role:accounts_officer|admin|director'])->prefix('accounts')->name('accounts.')->group(function () {
    Route::get('dashboard', [Departmental\AccountsController::class, 'dashboard'])
        ->middleware('department.feature:ACC,accounts.dashboard')
        ->name('dashboard');
    Route::middleware('department.feature:ACC,accounts.fee_collection')->group(function () {
        Route::get('fee-collections', [Departmental\AccountsController::class, 'feeCollections'])->name('fee-collections');
        Route::get('outstanding', [Departmental\AccountsController::class, 'outstanding'])->name('outstanding');
        Route::get('fee-demands/{feeDemand}/demand-letter', [Departmental\AccountsController::class, 'demandLetter'])->name('fee-demands.demand-letter');
    });
    Route::middleware('department.feature:ACC,accounts.reconciliation')->group(function () {
        Route::get('admission-payments', [Departmental\AccountsController::class, 'admissionPayments'])->name('admission-payments');
        Route::get('reconciliation', [Departmental\AccountsController::class, 'reconciliation'])->name('reconciliation');
    });
    Route::middleware('department.feature:ACC,accounts.reports_exports')->group(function () {
        Route::get('reports', [Departmental\AccountsController::class, 'reports'])->name('reports');
        Route::get('export-fee-collections', [Departmental\AccountsController::class, 'exportFeeCollections'])->name('export-fee-collections');
        Route::get('export-admission-payments', [Departmental\AccountsController::class, 'exportAdmissionPayments'])->name('export-admission-payments');
        Route::get('export-outstanding', [Departmental\AccountsController::class, 'exportOutstanding'])->name('export-outstanding');
    });
});

// ── Shared Approval Inbox (all roles) ────────────────────────────────────────
Route::middleware('auth')->prefix('approvals')->name('approvals.')->group(function () {
    Route::get('inbox', [ApprovalController::class, 'inbox'])->name('inbox');
    Route::get('{approval}/chain', [ApprovalController::class, 'chain'])->name('chain');
    Route::post('{approval}/approve', [ApprovalController::class, 'approve'])->name('approve');
    Route::post('{approval}/reject', [ApprovalController::class, 'reject'])->name('reject');
});

// ── CMC / Placement routes ─────────────────────────────────────────────────
Route::middleware(['auth', 'role:admin|cmc|dean_academics|program_chair'])->prefix('cmc')->name('cmc.')->group(function () {
    Route::get('dashboard', [Departmental\CmcController::class, 'dashboard'])
        ->middleware('department.feature:CMC,cmc.dashboard')
        ->name('dashboard');
    // Placement Drives
    Route::middleware('department.feature:CMC,cmc.companies_drives')->group(function () {
        Route::get('drives', [Departmental\CmcController::class, 'drives'])->name('drives');
        Route::get('drives/export', [Departmental\CmcController::class, 'exportDrives'])->name('drives.export');
        Route::get('drives/create', [Departmental\CmcController::class, 'createDrive'])->name('drives.create');
        Route::post('drives', [Departmental\CmcController::class, 'storeDrive'])->name('drives.store');
        Route::get('drives/{drive}/edit', [Departmental\CmcController::class, 'editDrive'])->name('drives.edit');
        Route::put('drives/{drive}', [Departmental\CmcController::class, 'updateDrive'])->name('drives.update');
        Route::delete('drives/{drive}', [Departmental\CmcController::class, 'destroyDrive'])->name('drives.destroy');
        Route::get('drives/{drive}/applications', [Departmental\CmcController::class, 'driveApplications'])->name('drives.applications');
        Route::get('drives/{drive}/applications/export', [Departmental\CmcController::class, 'exportDriveApplications'])->name('drives.applications.export');
        Route::patch('placements/{placement}/status', [Departmental\CmcController::class, 'updateApplicationStatus'])->name('placements.update-status');
        Route::get('placements', [Departmental\CmcController::class, 'placements'])->name('placements');
        Route::get('placements/export', [Departmental\CmcController::class, 'exportPlacements'])->name('placements.export');
        Route::get('companies', [Departmental\CmcController::class, 'companies'])->name('companies');
        Route::get('companies/export', [Departmental\CmcController::class, 'exportCompanies'])->name('companies.export');
        Route::get('companies/create', [Departmental\CmcController::class, 'createCompany'])->name('companies.create');
        Route::post('companies', [Departmental\CmcController::class, 'storeCompany'])->name('companies.store');
        Route::get('companies/{company}/edit', [Departmental\CmcController::class, 'editCompany'])->name('companies.edit');
        Route::put('companies/{company}', [Departmental\CmcController::class, 'updateCompany'])->name('companies.update');
        Route::get('events', [Departmental\CmcController::class, 'events'])->name('events');
        Route::get('events/export', [Departmental\CmcController::class, 'exportEvents'])->name('events.export');
        Route::get('events/create', [Departmental\CmcController::class, 'createEvent'])->name('events.create');
        Route::post('events', [Departmental\CmcController::class, 'storeEvent'])->name('events.store');
        Route::get('events/{event}/edit', [Departmental\CmcController::class, 'editEvent'])->name('events.edit');
        Route::put('events/{event}', [Departmental\CmcController::class, 'updateEvent'])->name('events.update');
        Route::delete('events/{event}', [Departmental\CmcController::class, 'destroyEvent'])->name('events.destroy');
        Route::get('events/{event}/registrations', [Departmental\CmcController::class, 'eventRegistrations'])->name('events.registrations');
        Route::get('events/{event}/registrations/export', [Departmental\CmcController::class, 'exportEventRegistrations'])->name('events.registrations.export');
        Route::patch('events/{event}/registrations/{registration}/attendance', [Departmental\CmcController::class, 'updateEventAttendance'])->name('events.registrations.attendance');
    });
    Route::middleware('department.feature:CMC,cmc.analytics_exports')->group(function () {
        Route::get('analytics', [Departmental\CmcController::class, 'analytics'])->name('analytics');
        Route::get('placement-stats', [Departmental\PlacementStatsController::class, 'index'])->name('placement-stats');
    });
    // Internships
    Route::middleware('department.feature:CMC,cmc.internships')->group(function () {
        Route::get('internships', [Departmental\InternshipController::class, 'index'])->name('internships.index');
        Route::get('internships/create', [Departmental\InternshipController::class, 'create'])->name('internships.create');
        Route::post('internships', [Departmental\InternshipController::class, 'store'])->name('internships.store');
        Route::get('internships/{internship}', [Departmental\InternshipController::class, 'show'])->name('internships.show');
        Route::post('internships/{internship}/complete', [Departmental\InternshipController::class, 'complete'])->name('internships.complete');
    });
    // Alumni
    Route::middleware('department.feature:CMC,cmc.alumni')->group(function () {
        Route::get('alumni', [Departmental\AlumniController::class, 'index'])->name('alumni.index');
        Route::get('alumni/create', [Departmental\AlumniController::class, 'create'])->name('alumni.create');
        Route::post('alumni', [Departmental\AlumniController::class, 'store'])->name('alumni.store');
        Route::post('alumni/{alumniProfile}/verify', [Departmental\AlumniController::class, 'verify'])->name('alumni.verify');
    });
});

// ── Director ─────────────────────────────────────────────────────────────────
Route::middleware(['auth', 'role:director|admin'])->prefix('director')->name('director.')->group(function () {
    Route::get('dashboard', [Departmental\DirectorController::class, 'dashboard'])->name('dashboard');
    Route::get('programs',  [Departmental\DirectorController::class, 'programs'])->name('programs');
    Route::get('reports',   [Departmental\DirectorController::class, 'reports'])->name('reports');
});

// ── Claude Analysis (with prompt caching) ───────────────────────────────────
Route::middleware(['auth', 'role:admin|dean_academics|program_chair'])->prefix('api')->group(function () {
    Route::get('claude/student/{studentId}/analyze', [ClaudeAnalysisController::class, 'analyzeStudent'])->name('claude.analyze-student');
    Route::get('claude/applicant/{applicantId}/evaluate', [ClaudeAnalysisController::class, 'evaluateAdmission'])->name('claude.evaluate-applicant');
    Route::post('claude/curriculum/{programId}/recommend', [ClaudeAnalysisController::class, 'recommendCurriculum'])->name('claude.recommend-curriculum');
});

// ── Auth (Breeze) ───────────────────────────────────────────────────────────
Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
