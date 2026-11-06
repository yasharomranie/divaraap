<?php

declare(strict_types=1);

namespace Modules\Notification\Services;

use Illuminate\Support\Facades\Mail;
use Modules\Notification\Mail\ActivityNotice;
use Modules\Notification\Models\EmailPreference;

class NotificationEmailSender
{
    public static function ready(): bool
    {
        $mailer = config('mail.mailers.'.config('mail.default'));
        $base = config('app.url');

        return is_array($mailer) && ($mailer['transport'] ?? null) === 'smtp'
            && filled($mailer['host'] ?? $mailer['url'] ?? null)
            && filter_var(config('mail.from.address'), FILTER_VALIDATE_EMAIL) !== false
            && filter_var($base, FILTER_VALIDATE_URL) !== false
            && (parse_url($base, PHP_URL_SCHEME) === 'https' || app()->environment(['local', 'testing']));
    }

    public function send(EmailPreference $preference): bool
    {
        $mailer = config('mail.mailers.'.config('mail.default'));
        config(['mail.mailers.notification_email' => array_replace($mailer, ['timeout' => 10])]);
        Mail::purge('notification_email');

        return Mail::mailer('notification_email')->to($preference->recipient)->send((new ActivityNotice($preference->unsubscribe_token))->locale($preference->locale)) !== null;
    }
}
