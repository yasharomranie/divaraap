@if($order->bundle_id)
    <section class="card stack">
        <h2 class="title-section">{{ __('advertising::bundles.items') }}</h2>
        <p>{{ __('advertising::bundles.captured') }}</p>
        <strong>{{ number_format($order->bundle_snapshot['price']) }} {{ __('advertising::billing.rial') }}</strong>
        @foreach($order->bundle_snapshot['items'] as $index => $item)
            <div class="stack" data-bundle-item="{{ $index + 1 }}">
                <strong>{{ __('advertising::admin.'.$item['placement']) }}</strong>
                <p>{{ __('advertising::admin.'.$item['device']) }} · {{ $item['duration_days'] }} {{ __('advertising::orders.days') }}</p>
                <p>{{ isset($order->bundle_fulfillment[$index + 1]) ? __('advertising::bundles.done') : __('advertising::bundles.pending') }}</p>
                @if(isset($order->bundle_fulfillment[$index + 1]))
                    <time datetime="{{ $order->bundle_fulfillment[$index + 1]['at'] }}">{{ \Illuminate\Support\Carbon::parse($order->bundle_fulfillment[$index + 1]['at'])->timezone('Asia/Tehran')->format('Y/m/d H:i') }} {{ __('advertising::billing.tehran') }}</time>
                @endif
            </div>
        @endforeach
        <p class="text-meta">{{ __('advertising::bundles.fulfill_hint') }}</p>
    </section>
@endif
