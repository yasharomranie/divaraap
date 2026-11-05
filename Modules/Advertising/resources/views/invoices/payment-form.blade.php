@if($invoice->unallocatedAmount() > 0)
    <div class="alert alert--critical" role="status">
        <p>{{ __('advertising::gateway.overpayment') }}: <bdi dir="ltr">{{ number_format($invoice->unallocatedAmount()) }}</bdi> {{ __('advertising::billing.rial') }}</p>
        <p>{{ __('advertising::gateway.overpayment_help') }}</p>
    </div>
@endif
@if((int) auth()->id() === (int) $invoice->user_id)
    <section class="stack advertising-invoice__screen" aria-label="{{ __('advertising::gateway.pay_title') }}">
        <h2 class="text-title">{{ __('advertising::gateway.pay_title') }}</h2>
        @if($paymentAttempt)
            <p role="status">{{ __('advertising::gateway.'.$paymentAttempt->status) }} — {{ __('advertising::gateway.'.$paymentAttempt->gateway) }}</p>
            @if($paymentAttempt->sandbox)<p class="text-meta">{{ __('advertising::gateway.test_notice') }}</p>@endif
            @if(! in_array($paymentAttempt->status, ['verified', 'test_passed'], true))
                <p class="text-meta">{{ __('advertising::gateway.status_help') }}</p>
                <form method="POST" action="{{ route('payments.check', $paymentAttempt) }}">
                    @csrf
                    <button class="button button--secondary" type="submit">{{ __('advertising::gateway.check') }}</button>
                </form>
                @if($paymentAttempt->status === 'pending' && ! $invoice->voided_at && $invoice->balance() === $paymentAttempt->amount)
                    <form method="POST" action="{{ route('payments.resume', $paymentAttempt) }}">
                        @csrf
                        <button class="button button--primary" type="submit">{{ __('advertising::gateway.resume') }}</button>
                    </form>
                @endif
            @endif
        @endif
        @error('gateway')<p class="field__error" role="alert">{{ $message }}</p>@enderror
        @if(! $invoice->voided_at && $invoice->balance() > 0 && (! $paymentAttempt || in_array($paymentAttempt->status, ['failed', 'test_passed', 'verified'], true)))
            @if($gateways && \Modules\Advertising\Models\OnlinePaymentAttempt::validAmount($invoice->balance()))
                <form method="POST" action="{{ route('payments.start', $invoice) }}" class="stack">
                    @csrf
                    <fieldset class="stack" style="border:0;padding:0;min-width:0">
                        <legend class="field__label">{{ __('advertising::gateway.choose') }}</legend>
                        @foreach($gateways as $option)
                            <label class="checkbox" style="min-block-size:44px" for="gateway-{{ $option['gateway'] }}">
                                <input id="gateway-{{ $option['gateway'] }}" type="radio" name="gateway" value="{{ $option['gateway'] }}" required @checked(old('gateway', count($gateways) === 1 ? $option['gateway'] : null) === $option['gateway'])>
                                <span>{{ __('advertising::gateway.'.$option['gateway']) }} @if($option['sandbox']) — {{ __('advertising::gateway.test_mode') }} @endif</span>
                            </label>
                        @endforeach
                    </fieldset>
                    <button class="button button--primary" type="submit">{{ __('advertising::gateway.pay') }}</button>
                </form>
            @else
                <p class="text-meta">{{ $gateways ? __('advertising::gateway.invalid_amount') : __('advertising::gateway.no_gateway') }}</p>
            @endif
        @endif
    </section>
@endif
