<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PaymentVerified extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public int $emailLogId = 0;

    public function __construct(public array $data = []) {}

    public function envelope(): Envelope
    {
        $amount = $this->data['payment']->amount_paid ?? 0;
        return new Envelope(subject: 'Payment Confirmed - Rs. ' . number_format((float) $amount, 2));
    }

    public function content(): Content
    {
        return new Content(view: 'emails.admission.payment-verified', with: $this->data);
    }
}
