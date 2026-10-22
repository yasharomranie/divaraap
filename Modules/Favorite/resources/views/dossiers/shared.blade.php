@extends('site::layouts.app')
@section('title', __('favorite::dossier.shared_title'))
@section('content')
<div class="shell shell--wide page comparison-page dossier stack stack--loose">
    <h1 class="title-page">{{ __('favorite::dossier.shared_title') }}</h1>
    <p>{{ __('favorite::dossier.live') }}</p>
    @if($listings->count() < count($record->listing_ids))<p class="alert">{{ __('favorite::dossier.missing') }}</p>@endif
    <p>{{ __('favorite::dossier.expires') }} <time datetime="{{ $record->expires_at->toIso8601String() }}">{{ \App\Support\PersianLocale::date($record->expires_at) }}</time></p>
    <form method="get" action="{{ url()->current() }}" class="row row--wrap"><label><input type="checkbox" name="differences" value="1" @checked($differences)> {{ __('favorite::comparison.differences') }}</label><button class="button button--secondary" type="submit">{{ __('favorite::comparison.apply') }}</button></form>
    @include('favorite::dossiers.table', ['readOnly' => true])
    @if($differences && $rows === [])<p role="status">{{ __('favorite::comparison.same') }}</p>@endif
</div>
@endsection
