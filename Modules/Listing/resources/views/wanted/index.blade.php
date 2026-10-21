@extends('site::layouts.app')
@section('title', __('listing::wanted.title'))
@section('content')
<div class="shell shell--wide wanted stack stack--loose">
    <header class="stack"><h1 class="title-page">{{ __('listing::wanted.title') }}</h1><p>{{ __('listing::wanted.intro') }}</p><div class="wanted-actions"><a class="button button--primary" href="{{ route('wanted.create') }}">{{ __('listing::wanted.create') }}</a>@auth<a class="button button--ghost" href="{{ route('wanted.mine') }}">{{ __('listing::wanted.mine') }}</a>@endauth</div></header>
    @include('listing::wanted.errors')
    <form class="card wanted-filters" method="get" action="{{ route('wanted.index') }}" role="search">
        <div class="field"><label class="field__label" for="wanted-search">{{ __('listing::wanted.search') }}</label><input class="input" id="wanted-search" name="search" maxlength="120" value="{{ is_string(request('search')) ? request('search') : '' }}"></div>
        <div class="field"><label class="field__label" for="wanted-category_id">{{ __('listing::wanted.category_id') }}</label><select class="input" name="category_id" id="wanted-category_id"><option value="">{{ __('listing::wanted.all') }}</option>@foreach($categories as $id => $label)<option value="{{ $id }}" @selected(request('category_id') == $id)>{{ $label }}</option>@endforeach</select></div>
        <div class="field"><label class="field__label" for="wanted-city_id">{{ __('listing::wanted.city_id') }}</label><select class="input" name="city_id" id="wanted-city_id"><option value="">{{ __('listing::wanted.any_city') }}</option>@foreach($cities as $id => $label)<option value="{{ $id }}" @selected(request('city_id') == $id)>{{ $label }}</option>@endforeach</select></div>
        <button class="button button--secondary" type="submit">{{ __('listing::wanted.filter') }}</button>
    </form>
    <p class="text-muted">{{ __('listing::wanted.public_hint') }}</p>
    <div class="wanted-grid">
        @forelse($records as $record)
            <article class="card stack wanted-card" data-wanted="{{ $record->id }}"><h2><a href="{{ route('wanted.show', $record) }}" dir="auto">{{ $record->title }}</a></h2>@include('listing::wanted.summary')<p class="wanted-description" dir="auto">{{ \Illuminate\Support\Str::limit($record->description, 180) }}</p><a class="button button--ghost" href="{{ route('wanted.show', $record) }}">{{ __('listing::wanted.details') }}</a></article>
        @empty<p>{{ __('listing::wanted.empty') }}</p>@endforelse
    </div>
    {{ $records->links() }}
</div>
@endsection
