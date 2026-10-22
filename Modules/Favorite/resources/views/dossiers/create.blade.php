@extends('panel::layouts.panel', ['panelSection' => 'dossiers'])
@section('title', __('favorite::dossier.create'))
@section('panel_content')
<div class="wanted comparison-page dossier stack stack--loose">
    <h1 class="title-page">{{ __('favorite::dossier.create') }}</h1>
    <p>{{ __('favorite::dossier.hint') }}</p>
    @foreach($errors->get('comparison') as $error)<p class="alert alert--critical" role="alert">{{ $error }}</p>@endforeach
    <ul>@foreach($listings as $listing)<li dir="auto">{{ $listing->title }}</li>@endforeach</ul>
    @if($listings->count() < 2)<p class="alert">{{ __('favorite::dossier.selection') }}</p>
    @else
    <form class="card stack" method="post" action="{{ route('dossiers.store') }}">
        @csrf<input type="hidden" name="request_key" value="{{ old('request_key', $requestKey) }}">
        <div class="field"><label class="field__label" for="dossier-label">{{ __('favorite::dossier.label') }}</label><input class="input" id="dossier-label" name="label" maxlength="100" value="{{ old('label') }}" required dir="auto" aria-describedby="dossier-label-hint{{ $errors->has('label') ? ' dossier-label-error' : '' }}" @if($errors->has('label')) aria-invalid="true" autofocus @endif><p class="field__hint" id="dossier-label-hint">{{ __('favorite::dossier.private_label') }}</p>@error('label')<p id="dossier-label-error" class="field__error">{{ $message }}</p>@enderror</div>
        <label class="dossier-consent" for="dossier-consent"><input type="checkbox" id="dossier-consent" name="consent" value="1" @checked(old('consent')) required @if($errors->has('consent')) aria-invalid="true" aria-describedby="dossier-consent-error" @endif><span>{{ __('favorite::dossier.consent') }}</span></label>
        @error('consent')<p class="field__error" id="dossier-consent-error">{{ $message }}</p>@enderror
        <button class="button button--primary" type="submit">{{ __('favorite::dossier.save') }}</button>
    </form>
    @endif
    <a class="button button--ghost" href="{{ route('comparison.index') }}">{{ __('favorite::dossier.selection_edit') }}</a>
</div>
@endsection
