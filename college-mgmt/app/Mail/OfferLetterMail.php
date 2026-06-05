<?php

namespace App\Mail;

use App\Models\OfferLetter;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class OfferLetterMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public OfferLetter $offerLetter) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Congratulations! Your Offer Letter - ' . $this->offerLetter->offer_number
        );
    }

    public function content(): Content
    {
        return new Content(view: 'emails.offer-letter');
    }
}
