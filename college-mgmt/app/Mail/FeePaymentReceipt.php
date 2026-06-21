<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class FeePaymentReceipt extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public int $emailLogId = 0;

    public function __construct(public array $data = []) {}

    public function envelope(): Envelope
    {
        $amount = number_format((float) ($this->data['feePayment']->amount ?? 0), 2);

        return new Envelope(subject: "Fee Payment Receipt - Rs. {$amount}");
    }

    public function content(): Content
    {
        return new Content(view: 'emails.student.fee-receipt', with: $this->data);
    }
}
