<details class="saved-reply-picker">
    <summary>{{ __('conversation::replies.picker') }}</summary>
    <div class="saved-reply-picker__list">
        @forelse($savedReplies as $reply)
            <details class="saved-reply-picker__entry">
                <summary dir="auto">{{ $reply->title }}</summary>
                <p class="saved-reply-picker__body" dir="auto">{{ $reply->body }}</p>
                <button type="button" class="button button--secondary" disabled data-saved-reply="{{ $reply->body }}" data-reply-replace="{{ __('conversation::replies.replace') }}">{{ __('conversation::replies.insert') }}</button>
            </details>
        @empty
            <p class="text-muted">{{ __('conversation::replies.empty') }}</p>
        @endforelse
    </div>
    <a class="button button--ghost" href="{{ route('panel.saved-replies.index') }}">{{ __('conversation::replies.title') }}</a>
    <p class="field__hint">{{ __('conversation::replies.private') }}</p>
    <noscript><p>{{ __('conversation::replies.no_js') }}</p></noscript>
</details>
