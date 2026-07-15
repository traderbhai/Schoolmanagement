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

// -- Applicant Routes -------------------------------------------------------
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

