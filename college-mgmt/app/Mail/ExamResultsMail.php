<?php
namespace App\Mail;

use App\Models\Student;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ExamResultsMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Student $student,
        public string $semesterName,
        public float $sgpa,
        public string $overallResult
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Your Exam Results are Available — ' . $this->semesterName);
    }

    public function content(): Content
    {
        return new Content(view: 'emails.exam-results');
    }
}
