@extends('site::layouts.app')
@section('chromeless', '1')
@section('title', __('advertising::gateway.result'))
@section('content')
<section class="shell shell--form stack" style="padding-block:var(--space-12)">
    <h1 class="title-section">{{ __('advertising::gateway.result') }}</h1>
    <p role="status">{{ __('advertising::gateway.'.$state) }}</p>
    @if($sandbox)<p>{{ __('advertising::gateway.test_notice') }}</p>@endif
    @if(! in_array($state, ['verified', 'test_passed'], true))<p>{{ __('advertising::gateway.status_help') }}</p>@endif
    <a class="button button--primary" href="{{ route('advertising.invoices.index') }}">{{ __('advertising::gateway.back') }}</a>
</section>
@endsection
