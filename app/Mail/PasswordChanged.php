<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PasswordChanged extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public User $user)
    {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Пароль змінено',
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.password-changed',
            with: [
                'name' => $this->user->name,
                'time' => now()->format('Y-m-d H:i:s'),
                'ip' => request()->ip(),
                'userAgent' => request()->userAgent(),
            ],
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
