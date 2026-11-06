<!doctype html>
<html lang="{{ app()->getLocale() }}" dir="{{ app()->getLocale() === 'fa' ? 'rtl' : 'ltr' }}">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"></head>
<body style="font-family:Tahoma,Arial,sans-serif;line-height:1.8;background:#fff;color:#222;padding:24px">
    <h1 style="font-size:20px">{{ __('notification::email.subject') }}</h1>
    <p>{{ __('notification::email.mail_body') }}</p>
    <p><a href="{{ $inboxUrl }}">{{ __('notification::email.open') }}</a></p>
    <p><a href="{{ $unsubscribeUrl }}">{{ __('notification::email.unsubscribe') }}</a></p>
</body>
</html>
