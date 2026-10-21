@extends('site::layouts.app')
@section('title', $record->title)
@section('content')
<div class="shell wanted wanted-detail stack stack--loose">
    <a href="{{ route('wanted.index') }}">{{ __('listing::wanted.back') }}</a>
    <article class="card stack"><span class="badge">{{ __('listing::wanted.title') }}</span><h1 class="title-page" dir="auto">{{ $record->title }}</h1>@include('listing::wanted.summary')<p class="wanted-description" dir="auto">{{ $record->description }}</p><p class="field__hint">{{ __('listing::wanted.budget_hint') }}</p></article>
    @include('listing::wanted.errors')
    @if($own)<a class="button button--primary" href="{{ route('wanted.own', $record) }}">{{ __('listing::wanted.manage') }}</a>
    @elseif($suggested)<p class="alert" role="status">{{ __('listing::wanted.already_suggested') }}</p>
    @elseif($choices !== [])
        <form class="card stack" method="post" action="{{ route('wanted.suggest', $record) }}">@csrf<input type="hidden" name="revision" value="{{ $record->revision }}"><h2>{{ __('listing::wanted.suggest') }}</h2><label class="field__label" for="wanted-listing_id">{{ __('listing::wanted.choose_listing') }}</label><select class="input" id="wanted-listing_id" name="listing_id" required><option value="">{{ __('listing::wanted.choose_listing') }}</option>@foreach($choices as $id => $label)<option value="{{ $id }}">{{ $label }} (#{{ $id }})</option>@endforeach</select><p class="field__hint">{{ __('listing::wanted.suggest_hint') }}</p><button class="button button--primary" type="submit">{{ __('listing::wanted.suggest') }}</button></form>
    @elseif(auth()->check())<p>{{ __('listing::wanted.no_choices') }}</p>@else<a class="button button--primary" href="{{ route('login') }}">{{ __('listing::wanted.login') }}</a>@endif
</div>
@endsection
