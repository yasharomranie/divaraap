@extends('user::layouts.auth')

@section('title', __('user::otp.'.$purpose.'_title'))
@section('auth_title', __('user::otp.'.$purpose.'_title'))
@section('auth_lead', __('user::otp.lead'))

@section('auth_body')
<div class="stack">
    @if($identity)
        <p role="status">{{ __('user::otp.bound_help') }}</p>
    @elseif($challenge)
        @php([$phoneBefore, $phoneAfter] = explode(':phone', __('user::otp.sent_to'), 2))
        <p role="status">{{ $phoneBefore }}<bdi dir="ltr">{{ $challenge->maskedPhone() }}</bdi>{{ $phoneAfter }}</p>
        <form method="POST" action="{{ $phoneUrl }}/verify" class="stack">
            @csrf
            <div class="field">
                <label for="otp-code" class="field__label">{{ __('user::otp.code') }}</label>
                <input id="otp-code" name="code" type="text" inputmode="numeric" autocomplete="one-time-code" dir="ltr" maxlength="6" class="input" required autofocus aria-describedby="otp-code-help" aria-invalid="{{ $errors->has('code') ? 'true' : 'false' }}">
                <p id="otp-code-help" class="text-muted">{{ __('user::otp.resend_help') }}</p>
                @error('code')<p class="field__error" role="alert">{{ $message }}</p>@enderror
            </div>
            <button class="button button--primary button--block" type="submit">{{ __('user::otp.confirm') }}</button>
        </form>
        <form method="POST" action="{{ $phoneUrl }}/resend">
            @csrf
            <button class="button button--secondary button--block" type="submit">{{ __('user::otp.resend') }}</button>
            @error('phone')<p class="field__error" role="alert">{{ $message }}</p>@enderror
        </form>
        <form method="POST" action="{{ $phoneUrl }}/reset">
            @csrf
            <button class="button button--ghost" type="submit">{{ __('user::otp.change_phone') }}</button>
        </form>
    @else
        @if($purpose === 'bind')<p class="text-muted">{{ __('user::otp.binding_help') }}</p>@endif
        <form method="POST" action="{{ $phoneUrl }}" class="stack">
            @csrf
            @if($purpose === 'register')
                @foreach(['first_name' => 'given-name', 'last_name' => 'family-name'] as $name => $autocomplete)
                    <div class="field">
                        <label for="{{ $name }}" class="field__label">{{ __('user::messages.'.$name) }}</label>
                        <input id="{{ $name }}" name="{{ $name }}" autocomplete="{{ $autocomplete }}" dir="auto" value="{{ old($name) }}" class="input" maxlength="120" required>
                        @error($name)<p class="field__error">{{ $message }}</p>@enderror
                    </div>
                @endforeach
            @endif
            <div class="field">
                <label for="otp-phone" class="field__label">{{ __('user::messages.phone') }}</label>
                <input id="otp-phone" name="phone" type="tel" inputmode="tel" autocomplete="tel" dir="ltr" value="{{ old('phone') }}" class="input" maxlength="60" placeholder="09123456789" required autofocus aria-invalid="{{ $errors->has('phone') ? 'true' : 'false' }}">
                @error('phone')<p class="field__error" role="alert">{{ $message }}</p>@enderror
            </div>
            @if($purpose === 'bind')
                <div class="field">
                    <label for="current_password" class="field__label">{{ __('user::messages.current_password') }}</label>
                    <input id="current_password" name="current_password" type="password" autocomplete="current-password" class="input" required>
                    @error('current_password')<p class="field__error">{{ $message }}</p>@enderror
                </div>
            @endif
            @if($purpose === 'register')
                <a class="text-link" href="{{ route('pages.show', ['slug' => 'terms']) }}">{{ __('user::messages.read_terms') }}</a>
                <label class="checkbox" for="terms"><input id="terms" name="terms" type="checkbox" value="1" required @checked(old('terms'))><span>{{ __('user::messages.accept_terms') }}</span></label>
                @error('terms')<p class="field__error">{{ $message }}</p>@enderror
            @endif
            <button class="button button--primary button--block" type="submit">{{ __('user::otp.send') }}</button>
        </form>
    @endif
</div>
@endsection

@section('auth_footer')
<a class="text-link" href="{{ $purpose === 'bind' ? route('panel.profile.edit') : route('login') }}">{{ __('user::otp.'.($purpose === 'bind' ? 'back_profile' : 'back_login')) }}</a>
@endsection
