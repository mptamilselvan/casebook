<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\URL;

class VerifyEmail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public User $user) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Verify your email for Case Book',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.verify-email',
            with: [
                'url' => $this->verificationUrl(),
            ],
        );
    }

    public function verificationUrl(): string
    {
        return URL::temporarySignedRoute(
            'verification.verify',
            now()->addDay(),
            [
                'id' => $this->user->getKey(),
                'hash' => sha1($this->user->getEmailForVerification()),
            ],
        );
    }
}
