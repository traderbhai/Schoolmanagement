<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ExamResultPublished extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public int $emailLogId = 0;

    public function __construct(public array $data = []) {}

    public function envelope(): Envelope
    {
        $examName = $this->data['exam']->name ?? 'Exam';

        return new Envelope(subject: "Results Published - {$examName}");
    }

    public function content(): Content
    {
        return new Content(view: 'emails.student.result-published', with: $this->data);
    }
}
