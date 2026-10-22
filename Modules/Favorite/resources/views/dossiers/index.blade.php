@extends('panel::layouts.panel', ['panelSection' => 'dossiers'])
@section('title', __('favorite::dossier.title'))
@section('panel_content')
<div class="wanted dossier stack stack--loose">
    <h1 class="title-page">{{ __('favorite::dossier.title') }}</h1>
    <p>{{ __('favorite::dossier.hint') }}</p>
    <a class="button button--primary" href="{{ route('comparison.index') }}">{{ __('favorite::dossier.selection_edit') }}</a>
    @foreach($errors->all() as $error)<p class="alert alert--critical" role="alert">{{ $error }}</p>@endforeach
    @forelse($records as $record)
    <article class="card stack" data-dossier="{{ $record->id }}">
        <h2 class="title-card" dir="auto">{{ $record->label }}</h2>
        <p><span class="badge">{{ $record->stateLabel() }}</span></p>
        <p>{{ __('favorite::dossier.expires') }} <time datetime="{{ $record->expires_at->toIso8601String() }}">{{ \App\Support\PersianLocale::date($record->expires_at) }}</time></p>
        @if($record->sharing && $record->expires_at->isFuture())
        <div class="field"><label class="field__label" for="dossier-url-{{ $record->id }}">{{ __('favorite::dossier.link') }}</label><input class="input dossier-link" id="dossier-url-{{ $record->id }}" value="{{ $record->shareUrl() }}" readonly dir="ltr"><p class="field__hint">{{ __('favorite::dossier.copy_hint') }}</p></div>
        <a class="button button--secondary" href="{{ $record->shareUrl() }}" rel="noreferrer">{{ __('favorite::dossier.open') }}</a>
        @endif
        <div class="wanted-actions">
        @foreach($record->sharing && $record->expires_at->isFuture() ? ['revoke', 'rotate', 'remove'] : ['rotate', 'remove'] as $action)
        <form method="post" action="{{ route('dossiers.change', $record) }}">@csrf<input type="hidden" name="action" value="{{ $action }}"><input type="hidden" name="revision" value="{{ $record->revision }}"><button class="button button--ghost" type="submit">{{ __('favorite::dossier.'.$action) }}</button></form>
        @endforeach
        </div>
    </article>
    @empty<p>{{ __('favorite::dossier.empty') }}</p>@endforelse
    {{ $records->links() }}
    <p class="field__hint">{{ __('favorite::dossier.lifecycle') }}</p>
</div>
@endsection
