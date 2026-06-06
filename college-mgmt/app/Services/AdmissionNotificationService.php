<?php

namespace App\Services;

use App\Models\AdmissionPayment;
use App\Models\Applicant;
use App\Models\Notification;
use App\Models\OfferLetter;
use Illuminate\Support\Facades\Mail;

class AdmissionNotificationService
{
    public function notifyApplicantStatusChanged(Applicant $applicant, string $newStatus): void
    {
        $user = $applicant->user;
        if (!$user) return;

        $titles = [
            'under_review' => 'Your Application is Under Review',
            'shortlisted'  => 'Congratulations! You are Shortlisted',
            'selected'     => 'Congratulations! You have been Selected',
            'rejected'     => 'Update on Your Application',
            'withdrawn'    => 'Application Withdrawn',
        ];

        $title = $titles[$newStatus] ?? 'Application Status Update';
        $message = $this->buildStatusMessage($applicant, $newStatus);

        // DB Notification
        Notification::create([
            'user_id'    => $user->id,
            'title'      => $title,
            'message'    => $message,
            'type'       => in_array($newStatus, ['shortlisted', 'selected']) ? 'success' : (in_array($newStatus, ['rejected']) ? 'error' : 'info'),
            'action_url' => '/applicant/status',
            'is_read'    => false,
        ]);

        // Email
        try {
            Mail::send(
                'emails.admission.status-changed',
                compact('applicant', 'newStatus', 'title', 'message'),
                function ($mail) use ($user, $title) {
                    $mail->to($user->email, $user->name)->subject($title);
                }
            );
        } catch (\Exception $e) {
            \Log::warning("Admission email failed for applicant {$applicant->id}: " . $e->getMessage());
        }
    }

    public function notifyOfferIssued(OfferLetter $offerLetter): void
    {
        $applicant = $offerLetter->applicant;
        $user = $applicant?->user;
        if (!$user) return;

        $title = 'Offer Letter Issued — ' . ($offerLetter->program->name ?? '');

        Notification::create([
            'user_id'    => $user->id,
            'title'      => $title,
            'message'    => "Your offer letter #{$offerLetter->offer_number} has been issued. Please respond by {$offerLetter->acceptance_deadline?->format('d M Y')}.",
            'type'       => 'success',
            'action_url' => '/applicant/offer-letters',
            'is_read'    => false,
        ]);

        try {
            Mail::send(
                'emails.admission.offer-issued',
                compact('offerLetter', 'applicant'),
                function ($mail) use ($user, $title) {
                    $mail->to($user->email, $user->name)->subject($title);
                }
            );
        } catch (\Exception $e) {
            \Log::warning("Offer letter email failed: " . $e->getMessage());
        }
    }

    public function notifyPaymentVerified(AdmissionPayment $payment): void
    {
        $applicant = $payment->applicant;
        $user = $applicant?->user;
        if (!$user) return;

        $installmentName = $payment->installment?->name ?? 'Fee Payment';
        $title = "Payment Confirmed — {$installmentName}";

        Notification::create([
            'user_id'    => $user->id,
            'title'      => $title,
            'message'    => "Your payment of ₹" . number_format($payment->amount_paid, 2) . " for {$installmentName} has been verified. Ref: {$payment->transaction_reference}",
            'type'       => 'success',
            'action_url' => '/applicant/fees',
            'is_read'    => false,
        ]);

        try {
            Mail::send(
                'emails.admission.payment-verified',
                compact('payment', 'applicant', 'installmentName'),
                function ($mail) use ($user, $title) {
                    $mail->to($user->email, $user->name)->subject($title);
                }
            );
        } catch (\Exception $e) {
            \Log::warning("Payment verified email failed: " . $e->getMessage());
        }
    }

    public function notifyPaymentRejected(AdmissionPayment $payment, string $reason): void
    {
        $applicant = $payment->applicant;
        $user = $applicant?->user;
        if (!$user) return;

        $installmentName = $payment->installment?->name ?? 'Fee Payment';
        $title = "Payment Not Verified — {$installmentName}";

        Notification::create([
            'user_id'    => $user->id,
            'title'      => $title,
            'message'    => "Your payment of ₹" . number_format($payment->amount_paid, 2) . " could not be verified. Reason: {$reason}. Please re-upload proof.",
            'type'       => 'error',
            'action_url' => '/applicant/fees',
            'is_read'    => false,
        ]);

        try {
            Mail::send(
                'emails.admission.payment-rejected',
                compact('payment', 'applicant', 'installmentName', 'reason'),
                function ($mail) use ($user, $title) {
                    $mail->to($user->email, $user->name)->subject($title);
                }
            );
        } catch (\Exception $e) {
            \Log::warning("Payment rejected email failed: " . $e->getMessage());
        }
    }

    private function buildStatusMessage(Applicant $applicant, string $status): string
    {
        $program = $applicant->program?->name ?? 'your program';
        return match($status) {
            'under_review' => "Your application ({$applicant->application_number}) for {$program} is now under review by our admission committee.",
            'shortlisted'  => "Congratulations! Your application ({$applicant->application_number}) for {$program} has been shortlisted. You will receive further details about the selection process shortly.",
            'selected'     => "Congratulations! You have been selected for {$program}. Please log in to view your offer letter and next steps.",
            'rejected'     => "We regret to inform you that your application ({$applicant->application_number}) for {$program} has not been successful at this time.",
            'withdrawn'    => "Your application ({$applicant->application_number}) for {$program} has been withdrawn as requested.",
            default        => "Your application status has been updated to: " . ucfirst(str_replace('_', ' ', $status)),
        };
    }
}
