@extends('panel::layouts.panel', ['panelSection' => 'inbox'])
@section('title', __('conversation::safety.blocked_users'))
@section('panel_content')
<header class="stack stack--tight">
    <a class="button button--ghost" href="{{ route('panel.inbox.index') }}">{{ __('conversation::safety.back') }}</a>
    <h1 class="title-page">{{ __('conversation::safety.blocked_users') }}</h1>
    <p>{{ __('conversation::safety.list_hint') }}</p>
</header>
@forelse($blocks as $block)
    <article class="card"><div class="card__body stack stack--tight">
        <h2 class="title-card" dir="auto">{{ $users[$block->blocked_id]['name'] ?? __('conversation::safety.unknown') }}</h2>
        <form class="stack stack--tight" method="post" action="{{ route('panel.blocked-users.unblock', $block->blocked_id) }}">
            @csrf
            <label class="row"><input type="checkbox" name="confirmed" value="1" required><span>{{ __('conversation::safety.confirm') }}</span></label>
            <button class="button button--secondary" type="submit">{{ __('conversation::safety.unblock') }}</button>
        </form>
    </div></article>
@empty
    <p>{{ __('conversation::safety.empty') }}</p>
@endforelse
{{ $blocks->links('components.pagination') }}
@endsection
