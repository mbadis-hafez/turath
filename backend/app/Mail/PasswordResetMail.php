<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Written in the language the reset was requested in, and links to the SPA's
 * reset page in that same language.
 */
class PasswordResetMail extends Mailable
{
    use Queueable, SerializesModels;

    public readonly string $url;

    public function __construct(
        public readonly User $user,
        #[\SensitiveParameter] string $token,
    ) {
        $locale = app()->getLocale();
        $this->locale($locale);

        $this->url = rtrim((string) config('app.frontend_url'), '/')."/{$locale}/reset-password?"
            .http_build_query(['token' => $token, 'email' => $user->email]);
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('passwords.mail.subject', ['app' => config('app.name')]),
        );
    }

    public function content(): Content
    {
        $rtl = $this->locale === 'ar';

        return new Content(
            view: 'emails.password-reset',
            text: 'emails.password-reset-text',
            with: [
                'dir' => $rtl ? 'rtl' : 'ltr',
                'align' => $rtl ? 'right' : 'left',
                'minutes' => (int) config('auth.passwords.users.expire'),
            ],
        );
    }
}
