<?php
namespace App\Mail;

use App\Models\Notice;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class NoticeMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Notice $notice) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->notice->title);
    }

    public function content(): Content
    {
        return new Content(view: 'emails.notice');
    }
}
