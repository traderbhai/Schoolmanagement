<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class EnrollmentConfirmed extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public int $emailLogId = 0;

    public function __construct(public array $data = []) {}

    public function envelope(): Envelope
    {
        $institute = config('app.institute_name', env('INSTITUTE_NAME', 'College'));
        return new Envelope(subject: "Welcome to {$institute}! Your Enrollment is Confirmed");
    }

    public function content(): Content
    {
        return new Content(view: 'emails.admission.enrollment-confirmed', with: $this->data);
    }
}
