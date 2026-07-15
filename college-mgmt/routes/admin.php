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

// -- Admin routes ------------------------------------------------------------
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


