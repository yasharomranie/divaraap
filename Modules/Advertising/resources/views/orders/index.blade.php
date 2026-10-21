@extends('panel::layouts.panel', ['panelSection' => 'advertising-orders'])
@section('title', __('advertising::orders.orders'))
@section('panel_content')
<section class="advertising-orders stack stack--loose">
    <h1 class="title-page">{{ __('advertising::orders.orders') }}</h1>
    <a class="button button--primary" href="{{ route('advertising.orders.create') }}">{{ __('advertising::orders.create') }}</a>
    @forelse($orders as $order)
        <a class="card stack" href="{{ route('advertising.orders.show', $order) }}">
            <strong dir="auto">{{ $order->title }}</strong>
            <span>{{ __('advertising::orders.number') }}: {{ $order->id }} · {{ __('advertising::orders.'.$order->state) }}</span>
            <span class="text-meta">{{ __('advertising::admin.'.$order->placement) }} · {{ $order->duration_days }} {{ __('advertising::orders.days') }}</span>
        </a>
    @empty
        <p>{{ __('advertising::orders.empty') }}</p>
    @endforelse
    {{ $orders->links() }}
</section>
@endsection
