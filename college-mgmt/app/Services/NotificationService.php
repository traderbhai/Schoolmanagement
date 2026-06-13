<?php

namespace App\Services;

use App\Models\EmailLog;
use App\Models\NotificationPreference;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class NotificationService
{
    // Student-facing email types that are preference-controlled
    private static array $preferenceMap = [
        'ApplicationReceived'    => 'email_application_updates',
        'ApplicationShortlisted' => 'email_application_updates',
        'ApplicationSelected'    => 'email_application_updates',
        'ApplicationRejected'    => 'email_application_updates',
        'SessionScheduled'       => 'email_application_updates',
        'DocumentRejected'       => 'email_application_updates',
        'PaymentVerified'        => 'email_payment_updates',
        'PaymentRejected'        => 'email_payment_updates',
        'EnrollmentConfirmed'    => 'email_application_updates',
        'ExamResultPublished'    => 'email_result_published',
        'FeePaymentReceipt'      => 'email_payment_updates',
        'FeeDueReminder'         => 'email_payment_updates',
        'LowAttendanceAlert'     => 'email_notices',
        'NoticePublished'        => 'email_notices',
        'StudentDocumentRequestUpdated' => 'email_notices',
    ];

    /**
     * Queue an email safely — never throws.
     */
    public static function send(string $mailableClass, $notifiable, array $data = []): void
    {
        try {
            $email = $notifiable->email ?? (is_string($notifiable) ? $notifiable : null);
            $name  = $notifiable->name  ?? null;

            if (! $email) {
                Log::warning('NotificationService::send — no email address', ['mailable' => $mailableClass]);
                return;
            }

            // Check notification preferences (only for preference-mapped mailables)
            $shortClass = class_basename($mailableClass);
            if (isset(static::$preferenceMap[$shortClass]) && $notifiable instanceof \App\Models\User) {
                $pref = NotificationPreference::firstOrCreate(
                    ['user_id' => $notifiable->id],
                    [
                        'email_application_updates' => true,
                        'email_payment_updates'     => true,
                        'email_result_published'    => true,
                        'email_notices'             => true,
                    ]
                );
                $prefKey = static::$preferenceMap[$shortClass];
                if (! $pref->$prefKey) {
                    return;
                }
            }

            // Log the email as queued
            $log = EmailLog::create([
                'mailable_class' => $mailableClass,
                'to_email'       => $email,
                'to_name'        => $name,
                'subject'        => '',   // placeholder; updated by mailable envelope
                'status'         => 'queued',
                'queued_at'      => now(),
            ]);

            $mailable = new $mailableClass($data);
            $mailable->emailLogId = $log->id;

            Mail::to($email)->queue($mailable);

        } catch (\Throwable $e) {
            Log::error('NotificationService::send failed', [
                'mailable' => $mailableClass,
                'error'    => $e->getMessage(),
            ]);
        }
    }

    /**
     * Send to multiple recipients.
     */
    public static function sendBulk(string $mailableClass, \Illuminate\Support\Collection $notifiables, array $data = []): void
    {
        foreach ($notifiables as $notifiable) {
            static::send($mailableClass, $notifiable, array_merge($data, ['recipient' => $notifiable]));
        }
    }
}
