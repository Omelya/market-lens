<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class EmailChangeVerification extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public User $user,
        public string $token
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Підтвердження зміни електронної пошти',
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.email-change-verification',
            with: [
                'name' => $this->user->name,
                'verificationUrl' => url(route('api.users.verify-email-change', ['token' => $this->token])),
                'expiresAt' => now()->addDay()->format('Y-m-d H:i:s'),
            ],
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
