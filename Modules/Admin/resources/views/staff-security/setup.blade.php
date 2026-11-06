@extends('site::layouts.app')
@section('title', __('admin::security.setup'))
@section('content')
<section class="shell shell--narrow"><div class="card card__body stack staff-security">
    <h1 class="text-title">{{ __('admin::security.setup') }}</h1>
    <p class="text-meta">{{ __('admin::security.setup_help') }}</p>
    @if(! $secret)
        <form method="POST" action="{{ route('staff-security.begin') }}" class="stack">
            @csrf
            <label for="staff-password">{{ __('admin::security.current_password') }}</label><input id="staff-password" type="password" class="input" name="current_password" autocomplete="current-password" required maxlength="1024">
            @error('current_password')<p class="field__error" role="alert">{{ $message }}</p>@enderror
            <button class="button button--primary" type="submit">{{ __('admin::security.begin') }}</button>
        </form>
    @else
        <div role="img" aria-label="{{ __('admin::security.qr') }}" class="staff-security__qr">{!! $qr !!}</div>
        <p>{{ __('admin::security.secret') }}: <bdi dir="ltr">{{ $secret }}</bdi></p>
        <form method="POST" action="{{ route('staff-security.enroll') }}" class="stack">
            @csrf
            <label for="staff-code">{{ __('admin::security.code') }}</label><input id="staff-code" class="input" name="code" inputmode="numeric" autocomplete="one-time-code" dir="ltr" maxlength="6" required>
            @error('code')<p class="field__error" role="alert">{{ $message }}</p>@enderror
            <button class="button button--primary" type="submit">{{ __('admin::security.confirm') }}</button>
        </form>
    @endif
    <form method="POST" action="{{ route('logout') }}">@csrf<button type="submit" class="button button--secondary">{{ __('admin::security.logout') }}</button></form>
</div></section>
@endsection
