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

// -- Accounts -----------------------------------------------------------------
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
        Route::get('scholarship-disbursements', [Departmental\AccountsController::class, 'scholarshipDisbursements'])->name('scholarship-disbursements');
        Route::get('reconciliation', [Departmental\AccountsController::class, 'reconciliation'])->name('reconciliation');
    });
    Route::middleware('department.feature:ACC,accounts.reports_exports')->group(function () {
        Route::get('reports', [Departmental\AccountsController::class, 'reports'])->name('reports');
        Route::get('export-fee-collections', [Departmental\AccountsController::class, 'exportFeeCollections'])->name('export-fee-collections');
        Route::get('export-admission-payments', [Departmental\AccountsController::class, 'exportAdmissionPayments'])->name('export-admission-payments');
        Route::get('export-outstanding', [Departmental\AccountsController::class, 'exportOutstanding'])->name('export-outstanding');
    });
});


