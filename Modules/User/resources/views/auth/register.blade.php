@extends('user::layouts.auth')

@section('title', __('site::messages.register'))
@section('auth_title', __('user::messages.create_account'))
@section('auth_lead', __('user::messages.register_lead'))

@section('auth_body')
<form method="POST" action="{{ route('register') }}" class="stack">
    @csrf

    <div class="field">
        <label class="field__label" for="first_name">{{ __('user::messages.first_name') }}</label>
        <input id="first_name" type="text" dir="auto" name="first_name" value="{{ old('first_name') }}" class="input" required maxlength="120" autocomplete="given-name"
               aria-invalid="{{ $errors->has('first_name') ? 'true' : 'false' }}">
        @error('first_name')<p class="field__error">{{ $message }}</p>@enderror
    </div>

    <div class="field">
        <label class="field__label" for="last_name">{{ __('user::messages.last_name') }}</label>
        <input id="last_name" type="text" dir="auto" name="last_name" value="{{ old('last_name') }}" class="input" required maxlength="120" autocomplete="family-name"
               aria-invalid="{{ $errors->has('last_name') ? 'true' : 'false' }}">
        @error('last_name')<p class="field__error">{{ $message }}</p>@enderror
    </div>
    <div class="field">
        <label class="field__label" for="email">{{ __('user::messages.email') }}</label>
        <input id="email" type="email" name="email" value="{{ old('email') }}" class="input" required autocomplete="username"
               aria-invalid="{{ $errors->has('email') ? 'true' : 'false' }}">
        @error('email')<p class="field__error">{{ $message }}</p>@enderror
    </div>

    <div class="field">
        <label class="field__label" for="phone">{{ __('user::messages.phone') }}</label>
        <input id="phone" type="tel" dir="ltr" name="phone" value="{{ old('phone') }}" class="input" autocomplete="tel">
        @error('phone')<p class="field__error">{{ $message }}</p>@enderror
    </div>

    <div class="field">
        <label class="field__label" for="password">{{ __('user::messages.password') }}</label>
        <input id="password" type="password" name="password" class="input" required autocomplete="new-password"
               aria-invalid="{{ $errors->has('password') ? 'true' : 'false' }}">
        @error('password')<p class="field__error">{{ $message }}</p>@enderror
    </div>

    <div class="field">
        <label class="field__label" for="password_confirmation">{{ __('user::messages.confirm_password') }}</label>
        <input id="password_confirmation" type="password" name="password_confirmation" class="input" required autocomplete="new-password">
    </div>

    <div class="field">
        <a class="text-link" href="{{ route('pages.show', ['slug' => 'terms']) }}">{{ __('user::messages.read_terms') }}</a>
        <label for="terms" class="row">
            <input id="terms" type="checkbox" name="terms" value="1" required @checked(old('terms'))>
            <span>{{ __('user::messages.accept_terms') }}</span>
        </label>
        @error('terms')<p class="field__error">{{ $message }}</p>@enderror
    </div>

    <button type="submit" class="button button--primary button--block button--large">{{ __('site::messages.register') }}</button>

    @include('user::auth.partials.social-buttons')
</form>
@endsection

@section('auth_footer')
{{ __('user::messages.have_account') }} <a href="{{ route('login') }}" class="text-link">{{ __('site::messages.login') }}</a>
@endsection
