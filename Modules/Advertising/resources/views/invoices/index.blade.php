@extends('panel::layouts.panel')
@php($panelSection = 'advertising-invoices')
@section('panel_content')
<section class="advertising-invoice-list card stack">
    <h1 class="text-title">{{ __('advertising::billing.invoices') }}</h1>
    <p class="text-meta">{{ __('advertising::billing.customer_hint') }}</p>
    @forelse($invoices as $invoice)
        <a class="advertising-invoice-list__item card stack" href="{{ route('advertising.invoices.show', $invoice) }}">
            <span dir="ltr">{{ $invoice->number }}</span>
            <span dir="auto">{{ $invoice->description }}</span>
            <span>{{ __('advertising::billing.'.$invoice->billingState()) }} · {{ number_format($invoice->amount) }} {{ __('advertising::billing.rial') }}</span>
            <span class="text-meta">{{ __('advertising::billing.balance') }}: {{ number_format($invoice->balance()) }} {{ __('advertising::billing.rial') }}</span>
        </a>
    @empty
        <p>{{ __('advertising::billing.empty') }}</p>
    @endforelse
    {{ $invoices->links() }}
</section>
@endsection
