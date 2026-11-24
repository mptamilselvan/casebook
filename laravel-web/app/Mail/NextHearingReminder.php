<?php

namespace App\Mail;

use App\Models\CourtCase;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class NextHearingReminder extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public CourtCase $case,
        public string $whenLabel,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Hearing in '.$this->whenLabel.': '.$this->case->title,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.next-hearing',
        );
    }
}
