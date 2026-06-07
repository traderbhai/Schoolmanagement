<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class FollowUpReminder extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public int $emailLogId = 0;

    public function __construct(public array $data = []) {}

    public function envelope(): Envelope
    {
        $name = $this->data['applicant']->user?->name ?? ($this->data['applicant']->personal_data['name'] ?? 'Applicant');
        return new Envelope(subject: "Follow-up Due Today: {$name}");
    }

    public function content(): Content
    {
        return new Content(view: 'emails.admission-team.followup-reminder', with: $this->data);
    }
}
