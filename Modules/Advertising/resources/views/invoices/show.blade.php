@extends('panel::layouts.panel')
@php($panelSection = 'advertising-invoices')
@section('panel_content')
<article class="advertising-invoice card stack stack--loose">
    <header class="stack">
        <h1 class="text-title">{{ __('advertising::billing.invoice') }}</h1>
        <p dir="ltr">{{ $invoice->number }}</p>
        <p>{{ __('advertising::billing.'.$invoice->billingState()) }}</p>
        <p class="text-meta">{{ __('advertising::billing.issued_at') }}: <time datetime="{{ $invoice->created_at->toIso8601String() }}">{{ $invoice->created_at->timezone('Asia/Tehran')->format('Y/m/d H:i') }} {{ __('advertising::billing.tehran') }}</time></p>
    </header>
    <section class="stack" aria-label="{{ __('advertising::billing.customer') }}">
        <h2 class="text-title">{{ __('advertising::billing.customer') }}</h2>
        <p dir="auto">{{ $invoice->customer['name'] }}</p>
        <p dir="ltr">{{ $invoice->customer['email'] }}</p>
    </section>
    <p dir="auto">{{ $invoice->description }}</p>
    <dl class="advertising-invoice__totals">
        <dt>{{ __('advertising::billing.amount') }}</dt><dd>{{ number_format($invoice->amount) }} {{ __('advertising::billing.rial') }}</dd>
        <dt>{{ __('advertising::billing.received') }}</dt><dd>{{ number_format($invoice->paidAmount()) }} {{ __('advertising::billing.rial') }}</dd>
        <dt>{{ __('advertising::billing.balance') }}</dt><dd>{{ number_format($invoice->balance()) }} {{ __('advertising::billing.rial') }}</dd>
    </dl>
    @if($invoice->voided_at)
        <p>{{ __('advertising::billing.reason') }}: <span dir="auto">{{ $invoice->void_reason }}</span></p>
    @endif
    <section class="stack" aria-label="{{ __('advertising::billing.payments') }}">
        <h2 class="text-title">{{ __('advertising::billing.payments') }}</h2>
        @forelse($invoice->payments as $payment)
            <dl class="advertising-invoice__payment">
                <dt>{{ __('advertising::billing.reference') }}</dt><dd dir="ltr">{{ $payment->reference }}</dd>
                <dt>{{ __('advertising::billing.amount') }}</dt><dd>{{ number_format($payment->amount) }} {{ __('advertising::billing.rial') }}</dd>
                <dt>{{ __('advertising::billing.recorded_at') }}</dt><dd><time datetime="{{ $payment->created_at->toIso8601String() }}">{{ $payment->created_at->timezone('Asia/Tehran')->format('Y/m/d H:i') }} {{ __('advertising::billing.tehran') }}</time></dd>
            </dl>
        @empty
            <p class="text-meta">{{ __('advertising::billing.no_payments') }}</p>
        @endforelse
    </section>
    <p class="text-meta">{{ __('advertising::billing.receipt_notice') }}</p>
    <p class="text-meta advertising-invoice__screen">{{ __('advertising::billing.print_hint') }}</p>
    <a class="button button--secondary advertising-invoice__screen" href="{{ route('advertising.invoices.index') }}">{{ __('advertising::billing.back') }}</a>
</article>
@endsection
