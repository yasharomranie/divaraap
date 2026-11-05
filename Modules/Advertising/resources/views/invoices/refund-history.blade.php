<section class="stack" aria-label="{{ __('advertising::refunds.requests') }}">
    <h2 class="text-title">{{ __('advertising::refunds.requests') }}</h2>
    @forelse($invoice->refundRequests as $refund)
        <article class="card stack">
            <p>#{{ $refund->id }} · {{ __('advertising::refunds.'.$refund->state) }}</p>
            <p><bdi dir="ltr" style="overflow-wrap:anywhere">{{ number_format($refund->amount) }}</bdi> {{ __('advertising::billing.rial') }} · {{ __('advertising::refunds.payment') }} #{{ $refund->payment_id }}</p>
            <p dir="auto" style="overflow-wrap:anywhere">{{ $refund->reason }}</p>
            @if($refund->decision_reason)<p dir="auto" style="overflow-wrap:anywhere">{{ $refund->decision_reason }}</p>@endif
            @if($refund->state === 'requested' && (int) auth()->id() === (int) $refund->user_id)
                <form method="POST" action="{{ route('refunds.withdraw', $refund) }}">
                    @csrf
                    <input type="hidden" name="version" value="{{ $refund->version }}">
                    <button type="submit" class="button button--secondary">{{ __('advertising::refunds.withdraw') }}</button>
                </form>
            @endif
        </article>
    @empty
        <p class="text-meta">{{ __('advertising::refunds.no_requests') }}</p>
    @endforelse
</section>
@if($invoice->adjustments->isNotEmpty())
    <section class="stack" aria-label="{{ __('advertising::refunds.history') }}">
        <h2 class="text-title">{{ __('advertising::refunds.history') }}</h2>
        @foreach($invoice->adjustments as $entry)
            <article class="card stack">
                <p>{{ __('advertising::refunds.'.$entry->kind) }} · <bdi dir="ltr" style="overflow-wrap:anywhere">{{ number_format($entry->amount) }}</bdi> {{ __('advertising::billing.rial') }}</p>
                <p><bdi dir="ltr" style="overflow-wrap:anywhere">{{ $entry->reference }}</bdi> · {{ __('advertising::refunds.payment') }} #{{ $entry->payment_id }}</p>
                <p dir="auto" style="overflow-wrap:anywhere">{{ $entry->reason }}</p>
                <time datetime="{{ $entry->created_at->toIso8601String() }}">{{ $entry->created_at->timezone('Asia/Tehran')->format('Y/m/d H:i') }}</time>
            </article>
        @endforeach
    </section>
@endif
