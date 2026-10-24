@extends('site::layouts.app')
@section('title', __('favorite::collections.title'))
@section('content')
<div class="shell page collections-page"><div class="stack stack--loose">
    <h1 class="title-page">{{ __('favorite::collections.title') }}</h1>
    <p class="text-muted">{{ __('favorite::collections.hint') }}</p>
    <a class="button button--ghost" href="{{ route('favorites.index') }}">{{ __('favorite::collections.favorites') }}</a>
    <form method="POST" action="{{ route('collections.store') }}" class="collection-create stack stack--tight">
        @csrf
        <label for="collection-name">{{ __('favorite::collections.name') }}</label>
        <input class="input" id="collection-name" name="name" value="{{ old('editing_collection') ? '' : old('name') }}" maxlength="60" required>
        @unless(old('editing_collection')) @error('name')<p role="alert">{{ $message }}</p>@enderror @endunless
        <button class="button button--primary" type="submit">{{ __('favorite::collections.create') }}</button>
    </form>
    @forelse($collections as $id => $name)
        <article class="collection-entry stack stack--tight">
            <form method="POST" action="{{ route('collections.update', $id) }}" class="stack stack--tight">
                @csrf @method('PUT')
                <label for="collection-{{ $id }}">{{ __('favorite::collections.name') }}</label>
                <input class="input" id="collection-{{ $id }}" name="name" value="{{ (int) old('editing_collection') === (int) $id ? old('name', $name) : $name }}" maxlength="60" required dir="auto">
                @if((int) old('editing_collection') === (int) $id) @error('name')<p role="alert">{{ $message }}</p>@enderror @endif
                <button class="button button--secondary" type="submit">{{ __('favorite::collections.rename') }}</button>
            </form>
            <a class="button button--ghost" href="{{ route('favorites.index', ['collection' => $id]) }}">{{ __('favorite::collections.open') }}</a>
            <a class="button button--secondary" href="{{ route('shortlists.manage', $id) }}">{{ __('favorite::sharing.manage') }}</a>
            <details><summary>{{ __('favorite::collections.delete') }}</summary>
                <p>{{ __('favorite::collections.delete_confirm') }}</p>
                <form method="POST" action="{{ route('collections.destroy', $id) }}">@csrf @method('DELETE')<button class="button button--ghost" type="submit">{{ __('favorite::collections.delete') }}</button></form>
            </details>
        </article>
    @empty
        <p>{{ __('favorite::collections.empty') }}</p>
    @endforelse
</div></div>
@endsection
