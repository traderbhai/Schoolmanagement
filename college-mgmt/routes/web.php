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
use App\Http\Controllers\Departmental;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\StatusTrackerController;
use Illuminate\Support\Facades\Route;

// ── Public Application Status Tracker ─────────────────────────────────────
Route::get('/track', [StatusTrackerController::class, 'index'])->name('public.status-tracker.index');
Route::post('/track', [StatusTrackerController::class, 'track'])->name('public.status-tracker.track');

// ── Public Application Routes ──────────────────────────────────────────────
Route::get('/apply', [ApplyController::class, 'index'])->name('apply');
Route::get('/apply/{program}', [ApplyController::class, 'show'])->name('apply.program');
Route::post('/apply/{program}', [ApplyController::class, 'register'])->name('apply.program.register');

// ── Applicant Routes ───────────────────────────────────────────────────────
Route::prefix('applicant')->name('applicant.')->middleware(['auth', 'role:applicant|admin'])->group(function () {
    Route::get('dashboard', [ApplicantDashboard::class, 'index'])->name('dashboard');
    Route::get('application', [ApplicantApplication::class, 'show'])->name('application.show');
    // Static route BEFORE parameterized
    Route::post('application/submit', [ApplicantApplication::class, 'submit'])->name('application.submit');
    Route::post('application/{section}', [ApplicantApplication::class, 'saveSection'])->name('application.section');
    Route::get('documents', [ApplicantDocument::class, 'index'])->name('documents.index');
    Route::post('documents/{requiredDocument}', [ApplicantDocument::class, 'store'])->name('documents.store');
    Route::delete('documents/{document}', [ApplicantDocument::class, 'destroy'])->name('documents.destroy');
    Route::get('status', [ApplicantStatus::class, 'index'])->name('status');
    // Fees — static route before parameterized
    Route::get('fees', [ApplicantPayment::class, 'index'])->name('fees.index');
    Route::get('fees/payment/{payment}', [ApplicantPayment::class, 'show'])->name('fees.show');
    Route::post('fees/{installment}', [ApplicantPayment::class, 'store'])->name('fees.store');
    // Offer Letters
    Route::get('offer-letters', [\App\Http\Controllers\Applicant\OfferLetterController::class, 'index'])->name('offer-letters.index');
    Route::get('offer-letters/{offerLetter}', [\App\Http\Controllers\Applicant\OfferLetterController::class, 'show'])->name('offer-letters.show');
    Route::get('offer-letters/{offerLetter}/pdf', [\App\Http\Controllers\Applicant\OfferLetterController::class, 'downloadPdf'])->name('offer-letters.pdf');
    Route::post('offer-letters/{offerLetter}/accept', [\App\Http\Controllers\Applicant\OfferLetterController::class, 'accept'])->name('offer-letters.accept');
    Route::post('offer-letters/{offerLetter}/decline', [\App\Http\Controllers\Applicant\OfferLetterController::class, 'decline'])->name('offer-letters.decline');

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
        $user = auth()->user();
        if ($user->hasRole('admin'))     return redirect()->route('admin.dashboard');
        if ($user->hasRole('teacher'))   return redirect()->route('teacher.dashboard');
        if ($user->hasRole('parent'))    return redirect()->route('parent.dashboard');
        if ($user->hasRole('applicant')) return redirect()->route('applicant.dashboard');
        return redirect()->route('student.dashboard');
    }
    return view('welcome');
});

// Compatibility alias so Breeze tests and legacy redirects still work
Route::get('/dashboard', function () {
    $user = auth()->user();
    if ($user?->hasRole('admin'))     return redirect()->route('admin.dashboard');
    if ($user?->hasRole('teacher'))   return redirect()->route('teacher.dashboard');
    if ($user?->hasRole('parent'))    return redirect()->route('parent.dashboard');
    if ($user?->hasRole('applicant')) return redirect()->route('applicant.dashboard');
    return redirect()->route('student.dashboard');
})->middleware(['auth'])->name('dashboard');

// ── Admin routes ────────────────────────────────────────────────────────────
Route::prefix('admin')->name('admin.')->middleware(['auth', 'role:admin|dean_academics|program_chair|exam_cell|hod|accounts_officer'])->group(function () {
    Route::get('dashboard', [Admin\DashboardController::class, 'index'])->name('dashboard');

    Route::resource('departments',   Admin\DepartmentController::class);
    Route::resource('courses',       Admin\CourseController::class);

    // Academic Structure — Programs, Batches, Terms, Specializations
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
    Route::resource('students',      Admin\StudentController::class);
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
    Route::get('admission-config/{program}', [Admin\AdmissionConfigController::class, 'index'])->name('admin.admission-config.index');
    Route::get('admission-config/{program}/form', [Admin\AdmissionConfigController::class, 'editFormConfig'])->name('admin.admission-config.form');
    Route::post('admission-config/{program}/form', [Admin\AdmissionConfigController::class, 'updateFormConfig'])->name('admin.admission-config.form.update');
    Route::post('admission-config/{program}/documents', [Admin\AdmissionConfigController::class, 'storeDocument'])->name('admin.admission-config.documents.store');
    Route::put('admission-config/documents/{document}', [Admin\AdmissionConfigController::class, 'updateDocument'])->name('admin.admission-config.documents.update');
    Route::delete('admission-config/documents/{document}', [Admin\AdmissionConfigController::class, 'destroyDocument'])->name('admin.admission-config.documents.destroy');
    Route::post('admission-config/{program}/documents/seed-defaults', [Admin\AdmissionConfigController::class, 'seedDefaultDocuments'])->name('admin.admission-config.documents.seed');
    Route::post('admission-config/{program}/steps', [Admin\AdmissionConfigController::class, 'storeStep'])->name('admin.admission-config.steps.store');
    Route::put('admission-config/steps/{step}', [Admin\AdmissionConfigController::class, 'updateStep'])->name('admin.admission-config.steps.update');
    Route::delete('admission-config/steps/{step}', [Admin\AdmissionConfigController::class, 'destroyStep'])->name('admin.admission-config.steps.destroy');
    Route::post('admission-config/steps/{step}/parameters', [Admin\AdmissionConfigController::class, 'storeParameter'])->name('admin.admission-config.parameters.store');
    Route::delete('admission-config/parameters/{parameter}', [Admin\AdmissionConfigController::class, 'destroyParameter'])->name('admin.admission-config.parameters.destroy');
    Route::post('admission-config/{program}/fee-installments', [Admin\AdmissionConfigController::class, 'storeFeeInstallment'])->name('admin.admission-config.fee.store');
    Route::put('admission-config/fee-installments/{installment}', [Admin\AdmissionConfigController::class, 'updateFeeInstallment'])->name('admin.admission-config.fee.update');
    Route::delete('admission-config/fee-installments/{installment}', [Admin\AdmissionConfigController::class, 'destroyFeeInstallment'])->name('admin.admission-config.fee.destroy');

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

    // Export routes
    Route::get('students/export', [Admin\StudentController::class, 'export'])->name('students.export');
    Route::get('fees/export', [Admin\FeeController::class, 'export'])->name('fees.export');
    Route::get('attendance/export', [Admin\AttendanceController::class, 'export'])->name('attendance.export');

    // Settings
    Route::get('settings', [Admin\SettingsController::class, 'index'])->name('settings');
    Route::get('settings/branding', [Admin\SettingsController::class, 'branding'])->name('settings.branding');
    Route::post('settings/branding', [Admin\SettingsController::class, 'update'])->name('settings.update');
    Route::get('api-docs', [Admin\SettingsController::class, 'apiDocs'])->name('api-docs');

    // Global Search
    Route::get('search', [Admin\SearchController::class, 'index'])->name('search');

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

    // Audit Log (static routes before wildcards)
    Route::get('audit-log', [Admin\AuditController::class, 'index'])->name('audit.index');
    Route::get('audit-log/search', [Admin\AuditController::class, 'search'])->name('audit.search');
    Route::get('audit-log/{log}', [Admin\AuditController::class, 'show'])->name('audit.show');
});

// ── Academic routes ─────────────────────────────────────────────────────────
Route::middleware(['auth', 'role:dean_academics|program_chair|exam_cell|hod|accounts_officer|admin'])->prefix('academic')->name('academic.')->group(function () {
    // B2: Term Promotions
    Route::get('term-promotions', [Academic\TermPromotionController::class, 'index'])->name('term-promotions.index');
    Route::post('term-promotions/generate', [Academic\TermPromotionController::class, 'generate'])->name('term-promotions.generate');
    Route::get('term-promotions/{termPromotion}', [Academic\TermPromotionController::class, 'show'])->name('term-promotions.show');
    Route::post('term-promotions/{termPromotion}/approve', [Academic\TermPromotionController::class, 'approve'])->name('term-promotions.approve');
    Route::post('term-promotions/{termPromotion}/reject', [Academic\TermPromotionController::class, 'reject'])->name('term-promotions.reject');
    Route::post('term-promotions/bulk-approve', [Academic\TermPromotionController::class, 'bulkApprove'])->name('term-promotions.bulk-approve');

    // B3: Scholarships
    Route::resource('scholarships', Academic\ScholarshipController::class);

    // B3: Fee Demands
    Route::resource('fee-demands', Academic\FeeDemandController::class);
    Route::post('fee-demands/{feeDemand}/mark-paid', [Academic\FeeDemandController::class, 'markAsPaid'])->name('fee-demands.mark-paid');
    Route::post('fee-demands/generate-demands', [Academic\FeeDemandController::class, 'generateDemands'])->name('fee-demands.generate');

    // B5: Academic Calendar
    Route::resource('academic-calendars', Academic\AcademicCalendarController::class);
    Route::get('academic-calendars-events', [Academic\AcademicCalendarController::class, 'getEvents'])->name('academic-calendars.events');

    // Phase 3: Curriculum Changes
    Route::get('curriculum-changes', [Academic\CurriculumChangeController::class, 'index'])->name('curriculum-changes.index');
    Route::get('curriculum-changes/create', [Academic\CurriculumChangeController::class, 'create'])->name('curriculum-changes.create');
    Route::post('curriculum-changes', [Academic\CurriculumChangeController::class, 'store'])->name('curriculum-changes.store');
    Route::get('curriculum-changes/{curriculumChange}', [Academic\CurriculumChangeController::class, 'show'])->name('curriculum-changes.show');
    Route::post('curriculum-changes/{curriculumChange}/approve', [Academic\CurriculumChangeController::class, 'approve'])->name('curriculum-changes.approve');
    Route::post('curriculum-changes/{curriculumChange}/reject', [Academic\CurriculumChangeController::class, 'reject'])->name('curriculum-changes.reject');
});

// ── Admission Team routes ───────────────────────────────────────────────────
Route::middleware(['auth', 'role:admission_officer|admission_head|admin'])->prefix('admission')->name('admission.')->group(function () {
    Route::get('dashboard', [Admission\DashboardController::class, 'index'])->name('dashboard');
    Route::post('applicants/bulk-action', [Admission\ApplicantCrmController::class, 'bulkAction'])->name('applicants.bulk-action');
    Route::get('applicants', [Admission\ApplicantCrmController::class, 'index'])->name('applicants.index');
    Route::get('applicants/{applicant}', [Admission\ApplicantCrmController::class, 'show'])->name('applicants.show');
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
    Route::get('leads/analytics/dashboard', [Admission\LeadController::class, 'analytics'])->name('leads.analytics');
    Route::get('leads/import', [Admission\LeadImportController::class, 'showImportForm'])->name('leads.import');
    Route::post('leads/import', [Admission\LeadImportController::class, 'import'])->name('leads.import.post');
    Route::get('leads/export-csv', [Admission\LeadController::class, 'exportCsv'])->name('leads.export-csv');
    Route::get('leads/follow-ups/calendar', [Admission\LeadFollowUpController::class, 'calendar'])->name('leads.follow-ups.calendar');
    Route::patch('leads/follow-ups/{followUp}/complete', [Admission\LeadFollowUpController::class, 'complete'])->name('leads.follow-ups.complete');
    Route::get('leads/{lead}', [Admission\LeadController::class, 'show'])->name('leads.show');
    Route::post('leads/{lead}/contact', [Admission\LeadController::class, 'contactLead'])->name('leads.contact');
    Route::post('leads/{lead}/interested', [Admission\LeadController::class, 'markInterested'])->name('leads.interested');
    Route::post('leads/{lead}/not-interested', [Admission\LeadController::class, 'markNotInterested'])->name('leads.not-interested');
    Route::post('leads/{lead}/convert', [Admission\LeadController::class, 'convert'])->name('leads.convert');
    Route::post('leads/{lead}/assign', [Admission\LeadFollowUpController::class, 'assign'])->name('leads.assign');
    Route::post('leads/{lead}/follow-ups', [Admission\LeadFollowUpController::class, 'store'])->name('leads.follow-ups.store');

    // Document Verification Queue (static routes BEFORE {document} parameterized)
    Route::get('documents/queue', [Admission\DocumentVerificationController::class, 'pendingQueue'])->name('documents.queue');
    Route::post('documents/bulk-verify', [Admission\DocumentVerificationController::class, 'bulkVerify'])->name('documents.bulk-verify');
    Route::post('documents/{document}/verify', [Admission\DocumentVerificationController::class, 'verify'])->name('documents.verify');
    Route::post('documents/{document}/reject', [Admission\DocumentVerificationController::class, 'reject'])->name('documents.reject');
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
    Route::get('merit-list/{program}/export', [Admission\MeritListController::class, 'exportMeritList'])->name('merit-list.export');
    Route::post('merit-list/{program}/bulk-decide', [Admission\MeritListController::class, 'bulkDecide'])->name('merit-list.bulk-decide');
    Route::post('merit-list/entries/{entry}/decide', [Admission\MeritListController::class, 'updateDecision'])->name('merit-list.decide');

    // Offer Letters (static routes before {offerLetter} parameter)
    Route::get('offer-letters/{program}', [Admission\OfferLetterController::class, 'index'])->name('offer-letters.index');
    Route::post('offer-letters/{program}/generate', [Admission\OfferLetterController::class, 'generate'])->name('offer-letters.generate');
    Route::post('offer-letters/{program}/bulk-generate', [Admission\OfferLetterController::class, 'bulkGenerate'])->name('offer-letters.bulk-generate');
    Route::get('offer-letters/view/{offerLetter}', [Admission\OfferLetterController::class, 'show'])->name('offer-letters.show');
    Route::get('offer-letters/export/{offerLetter}', [Admission\OfferLetterController::class, 'exportPdf'])->name('offer-letters.export');
    Route::post('offer-letters/{offerLetter}/accept', [Admission\OfferLetterController::class, 'accept'])->name('offer-letters.accept');
    Route::post('offer-letters/{offerLetter}/decline', [Admission\OfferLetterController::class, 'decline'])->name('offer-letters.decline');

    // Payment Verification (static routes before parameterized)
    Route::get('payments/queue', [Admission\PaymentVerificationController::class, 'pendingQueue'])->name('payments.queue');
    Route::get('payments/{program}', [Admission\PaymentVerificationController::class, 'index'])->name('payments.index');
    Route::get('applicants/{applicant}/payments', [Admission\PaymentVerificationController::class, 'applicantPayments'])->name('applicants.payments');
    Route::post('payments/{payment}/verify', [Admission\PaymentVerificationController::class, 'verify'])->name('payments.verify');
    Route::post('payments/{payment}/reject', [Admission\PaymentVerificationController::class, 'reject'])->name('payments.reject');
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
    Route::get('reports', [Admission\ReportingController::class, 'index'])->name('reports.index');

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

    // P4-3: CSV Exports (applicants static route moved here; leads/export-csv is also before {lead} wildcard in its section)
    Route::get('applicants/export-csv', [Admission\ApplicantCrmController::class, 'exportCsv'])->name('applicants.export-csv');
    Route::get('merit-list/{program}/export-csv', [Admission\MeritListController::class, 'exportCsv'])->name('merit-list.export-csv');

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
    Route::get('reports/export-pdf', [Admission\ReportingController::class, 'exportPdf'])->name('reports.export-pdf');
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
});

// ── Student routes ──────────────────────────────────────────────────────────
Route::prefix('student')->name('student.')->middleware(['auth', 'role:student|admin'])->group(function () {
    Route::get('dashboard',  [Student\DashboardController::class, 'index'])->name('dashboard');
    Route::get('attendance', [Student\AttendanceController::class, 'index'])->name('attendance');
    Route::get('results',    [Student\ResultController::class, 'index'])->name('results');
    Route::get('fees',       [Student\FeeController::class, 'index'])->name('fees');
    Route::get('profile',    [Student\ProfileController::class, 'index'])->name('profile');
    Route::patch('profile',  [Student\ProfileController::class, 'update'])->name('profile.update');
    Route::get('notices',    [Student\NoticeController::class, 'index'])->name('notices');
    Route::get('notices/{notice}', [Student\NoticeController::class, 'show'])->name('notices.show');

    // PDF self-download
    Route::get('reports/grade-card/{semester}', [Student\ReportController::class, 'gradeCard'])->name('reports.grade-card');
    Route::get('reports/fee-receipt/{payment}', [Student\ReportController::class, 'feeReceipt'])->name('reports.fee-receipt');

    // Placements
    Route::get('placements/my-applications', [Student\PlacementController::class, 'myApplications'])->name('placements.applications');
    Route::get('placements', [Student\PlacementController::class, 'index'])->name('placements');
    Route::post('placements/{drive}/apply', [Student\PlacementController::class, 'apply'])->name('placements.apply');

    // P9-2: Subject Registration
    Route::get('subjects', [\App\Http\Controllers\Student\SubjectRegistrationController::class, 'index'])->name('subjects.index');
    Route::post('subjects', [\App\Http\Controllers\Student\SubjectRegistrationController::class, 'store'])->name('subjects.store');
    Route::delete('subjects/{enrollment}', [\App\Http\Controllers\Student\SubjectRegistrationController::class, 'destroy'])->name('subjects.drop');
    // P9-3: Timetable
    Route::get('timetable', [\App\Http\Controllers\Student\TimetableController::class, 'index'])->name('timetable');

    // Phase 3: Student Grievances (static routes before wildcard)
    Route::get('grievances/create', [Student\GrievanceController::class, 'create'])->name('grievances.create');
    Route::post('grievances', [Student\GrievanceController::class, 'store'])->name('grievances.store');
    Route::get('grievances', [Student\GrievanceController::class, 'index'])->name('grievances.index');
    Route::get('grievances/{grievance}', [Student\GrievanceController::class, 'show'])->name('grievances.show');
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
    Route::get('dashboard',  [Departmental\ProgramChairController::class, 'dashboard'])->name('dashboard');
    Route::get('students',   [Departmental\ProgramChairController::class, 'students'])->name('students');
    Route::get('curriculum', [Departmental\ProgramChairController::class, 'curriculum'])->name('curriculum');
    Route::get('timetable',  [Departmental\ProgramChairController::class, 'timetable'])->name('timetable');
    Route::get('exams',      [Departmental\ProgramChairController::class, 'exams'])->name('exams');
    // P5-5: Approval Workflow Routes for Program Chair
    Route::get('approvals', [Departmental\ProgramChairController::class, 'approvals'])->name('approvals');
    Route::post('approvals/{approval}/approve', [Departmental\ProgramChairController::class, 'approve'])->name('approve');
    Route::post('approvals/{approval}/reject', [Departmental\ProgramChairController::class, 'reject'])->name('reject');
});

// ── HOD (Head of Department) ─────────────────────────────────────────────────
Route::middleware(['auth', 'role:hod|admin'])->prefix('hod')->name('hod.')->group(function () {
    Route::get('dashboard', [Departmental\HodController::class, 'dashboard'])->name('dashboard');
    // P5-5: Approval Workflow Routes for HOD
    Route::get('approvals', [Departmental\HodController::class, 'approvals'])->name('approvals');
    Route::post('approvals/{approval}/approve', [Departmental\HodController::class, 'approve'])->name('approve');
    Route::post('approvals/{approval}/reject', [Departmental\HodController::class, 'reject'])->name('reject');
    // Phase 3: Grievance Management
    Route::get('grievances', [Departmental\GrievanceManagementController::class, 'index'])->name('grievances.index');
    Route::get('grievances/{grievance}', [Departmental\GrievanceManagementController::class, 'show'])->name('grievances.show');
    Route::post('grievances/{grievance}/resolve', [Departmental\GrievanceManagementController::class, 'resolve'])->name('grievances.resolve');
    Route::post('grievances/{grievance}/escalate', [Departmental\GrievanceManagementController::class, 'escalate'])->name('grievances.escalate');
});

// ── Exam Cell ────────────────────────────────────────────────────────────────
Route::middleware(['auth', 'role:exam_cell|dean_academics|admin'])->prefix('exam-cell')->name('exam-cell.')->group(function () {
    Route::get('dashboard',                          [Departmental\ExamCellController::class, 'dashboard'])->name('dashboard');
    Route::get('exams',                              [Departmental\ExamCellController::class, 'exams'])->name('exams');
    Route::get('results',                            [Departmental\ExamCellController::class, 'results'])->name('results');
    Route::get('results/{exam}/grade-sheet',         [Departmental\ExamCellController::class, 'gradeSheet'])->name('grade-sheet');
    Route::post('results/{exam}/publish',            [Departmental\ExamCellController::class, 'publishResults'])->name('publish');
});

// ── Accounts ─────────────────────────────────────────────────────────────────
Route::middleware(['auth', 'role:accounts_officer|admin'])->prefix('accounts')->name('accounts.')->group(function () {
    Route::get('dashboard',          [Departmental\AccountsController::class, 'dashboard'])->name('dashboard');
    Route::get('fee-collections',    [Departmental\AccountsController::class, 'feeCollections'])->name('fee-collections');
    Route::get('outstanding',        [Departmental\AccountsController::class, 'outstanding'])->name('outstanding');
    Route::get('admission-payments', [Departmental\AccountsController::class, 'admissionPayments'])->name('admission-payments');
    Route::get('reports',            [Departmental\AccountsController::class, 'reports'])->name('reports');
    Route::get('reconciliation', [Departmental\AccountsController::class, 'reconciliation'])->name('reconciliation');
    Route::get('export-fee-collections', [Departmental\AccountsController::class, 'exportFeeCollections'])->name('export-fee-collections');
    Route::get('export-admission-payments', [Departmental\AccountsController::class, 'exportAdmissionPayments'])->name('export-admission-payments');
    Route::get('export-outstanding', [Departmental\AccountsController::class, 'exportOutstanding'])->name('export-outstanding');
    Route::get('fee-demands/{feeDemand}/demand-letter', [Departmental\AccountsController::class, 'demandLetter'])->name('fee-demands.demand-letter');
});

// ── Auth (Breeze) ───────────────────────────────────────────────────────────
Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
