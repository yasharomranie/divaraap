@extends('site::layouts.app')
@section('title', __('category::checklists.title'))
@section('content')
<div class="shell">
<div class="trade-checklist-page stack stack--loose">
    <header class="stack stack--tight">
        <a class="button button--ghost" href="{{ route('listings.show', $listing) }}">{{ __('category::checklists.back') }}</a>
        <h1 class="title-page">{{ __('category::checklists.title') }}</h1>
        <p dir="auto">{{ $listing->title }}</p>
        <p class="text-meta">{{ __('category::checklists.hint') }}</p>
    </header>
    @if($state['changed'])<p class="alert" role="status">{{ __('category::checklists.changed') }}</p>@endif
    <section class="card"><div class="card__body stack">
        <p role="status">{{ __('category::checklists.progress', ['done' => \App\Support\PersianLocale::digits(count($state['checked'])), 'total' => \App\Support\PersianLocale::digits(count($state['items']))]) }}</p>
        <form method="post" action="{{ route('listings.checklist.save', $listing) }}" class="stack">
            @csrf
            <input type="hidden" name="revision" value="{{ $state['revision'] }}">
            <input type="hidden" name="version" value="{{ $state['version'] }}">
            @foreach($state['items'] as $item)
                <label class="trade-checklist-item">
                    <input type="checkbox" name="checked[]" value="{{ $item['key'] }}" @checked(in_array($item['key'], $state['checked'], true))>
                    <span dir="auto">{{ app()->getLocale() === 'fa' ? $item['fa'] : $item['en'] }}</span>
                </label>
            @endforeach
            @foreach(['revision', 'version', 'checked'] as $field)
                @error($field)<p class="field__error" role="alert">{{ $message }}</p>@enderror
            @endforeach
            @auth
                <p class="text-meta">{{ __('category::checklists.private') }}</p>
                <button class="button button--primary" type="submit">{{ __('category::checklists.save') }}</button>
            @else
                <a class="button button--secondary" href="{{ route('login') }}">{{ __('category::checklists.login') }}</a>
            @endauth
        </form>
    </div></section>
    <a class="button button--ghost" href="{{ route('listings.checklist', $listing) }}">{{ __('category::checklists.refresh') }}</a>
</div>
</div>
@endsection
