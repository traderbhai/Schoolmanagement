<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class StudentDocumentRequestUpdated extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public int $emailLogId = 0;

    public function __construct(public array $data = []) {}

    public function envelope(): Envelope
    {
        $documentRequest = $this->data['documentRequest'] ?? null;
        $status = $documentRequest?->status ?? 'updated';
        $label = $documentRequest ? \App\Models\DocumentRequest::typeLabel($documentRequest->document_type) : 'Document';

        return new Envelope(subject: "{$label} request {$status}");
    }

    public function content(): Content
    {
        return new Content(view: 'emails.student.document-request-updated', with: $this->data);
    }
}
