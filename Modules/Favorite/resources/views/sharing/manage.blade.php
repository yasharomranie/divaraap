@extends('site::layouts.app')
@section('title', __('favorite::sharing.title'))
@section('content')
<div class="shell wanted dossier page stack stack--loose">
    <h1 class="title-page">{{ __('favorite::sharing.title') }}</h1>
    <h2 class="title-section" dir="auto">{{ $record->name }}</h2><p class="field__hint">{{ __('favorite::sharing.private_name') }}</p>
    <p>{{ __('favorite::sharing.hint') }}</p>
    @foreach($errors->all() as $error)<p class="alert alert--critical" role="alert">{{ $error }}</p>@endforeach
    <p><span class="badge">{{ $record->sharingState() }}</span></p>
    @if($record->share_expires_at)<p>{{ __('favorite::sharing.expires') }} <time datetime="{{ $record->share_expires_at->toIso8601String() }}">{{ \App\Support\PersianLocale::date($record->share_expires_at) }}</time></p>@endif
    @if($record->share_token)
        @if($record->share_enabled && $record->share_expires_at->isFuture())
        <div class="field"><label class="field__label" for="shortlist-url">{{ __('favorite::sharing.link') }}</label><input class="input dossier-link" id="shortlist-url" readonly dir="ltr" value="{{ $record->shortlistUrl() }}"><p class="field__hint">{{ __('favorite::sharing.copy_hint') }}</p></div>
        <a class="button button--secondary" href="{{ $record->shortlistUrl() }}" rel="noreferrer">{{ __('favorite::sharing.open') }}</a>
        @endif
        <p class="field__hint">{{ __('favorite::sharing.snapshot_count', ['count' => \App\Support\PersianLocale::number(count($record->share_ids ?? []))]) }}</p>
        <div class="wanted-actions">
        @foreach($record->share_enabled && $record->share_expires_at->isFuture() ? ['revoke', 'rotate', 'clear'] : ['rotate', 'clear'] as $action)
        <form method="post" action="{{ route('shortlists.change', $record) }}">@csrf<input type="hidden" name="share_revision" value="{{ $record->share_revision }}"><input type="hidden" name="action" value="{{ $action }}"><button class="button button--ghost" type="submit">{{ __('favorite::sharing.'.$action) }}</button></form>
        @endforeach
        </div>
    @endif
    <section class="card stack" aria-labelledby="sharing-preview-title">
        <h2 id="sharing-preview-title">{{ __('favorite::sharing.preview') }}</h2>
        @if($preview['valid'])
            <ul>@foreach($preview['listings'] as $listing)<li dir="auto">{{ $listing->title }}</li>@endforeach</ul>
            <form method="post" class="stack" action="{{ route('shortlists.change', $record) }}">
                @csrf<input type="hidden" name="share_revision" value="{{ $record->share_revision }}"><input type="hidden" name="signature" value="{{ $preview['signature'] }}"><input type="hidden" name="action" value="{{ $record->share_token ? 'refresh' : 'create' }}">
                <label class="dossier-consent" for="shortlist-consent"><input type="checkbox" id="shortlist-consent" name="consent" value="1" required @if($errors->has('consent')) aria-invalid="true" aria-describedby="shortlist-consent-error" @endif><span>{{ __('favorite::sharing.consent') }}</span></label>
                @error('consent')<p class="field__error" id="shortlist-consent-error">{{ $message }}</p>@enderror
                <button class="button button--primary" type="submit">{{ __('favorite::sharing.'.($record->share_token ? 'refresh' : 'create')) }}</button>
            </form>
        @else<p class="alert">{{ __('favorite::sharing.empty_selection') }}</p>@endif
    </section>
    <p>{{ __('favorite::sharing.membership') }}</p>
    <div class="wanted-actions"><a class="button button--secondary" href="{{ route('favorites.index', ['collection' => $record->id]) }}">{{ __('favorite::sharing.edit_items') }}</a><a class="button button--ghost" href="{{ route('collections.index') }}">{{ __('favorite::sharing.back') }}</a></div>
</div>
@endsection
