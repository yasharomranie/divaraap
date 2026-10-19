@if($listing->statusValue() === 'paused')
    <form method="post" action="{{ route('panel.listings.resume', $listing) }}">
        @csrf
        <button type="submit" class="button button--secondary button--small">{{ __('listing::availability.resume') }}</button>
    </form>
    @if($listing->expires_at && !$listing->expires_at->isFuture())
        <form method="post" action="{{ route('panel.listings.republish', $listing) }}">
            @csrf
            <button type="submit" class="button button--ghost button--small">{{ __('listing::availability.extend') }}</button>
        </form>
    @endif
@elseif($listing->canPauseDisplay())
    <form method="post" action="{{ route('panel.listings.pause', $listing) }}" data-confirm="{{ __('listing::availability.confirm') }}">
        @csrf
        <button type="submit" class="button button--ghost button--small">{{ __('listing::availability.pause') }}</button>
    </form>
@endif
