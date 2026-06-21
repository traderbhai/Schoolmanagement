<?php

namespace App\Mail;

use App\Models\FeePayment;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class FeeReceiptMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public FeePayment $payment) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Fee Payment Confirmation - Receipt #' . ($this->payment->receipt_number ?? 'pending'));
    }

    public function content(): Content
    {
        return new Content(view: 'emails.fee-receipt');
    }
}
