@extends('panel::layouts.panel', ['panelSection' => 'rare-watches'])
@section('title', __('favorite::rare.title'))
@section('panel_content')
<div class="wanted rare-watches stack stack--loose"><h1 class="title-page">{{ __('favorite::rare.title') }}</h1><p>{{ __('favorite::rare.delivery_short') }}</p><details><summary class="button button--ghost">{{ __('favorite::rare.delivery_details') }}</summary><p>{{ __('favorite::rare.delivery') }}</p></details><div class="wanted-actions"><a class="button button--primary" href="{{ route('rare-watches.create') }}">{{ __('favorite::rare.create') }}</a><a class="button button--secondary" href="{{ route('panel.notifications.index') }}">{{ __('favorite::rare.notifications') }}</a><a class="button button--ghost" href="{{ route('rare-watches.index') }}">{{ __('favorite::rare.refresh') }}</a></div>
@foreach($errors->all() as $error)<p class="alert alert--critical" role="alert">{{ $error }}</p>@endforeach
<p class="text-muted">{{ __('favorite::rare.private') }}</p>
@forelse($watches as $watch)
    @php($expired = $watch->expires_at->lessThanOrEqualTo(now()))
    @php($enabled = $preferences['rare:'.$watch->id] ?? false)
    @php($available = isset($categories[$watch->filters['category']]) && (!isset($watch->filters['city']) || isset($cities[$watch->filters['city']])))
    <article class="card stack" data-rare-watch="{{ $watch->id }}">
        <h2 class="title-card" dir="auto">{{ $watch->filters['search'] }}</h2>
        <p><span class="badge">{{ __('favorite::rare.'.($expired ? 'expired' : ($enabled ? 'on' : 'off'))) }}</span></p>
        <div class="wanted-meta text-muted"><span>{{ $categories[$watch->filters['category']] ?? '—' }}</span><span>{{ $cities[$watch->filters['city'] ?? 0] ?? __('favorite::rare.any_city') }}</span><span>{{ __('favorite::rare.expires') }} <time datetime="{{ $watch->expires_at->toIso8601String() }}">{{ \App\Support\PersianLocale::date($watch->expires_at) }}</time></span></div>
        @if(isset($watch->filters['max_price']))<p>{{ __('favorite::rare.max_price') }}: <bdi>{{ \App\Support\PersianLocale::number($watch->filters['max_price']) }}</bdi></p>@endif
        @if(!$available)<p>{{ __('favorite::rare.unavailable') }}</p>@endif
        <div class="wanted-actions">
        @foreach($available ? [$expired ? 'renew' : ($enabled ? 'pause' : 'resume'), 'remove'] : ['remove'] as $action)
            <form method="post" action="{{ route('rare-watches.change', $watch) }}">@csrf<input type="hidden" name="action" value="{{ $action }}"><button class="button button--secondary" type="submit">{{ __('favorite::rare.'.$action) }}</button></form>
        @endforeach
        </div>
    </article>
@empty<p>{{ __('favorite::rare.empty') }}</p>@endforelse
{{ $watches->links() }}
<p class="field__hint">{{ __('favorite::rare.lifecycle') }}</p>
<a href="{{ route('favorites.alerts') }}">{{ __('favorite::rare.all_alerts') }}</a></div>
@endsection
