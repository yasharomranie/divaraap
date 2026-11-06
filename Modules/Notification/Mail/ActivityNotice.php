<?php

declare(strict_types=1);

namespace Modules\Notification\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailables\Headers;

final class ActivityNotice extends Mailable
{
    public function __construct(private readonly string $unsubscribeToken) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: __('notification::email.subject'));
    }

    public function content(): Content
    {
        return new Content(view: 'notification::email.notice', text: 'notification::email.notice-text', with: ['inboxUrl' => rtrim(config('app.url'), '/').'/panel/notifications', 'unsubscribeUrl' => $this->unsubscribeUrl()]);
    }

    public function headers(): Headers
    {
        return new Headers(text: ['List-Unsubscribe' => '<'.$this->unsubscribeUrl().'>']);
    }

    private function unsubscribeUrl(): string
    {
        return rtrim(config('app.url'), '/').'/notification-email/unsubscribe/'.$this->unsubscribeToken;
    }
}
