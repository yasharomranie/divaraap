@extends('panel::layouts.panel', ['panelSection' => 'notifications'])
@section('title', __('notification::email.preferences'))
@section('panel_content')
<section class="card card__body stack notification-email">
    <h1 class="text-title">{{ __('notification::email.preferences') }}</h1>
    <p class="text-meta">{{ __('notification::email.help') }}</p>
    <p class="text-meta">{{ __('notification::email.limits_help') }}</p>
    @if(! $siteEnabled)<p role="status">{{ __('notification::email.site_off') }}</p>@endif
    @if(! $eligible)<p role="status">{{ __('notification::email.verify') }}</p>@endif
    <form method="POST" action="{{ route('notification-email.update') }}" class="stack">
        @csrf
        <input type="hidden" name="version" value="{{ old('version', $preference?->version ?? 0) }}">
        <input type="hidden" name="enabled" value="0">
        <label class="checkbox"><input type="checkbox" name="enabled" value="1" @checked(old('enabled', $preference?->matchesUser(auth()->user()) ?? false))><span>{{ __('notification::email.enabled') }}</span></label>
        <fieldset class="stack">
            <legend>{{ __('notification::email.types') }}</legend>
            @foreach($types as $type)
                <label class="checkbox"><input type="checkbox" name="types[]" value="{{ $type }}" @checked(in_array($type, (array) old('types', $preference?->types ?? []), true))><span>{{ __('notification::admin.'.$type) }}</span></label>
            @endforeach
        </fieldset>
        <label class="checkbox"><input type="checkbox" name="consent" value="1" @checked(old('consent'))><span>{{ __('notification::email.consent') }}</span></label>
        @foreach(['enabled', 'types', 'types.*', 'consent', 'version'] as $field)
            @error($field)<p class="field__error" role="alert">{{ $message }}</p>@enderror
        @endforeach
        <button class="button button--primary" type="submit">{{ __('notification::email.save') }}</button>
    </form>
    <a class="button button--secondary" href="{{ route('panel.notifications.index') }}">{{ __('notification::email.back') }}</a>
</section>
@endsection
