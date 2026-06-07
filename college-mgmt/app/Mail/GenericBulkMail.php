<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class GenericBulkMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public int $emailLogId = 0;

    public string $emailSubject;
    public string $emailBody;
    public ?string $recipientName;

    public function __construct(array $data = [])
    {
        $this->emailSubject   = $data['subject']        ?? 'Notice from College';
        $this->emailBody      = $data['body']           ?? '';
        $this->recipientName  = $data['recipient_name'] ?? null;
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->emailSubject);
    }

    public function content(): Content
    {
        return new Content(view: 'emails.generic-bulk', with: [
            'subject'       => $this->emailSubject,
            'body'          => $this->emailBody,
            'recipientName' => $this->recipientName,
        ]);
    }
}
