<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class SessionScheduled extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public int $emailLogId = 0;

    public function __construct(public array $data = []) {}

    public function envelope(): Envelope
    {
        $session   = $this->data['session'] ?? null;
        $stepLabel = $session?->step?->type ? strtoupper($session->step->type) : 'Session';
        $date      = $session?->scheduled_date ? $session->scheduled_date->format('d M Y') : '';
        return new Envelope(subject: "Your {$stepLabel} is Scheduled — {$date}");
    }

    public function content(): Content
    {
        return new Content(view: 'emails.admission.session-scheduled', with: $this->data);
    }
}
