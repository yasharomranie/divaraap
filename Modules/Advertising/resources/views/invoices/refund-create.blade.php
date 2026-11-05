@extends('panel::layouts.panel')
@php($panelSection = 'advertising-invoices')
@section('title', __('advertising::refunds.request'))
@section('panel_content')
<section class="advertising-refund card stack">
    <h1 class="text-title">{{ __('advertising::refunds.request') }}</h1>
    <p><bdi dir="ltr" style="overflow-wrap:anywhere">{{ $invoice->number }}</bdi> · <bdi dir="ltr" style="overflow-wrap:anywhere">{{ $payment->reference }}</bdi></p>
    <p>{{ __('advertising::refunds.available') }}: <bdi dir="ltr" style="overflow-wrap:anywhere">{{ number_format($available) }}</bdi> {{ __('advertising::billing.rial') }}</p>
    <p class="text-meta">{{ __('advertising::refunds.request_help') }}</p>
    <x-ui.flash/>
    @if($available > 0)
        <form method="POST" action="{{ route('refunds.store', [$invoice, $payment]) }}" class="stack">
            @csrf
            <input type="hidden" name="form_key" value="{{ old('form_key', $formKey) }}">
            <div class="field">
                <label for="refund-amount" class="field__label">{{ __('advertising::refunds.amount') }}</label>
                <input id="refund-amount" name="amount" type="text" inputmode="numeric" dir="ltr" class="input" maxlength="13" value="{{ old('amount') }}" required aria-invalid="{{ $errors->has('amount') ? 'true' : 'false' }}">
                @error('amount')<p class="field__error">{{ $message }}</p>@enderror
            </div>
            <div class="field">
                <label for="refund-reason" class="field__label">{{ __('advertising::refunds.reason') }}</label>
                <textarea id="refund-reason" name="reason" dir="auto" class="input" maxlength="500" rows="3" required aria-describedby="refund-reason-help">{{ old('reason') }}</textarea>
                <p id="refund-reason-help" class="text-meta">{{ __('advertising::refunds.reason_help') }}</p>
                @error('reason')<p class="field__error">{{ $message }}</p>@enderror
            </div>
            <button type="submit" class="button button--primary">{{ __('advertising::refunds.submit') }}</button>
        </form>
    @else
        <p role="status">{{ __('advertising::refunds.no_available') }}</p>
    @endif
    <a class="button button--secondary" href="{{ route('advertising.invoices.show', $invoice) }}">{{ __('advertising::refunds.back') }}</a>
</section>
@endsection
