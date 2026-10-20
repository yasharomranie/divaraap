<details class="chat-offers" @if($errors->has('amount') || $errors->has('message') || $errors->has('confirmed')) open @endif>
    <summary>{{ __('offer::chat.title') }}</summary>
    <div class="chat-offers__body stack">
        <p class="text-meta">{{ __('offer::chat.notice') }}</p>
        <div data-chat-offers="{{ route('conversation-offers.snapshot', $selectedConversation) }}" aria-live="polite" aria-atomic="false">
            @include('offer::partials.chat-cards')
        </div>
        @if(!$communicationBlocked && $listing && $listing->canPauseDisplay() && (int) $listing->user_id === (int) $selectedConversation->seller_id && $viewerId === (int) $selectedConversation->buyer_id)
            <form method="post" action="{{ route('conversation-offers.store', $selectedConversation) }}" class="stack">
                @csrf
                <label class="field" for="chat-offer-amount">
                    <span>{{ __('offer::messages.your_offer') }} ({{ \App\Support\Money::currencyLabel($listing->currency) }})</span>
                    <input id="chat-offer-amount" class="input" name="amount" inputmode="{{ $listing->currency === 'IRR' ? 'numeric' : 'decimal' }}" dir="ltr" maxlength="26" required value="{{ is_scalar(old('amount')) ? old('amount') : '' }}" aria-describedby="chat-offer-amount-error" @error('amount') aria-invalid="true" @enderror>
                </label>
                <p id="chat-offer-amount-error" class="field__error" role="alert">@error('amount'){{ $message }}@enderror</p>
                <label class="field" for="chat-offer-message">
                    <span>{{ __('offer::chat.optional_message') }}</span>
                    <textarea id="chat-offer-message" class="textarea" name="message" dir="auto" rows="2" maxlength="500" aria-describedby="chat-offer-message-error" @error('message') aria-invalid="true" @enderror>{{ is_scalar(old('message')) ? old('message') : '' }}</textarea>
                </label>
                <p id="chat-offer-message-error" class="field__error" role="alert">@error('message'){{ $message }}@enderror</p>
                <p class="text-meta">{{ __('offer::chat.replaces') }}</p>
                <button class="button button--primary" type="submit">{{ __('offer::messages.send_offer') }}</button>
            </form>
        @endif
        <p class="field__error" role="alert">@error('confirmed'){{ $message }}@enderror</p>
        <a class="button button--ghost" href="{{ route('panel.offers.index', ['direction' => $viewerId === (int) $selectedConversation->buyer_id ? 'sent' : 'received']) }}">{{ __('offer::chat.all') }}</a>
        <a class="text-meta" href="{{ route('panel.inbox.index', ['conversation' => $selectedConversation->id]) }}">{{ __('offer::chat.refresh') }}</a>
    </div>
</details>
