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

// -- Shared Approval Inbox (all roles) ----------------------------------------
Route::middleware('auth')->prefix('approvals')->name('approvals.')->group(function () {
    Route::get('inbox', [ApprovalController::class, 'inbox'])->name('inbox');
    Route::get('{approval}/chain', [ApprovalController::class, 'chain'])->name('chain');
    Route::post('{approval}/approve', [ApprovalController::class, 'approve'])->name('approve');
    Route::post('{approval}/reject', [ApprovalController::class, 'reject'])->name('reject');
});


