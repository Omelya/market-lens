<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class SecurityAlert extends Mailable
{
    use Queueable, SerializesModels;

    protected User $user;
    protected array $alertInfo;
    protected array $details;

    public function __construct(User $user, array $alertInfo, array $details)
    {
        $this->user = $user;
        $this->alertInfo = $alertInfo;
        $this->details = $details;
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "[Сповіщення безпеки] " . $this->alertInfo['subject'],
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.security-alert',
            with: [
                'user' => $this->user,
                'alertInfo' => $this->alertInfo,
                'details' => $this->details,
                'ip' => $this->details['ip_address'] ?? request()->ip(),
                'userAgent' => $this->details['user_agent'] ?? request()->userAgent(),
                'time' => $this->details['time'] ?? now()->format('Y-m-d H:i:s'),
            ],
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
