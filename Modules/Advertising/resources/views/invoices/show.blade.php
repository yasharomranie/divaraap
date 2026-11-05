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
    @if($invoice->correctedAmount() > 0 || $invoice->supplementedAmount() > 0 || $invoice->refundedAmount() > 0 || $invoice->reservedRefundAmount() > 0)
        <dl class="advertising-invoice__totals">
            @foreach(['gross' => $invoice->grossReceivedAmount(), 'corrected_total' => $invoice->correctedAmount(), 'supplemented_total' => $invoice->supplementedAmount(), 'refunded_total' => $invoice->refundedAmount(), 'net' => $invoice->netReceivedAmount(), 'reserved' => $invoice->reservedRefundAmount()] as $label => $value)
                <dt>{{ __('advertising::refunds.'.$label) }}</dt><dd><bdi dir="ltr">{{ number_format($value) }}</bdi> {{ __('advertising::billing.rial') }}</dd>
            @endforeach
        </dl>
    @endif
    @if($invoice->refundedAmount() > 0)<p role="status">{{ __('advertising::refunds.collection_closed') }}</p>
    @elseif($invoice->reservedRefundAmount() > 0)<p role="status">{{ __('advertising::refunds.collection_hold') }}</p>@endif
    @include('advertising::invoices.payment-form')
    @if($invoice->voided_at)
        <p>{{ __('advertising::billing.reason') }}: <span dir="auto">{{ $invoice->void_reason }}</span></p>
    @endif
    <section class="stack" aria-label="{{ __('advertising::billing.payments') }}">
        <h2 class="text-title">{{ __('advertising::billing.payments') }}</h2>
        @forelse($invoice->payments as $payment)
            <dl class="advertising-invoice__payment">
                <dt>{{ __('advertising::gateway.source') }}</dt><dd>{{ __('advertising::gateway.'.($payment->gateway ?: 'manual')) }}</dd>
                <dt>{{ __('advertising::billing.reference') }}</dt><dd dir="ltr" style="overflow-wrap:anywhere">{{ $payment->reference }}</dd>
                <dt>{{ __('advertising::billing.amount') }}</dt><dd>{{ number_format($payment->amount) }} {{ __('advertising::billing.rial') }}</dd>
                <dt>{{ __('advertising::billing.recorded_at') }}</dt><dd><time datetime="{{ $payment->created_at->toIso8601String() }}">{{ $payment->created_at->timezone('Asia/Tehran')->format('Y/m/d H:i') }} {{ __('advertising::billing.tehran') }}</time></dd>
            </dl>
            @if(\Modules\Advertising\Models\AdvertisingPaymentAdjustment::availableForPayment($payment) > 0)
                <a class="button button--secondary advertising-invoice__screen" href="{{ route('refunds.create', [$invoice, $payment]) }}">{{ __('advertising::refunds.request') }} · <bdi dir="ltr">{{ $payment->reference }}</bdi></a>
            @endif
                @if(\Modules\Advertising\Models\AdvertisingInvoice::billingAdmin() && ! $payment->online_attempt_id && ! $payment->gateway && (\Modules\Advertising\Models\AdvertisingPaymentAdjustment::availableForPayment($payment) > 0 || $invoice->collectionOpen() && $invoice->balance() > 0))
                    @php($correctionOld = (string) old('correction_payment_id') === (string) $payment->id)
                    <details class="stack advertising-invoice__screen" @if($correctionOld && $errors->any()) open @endif>
                        <summary>{{ __('advertising::refunds.correct') }}</summary>
                        <p class="text-meta">{{ __('advertising::refunds.correction_help') }}</p>
                        <form method="POST" action="{{ route('refunds.correct', [$invoice, $payment]) }}" class="stack">
                            @csrf
                            <input type="hidden" name="operation_key" value="{{ $correctionOld ? old('operation_key') : \Illuminate\Support\Str::uuid() }}">
                            <input type="hidden" name="correction_payment_id" value="{{ $payment->id }}">
                            <div class="field"><label for="correction-direction-{{ $payment->id }}" class="field__label">{{ __('advertising::refunds.direction') }}</label><select id="correction-direction-{{ $payment->id }}" name="direction" class="input"><option value="decrease" @selected(! $correctionOld || old('direction', 'decrease') === 'decrease')>{{ __('advertising::refunds.decrease') }}</option><option value="increase" @selected($correctionOld && old('direction') === 'increase')>{{ __('advertising::refunds.increase') }}</option></select></div>
                            <div class="field"><label for="correction-amount-{{ $payment->id }}" class="field__label">{{ __('advertising::refunds.amount') }}</label><input id="correction-amount-{{ $payment->id }}" class="input" name="amount" type="text" inputmode="numeric" dir="ltr" maxlength="13" value="{{ $correctionOld ? old('amount') : '' }}" required></div>
                            <div class="field"><label for="correction-reason-{{ $payment->id }}" class="field__label">{{ __('advertising::refunds.reason') }}</label><textarea id="correction-reason-{{ $payment->id }}" class="input" name="reason" dir="auto" maxlength="500" rows="3" required>{{ $correctionOld ? old('reason') : '' }}</textarea></div>
                            <label class="checkbox"><input type="checkbox" name="confirmed" value="1" required @checked($correctionOld && old('confirmed'))><span>{{ __('advertising::refunds.correction_help') }}</span></label>
                            @if($correctionOld)
                                @foreach(['amount', 'reason', 'operation_key', 'confirmed', 'direction'] as $field) @error($field)<p class="field__error" role="alert">{{ $message }}</p>@enderror @endforeach
                            @endif
                            <button class="button button--secondary" type="submit">{{ __('advertising::refunds.correct') }}</button>
                        </form>
                    </details>
                @endif
        @empty
            <p class="text-meta">{{ __('advertising::billing.no_payments') }}</p>
        @endforelse
    </section>
    @include('advertising::invoices.refund-history')
    <p class="text-meta">{{ __('advertising::billing.receipt_notice') }}</p>
    <p class="text-meta advertising-invoice__screen">{{ __('advertising::billing.print_hint') }}</p>
    <a class="button button--secondary advertising-invoice__screen" href="{{ route('advertising.invoices.index') }}">{{ __('advertising::billing.back') }}</a>
</article>
@endsection
