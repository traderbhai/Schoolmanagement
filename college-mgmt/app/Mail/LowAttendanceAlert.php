<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class LowAttendanceAlert extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public int $emailLogId = 0;

    public function __construct(public array $data = []) {}

    public function envelope(): Envelope
    {
        $subjectName = $this->data['subject']->name ?? 'Subject';
        $percentage  = $this->data['attendance_percentage'] ?? 0;
        return new Envelope(subject: "Attendance Alert — {$subjectName}: {$percentage}%");
    }

    public function content(): Content
    {
        return new Content(view: 'emails.student.low-attendance-alert', with: $this->data);
    }
}
