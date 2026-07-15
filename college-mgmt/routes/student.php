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

// -- Student routes ----------------------------------------------------------
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

// -- Parent routes ------------------------------------------------------------
Route::middleware(['auth', 'role:parent|admin'])->prefix('parent')->name('parent.')->group(function () {
    Route::get('dashboard', [ParentController\DashboardController::class, 'index'])->name('dashboard');
    Route::get('children',  [ParentController\DashboardController::class, 'children'])->name('children');
    Route::get('children/{student}/attendance', [ParentController\DashboardController::class, 'attendance'])->name('children.attendance');
    Route::get('children/{student}/results',    [ParentController\DashboardController::class, 'results'])->name('children.results');
    Route::get('children/{student}/fees',       [ParentController\DashboardController::class, 'fees'])->name('children.fees');
    Route::get('notices', [ParentController\DashboardController::class, 'notices'])->name('notices');
});


