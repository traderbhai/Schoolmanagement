<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\Admin;
use App\Http\Controllers\Student;
use App\Http\Controllers\Teacher;
use App\Http\Controllers\Parent as ParentController;
use App\Http\Controllers\ApplyController;
use App\Http\Controllers\Admission;
use App\Http\Controllers\Applicant\DashboardController as ApplicantDashboard;
use App\Http\Controllers\Applicant\ApplicationController as ApplicantApplication;
use App\Http\Controllers\Applicant\DocumentController as ApplicantDocument;
use App\Http\Controllers\Applicant\StatusController as ApplicantStatus;
use Illuminate\Support\Facades\Route;

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
    Route::get('documents', [ApplicantDocument::class, 'index'])->name('documents');
    Route::post('documents/{requiredDocument}', [ApplicantDocument::class, 'upload'])->name('documents.upload');
    Route::delete('documents/{document}', [ApplicantDocument::class, 'destroy'])->name('documents.destroy');
    Route::get('status', [ApplicantStatus::class, 'index'])->name('status');
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
Route::prefix('admin')->name('admin.')->middleware(['auth', 'role:admin'])->group(function () {
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
    Route::post('documents/{document}/verify', [Admission\ApplicantCrmController::class, 'verifyDocument'])->name('documents.verify');

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

// ── Auth (Breeze) ───────────────────────────────────────────────────────────
Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
