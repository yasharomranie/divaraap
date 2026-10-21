@if($order->reservations->isNotEmpty())
    <section class="card stack">
        <h2 class="title-section">{{ __('advertising::reservations.reservations') }}</h2>
        @foreach($order->reservations as $reservation)
            <div class="stack" data-order-reservation="{{ $reservation->id }}">
                <strong dir="auto">{{ $reservation->title }}</strong>
                <p>{{ __('advertising::admin.'.$reservation->device) }} · {{ __('advertising::reservations.'.$reservation->displayState()) }}</p>
                <p>{{ __('advertising::reservations.starts_at') }}: <time datetime="{{ $reservation->starts_at->toIso8601String() }}">{{ $reservation->starts_at->copy()->timezone('Asia/Tehran')->format('Y/m/d H:i') }}</time></p>
                <p>{{ __('advertising::reservations.ends_at') }}: <time datetime="{{ $reservation->ends_at->toIso8601String() }}">{{ $reservation->ends_at->copy()->timezone('Asia/Tehran')->format('Y/m/d H:i') }}</time> {{ __('advertising::billing.tehran') }}</p>
                @if($reservation->cancel_reason)<p dir="auto">{{ $reservation->cancel_reason }}</p>@endif
            </div>
        @endforeach
        <p class="text-meta">{{ __('advertising::reservations.scope') }}</p>
    </section>
@endif
