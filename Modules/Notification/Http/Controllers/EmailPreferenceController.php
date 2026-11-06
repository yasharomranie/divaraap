<?php

declare(strict_types=1);

namespace Modules\Notification\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Modules\Notification\Http\Requests\EmailPreferenceRequest;
use Modules\Notification\Models\EmailPreference;
use Modules\Site\App\Settings\EmailNotificationSettings;

final class EmailPreferenceController extends Controller
{
    public function edit(Request $request): Response
    {
        abort_unless((string) $request->user()->status === 'active', 403);

        return response()->view('notification::email.preferences', ['preference' => EmailPreference::forUser($request->user()), 'eligible' => EmailPreference::eligibleUser($request->user()), 'siteEnabled' => app(EmailNotificationSettings::class)->enabled, 'types' => EmailPreference::TYPES])->header('Cache-Control', 'private, no-store')->header('X-Robots-Tag', 'noindex, nofollow');
    }

    public function update(EmailPreferenceRequest $request): RedirectResponse
    {
        EmailPreference::updateFor($request->user(), $request->boolean('enabled'), $request->validated('types') ?? [], (int) $request->validated('version'), $request->boolean('consent'), app()->getLocale());

        return redirect()->route('notification-email.edit')->with('success', __('notification::email.saved'));
    }

    public function unsubscribe(string $token): Response
    {
        return response()->view('notification::email.unsubscribe', compact('token'))->header('Cache-Control', 'private, no-store')->header('Referrer-Policy', 'no-referrer')->header('X-Robots-Tag', 'noindex, nofollow');
    }

    public function stop(string $token): Response
    {
        EmailPreference::unsubscribe($token);

        return response()->view('notification::email.unsubscribed')->header('Cache-Control', 'private, no-store')->header('Referrer-Policy', 'no-referrer')->header('X-Robots-Tag', 'noindex, nofollow');
    }
}
