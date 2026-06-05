<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class FeeDueReminder extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public int $emailLogId = 0;

    public function __construct(public array $data = []) {}

    public function envelope(): Envelope
    {
        $dueDate = isset($this->data['feeStructure']) && $this->data['feeStructure']->due_date
            ? \Carbon\Carbon::parse($this->data['feeStructure']->due_date)->format('d M Y')
            : 'Soon';
        return new Envelope(subject: "Fee Payment Reminder — Due {$dueDate}");
    }

    public function content(): Content
    {
        return new Content(view: 'emails.student.fee-due-reminder', with: $this->data);
    }
}
