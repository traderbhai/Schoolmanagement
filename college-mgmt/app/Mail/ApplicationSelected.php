<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ApplicationSelected extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public int $emailLogId = 0;

    public function __construct(public array $data = []) {}

    public function envelope(): Envelope
    {
        $program = $this->data['applicant']->program->name ?? 'program to be confirmed';
        return new Envelope(subject: "Selected - Admission Offer for {$program}");
    }

    public function content(): Content
    {
        return new Content(view: 'emails.admission.selected', with: $this->data);
    }
}
