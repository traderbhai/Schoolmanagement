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

Route::middleware(['auth'])->prefix('admission/partners')->name('admission.partner-portal.')->group(function () {
    Route::get('dashboard', [Admission\PartnerController::class, 'portalDashboard'])->name('dashboard');
    Route::get('leads', [Admission\PartnerController::class, 'portalLeads'])->name('leads');
    Route::post('leads', [Admission\PartnerController::class, 'portalSubmitLead'])->name('leads.store');
});

// -- Admission Team routes ---------------------------------------------------
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


