@extends('panel::layouts.panel', ['panelSection' => 'advertising-orders'])
@section('title', __('advertising::orders.order'))
@section('panel_content')
<article class="advertising-orders stack stack--loose">
    <header class="stack">
        <h1 class="title-page" dir="auto">{{ $order->title }}</h1>
        <p>{{ __('advertising::orders.number') }}: {{ $order->id }} · <strong>{{ __('advertising::orders.'.$order->state) }}</strong></p>
    </header>
    @if(session('status'))<p class="alert" role="status">{{ session('status') }}</p>@endif
    @if($errors->any())<div class="alert alert--critical" role="alert">@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>@endif
    <section class="card stack">
        <h2 class="title-section">{{ __('advertising::orders.brief') }}</h2>
        <p class="advertising-orders__text" dir="auto">{{ $order->brief }}</p>
        <p>{{ __('advertising::campaign.goal') }}: {{ __('advertising::campaign.'.$order->goal) }}</p>
        <p>{{ __('advertising::orders.placement') }}: {{ __('advertising::admin.'.$order->placement) }}</p>
        <p>{{ __('advertising::orders.duration') }}: {{ $order->duration_days }}</p>
        @if($order->requested_budget)<p>{{ __('advertising::orders.budget') }}: {{ number_format($order->requested_budget) }} {{ __('advertising::billing.rial') }}</p>@endif
        <p class="text-meta">{{ __('advertising::orders.created') }}: {{ $order->created_at->timezone('Asia/Tehran')->format('Y/m/d H:i') }} {{ __('advertising::billing.tehran') }}</p>
    </section>
    @if($order->manager_note)
        <section class="card stack"><h2 class="title-section">{{ __('advertising::orders.terms') }}</h2><p class="advertising-orders__text" dir="auto">{{ $order->manager_note }}</p></section>
    @endif
    @if($order->rejection_reason)
        <p class="alert" dir="auto">{{ $order->rejection_reason }}</p>
    @endif
    @if($order->invoice)
        <section class="card stack">
            <h2 class="title-section">{{ __('advertising::billing.invoice') }}</h2>
            <p>{{ number_format($order->invoice->amount) }} {{ __('advertising::billing.rial') }} · {{ __('advertising::billing.'.$order->invoice->billingState()) }}</p>
            <a class="button button--secondary" href="{{ route('advertising.invoices.show', $order->invoice) }}">{{ __('advertising::billing.view') }}</a>
        </section>
    @endif
    @if((int) auth()->id() === (int) $order->user_id)
        @if($order->state === 'quoted' && $order->invoice && ! $order->invoice->voided_at)
            <form method="post" action="{{ route('advertising.orders.accept', $order) }}" class="card stack">
                @csrf
                <p>{{ __('advertising::orders.accept_hint') }}</p>
                <button class="button button--primary" type="submit">{{ __('advertising::orders.accept') }}</button>
            </form>
        @elseif($order->state === 'submitted')
            <form method="post" action="{{ route('advertising.orders.cancel', $order) }}">
                @csrf
                <button class="button button--secondary" type="submit">{{ __('advertising::orders.cancel') }}</button>
            </form>
        @endif
    @endif
    <p class="text-meta">{{ __('advertising::orders.request_hint') }}</p>
    <a class="button button--ghost" href="{{ route('advertising.orders.index') }}">{{ __('advertising::orders.back') }}</a>
</article>
@endsection
