@php
    $viewerId = (int) auth()->id();
    $blocked = \Modules\Conversation\App\Models\ConversationBlock::between((int) $selectedConversation->buyer_id, (int) $selectedConversation->seller_id);
@endphp
<div class="stack">
    @forelse($chatOffers as $offer)
        <article class="chat-offers__card stack" data-chat-offer="{{ $offer->id }}" data-offer-status="{{ $offer->status }}">
            <div class="row row--wrap">
                <strong dir="auto">{{ $offer->amountLabel() }}</strong>
                <span class="badge">{{ __('offer::messages.status_'.$offer->status) }}</span>
            </div>
            <time class="text-meta" datetime="{{ $offer->created_at->toIso8601String() }}">{{ \App\Support\PersianLocale::date($offer->created_at->copy()->timezone('Asia/Tehran')) }}</time>
            @if($offer->message)<p class="text-meta" dir="auto">{{ $offer->message }}</p>@endif
            @if($offer->isPending() && ($offer->belongsToBuyer($viewerId) || !$blocked))
                <form method="post" action="{{ route('conversation-offers.respond', [$selectedConversation, $offer]) }}" class="stack">
                    @csrf
                    <label class="chat-offers__confirm"><input type="checkbox" name="confirmed" value="1" required> <span>{{ __('offer::chat.confirm') }}</span></label>
                    <div class="row row--wrap">
                        @if($offer->belongsToSeller($viewerId))
                            <button class="button button--primary" name="decision" value="accepted" type="submit">{{ __('offer::messages.accept') }}</button>
                            <button class="button button--secondary" name="decision" value="declined" type="submit">{{ __('offer::messages.decline') }}</button>
                        @else
                            <button class="button button--secondary" name="decision" value="withdrawn" type="submit">{{ __('offer::messages.withdraw') }}</button>
                        @endif
                    </div>
                </form>
            @endif
        </article>
    @empty
        <p class="text-meta">{{ __('offer::messages.no_offers') }}</p>
    @endforelse
</div>
