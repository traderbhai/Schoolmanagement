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

// -- CMC / Placement routes -------------------------------------------------
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


