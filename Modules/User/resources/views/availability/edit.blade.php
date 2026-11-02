@extends('panel::layouts.panel', ['panelSection' => 'availability'])
@section('title', __('user::availability.title'))
@section('panel_content')
<div class="availability-settings stack stack--loose">
    <header class="stack stack--tight"><h1 class="title-page">{{ __('user::availability.title') }}</h1><p class="text-muted">{{ __('user::availability.intro') }}</p></header>
    @if(session('success'))<p class="alert" role="status">{{ session('success') }}</p>@endif
    <p class="badge" role="status">{{ __('user::availability.'.($availability['active'] ? 'active' : ($availability['enabled'] ? 'expired' : 'inactive'))) }}</p>
    <form class="card stack" method="POST" action="{{ route('seller-availability.update') }}">@csrf @method('PUT')
        <input type="hidden" name="revision" value="{{ is_scalar(old('revision', $availability['revision'])) ? old('revision', $availability['revision']) : $availability['revision'] }}">
        @if($errors->any())<div class="alert alert--critical" role="alert" tabindex="-1" autofocus>@foreach($errors->all() as $message)<p>{{ $message }}</p>@endforeach</div>@endif
        <p class="field__hint">{{ __('user::availability.privacy') }}</p>
        <label class="field__label" for="availability-enabled">{{ __('user::availability.enabled') }}</label>
        <select class="select" name="enabled" id="availability-enabled"><option value="0" @selected(old('enabled', $availability['enabled']) == 0)>{{ __('user::availability.off') }}</option><option value="1" @selected(old('enabled', $availability['enabled']) == 1)>{{ __('user::availability.on') }}</option></select>
        <label class="field__label" for="availability-reason">{{ __('user::availability.reason') }}</label>
        <select class="select" name="reason" id="availability-reason">@foreach(\Modules\User\App\Models\Profile::availabilityReasons() as $reason)<option value="{{ $reason }}" @selected(old('reason', $availability['reason']) === $reason)>{{ __('user::availability.'.$reason) }}</option>@endforeach</select>
        @error('reason')<p class="field__error" role="alert">{{ $message }}</p>@enderror
        <label class="field__label" for="availability-until">{{ __('user::availability.until') }}</label>
        <input class="input" id="availability-until" name="until" type="datetime-local" dir="ltr" value="{{ is_string(old('until', $availability['until'])) ? old('until', $availability['until']) : '' }}" aria-describedby="availability-time-hint @error('until') availability-until-error @enderror" @error('until') aria-invalid="true" @enderror>
        <p class="field__hint" id="availability-time-hint">{{ __('user::availability.timezone_hint') }}</p>
        @error('until')<p class="field__error" id="availability-until-error" role="alert">{{ $message }}</p>@enderror
        <label class="availability-consent"><input type="checkbox" name="public_consent" value="1"><span>{{ __('user::availability.consent') }}</span></label>
        @error('public_consent')<p class="field__error" role="alert">{{ $message }}</p>@enderror
        <p class="field__hint">{{ __('user::availability.no_auto_reply') }}</p>
        <button class="button button--primary" type="submit">{{ __('user::availability.save') }}</button>
    </form>
    <a class="button button--ghost" href="{{ route('sellers.show', auth()->id()) }}">{{ __('user::availability.preview') }}</a>
</div>
@endsection
