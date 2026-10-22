@extends('site::layouts.app')
@section('title', __('favorite::alerts.title'))
@section('content')
<div class="shell page buyer-alerts"><div class="stack stack--loose">
    <h1 class="title-page">{{ __('favorite::alerts.title') }}</h1>
    <a class="button button--secondary" href="{{ route('rare-watches.index') }}">{{ __('favorite::rare.title') }}</a>
    <p class="muted">{{ __('favorite::alerts.hint') }}</p>
    <p class="muted">{{ __('favorite::alerts.price_hint') }}</p>
    <a class="button button--secondary" href="{{ route('favorites.index') }}">{{ __('favorite::messages.favorites') }}</a>
    @foreach($errors->all() as $error)<p role="alert">{{ $error }}</p>@endforeach
    @forelse($alerts as $alert)
        @php($subject = $alert->subjectLabel())
        <article class="card stack stack--tight">
            <h2 class="title-card">{{ __('favorite::alerts.'.$alert->kind.'_toggle') }}</h2>
            <p>{{ $subject }}</p>
            @if(\Modules\Favorite\App\Models\BuyerAlert::source((int) $alert->user_id, $alert->kind, (int) $alert->source_id))
            @include('favorite::partials.alert-toggle', ['kind' => $alert->kind, 'sourceId' => $alert->source_id, 'enabled' => $alert->enabled, 'subject' => $subject])
            @endif
        </article>
    @empty
        <p>{{ __('favorite::alerts.empty') }}</p>
    @endforelse
    {{ $alerts->links('components.pagination') }}
</div></div>
@endsection
