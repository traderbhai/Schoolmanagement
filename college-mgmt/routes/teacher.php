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

// -- Teacher routes ----------------------------------------------------------
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


