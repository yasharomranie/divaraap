@extends('site::layouts.app')
@section('title', __('admin::security.challenge'))
@section('content')
<section class="shell shell--narrow"><div class="card card__body stack staff-security">
    <h1 class="text-title">{{ __('admin::security.challenge') }}</h1>
    <p class="text-meta">{{ __('admin::security.'.($needsPassword ? 'sensitive_help' : 'challenge_help')) }}</p>
    <form method="POST" action="{{ route('staff-security.verify') }}" class="stack">
        @csrf
        @if($needsPassword)
            <label for="staff-password">{{ __('admin::security.current_password') }}</label><input id="staff-password" class="input" type="password" name="current_password" autocomplete="current-password" maxlength="1024" required>
        @endif
        @error('current_password')<p class="field__error" role="alert">{{ $message }}</p>@enderror
        <label for="staff-code">{{ __('admin::security.code') }}</label><input id="staff-code" class="input" name="code" dir="ltr" autocomplete="one-time-code" autocapitalize="none" maxlength="80" required>
        @error('code')<p class="field__error" role="alert">{{ $message }}</p>@enderror
        <button class="button button--primary" type="submit">{{ __('admin::security.confirm') }}</button>
    </form>
    <form method="POST" action="{{ route('logout') }}">@csrf<button type="submit" class="button button--secondary">{{ __('admin::security.logout') }}</button></form>
</div></section>
@endsection
