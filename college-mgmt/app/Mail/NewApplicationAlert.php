<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class NewApplicationAlert extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public int $emailLogId = 0;

    public function __construct(public array $data = []) {}

    public function envelope(): Envelope
    {
        $applicant = $this->data['applicant'];
        $name      = $applicant->user?->name ?? 'Applicant';
        $number    = $applicant->application_number ?? 'N/A';
        return new Envelope(subject: "New Application: {$name} — {$number}");
    }

    public function content(): Content
    {
        return new Content(view: 'emails.admission-team.new-application-alert', with: $this->data);
    }
}
