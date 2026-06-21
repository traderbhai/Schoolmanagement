<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PaymentRejected extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public int $emailLogId = 0;

    public function __construct(public array $data = []) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Payment Verification Failed - Action Required');
    }

    public function content(): Content
    {
        return new Content(view: 'emails.admission.payment-rejected', with: $this->data);
    }
}
