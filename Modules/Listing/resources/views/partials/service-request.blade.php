<section class="card service-request" id="service-request" aria-labelledby="service-request-title" tabindex="-1">
    <div class="card__head"><h2 class="card__title" id="service-request-title">{{ __('listing::service.title') }}</h2></div>
    <div class="card__body stack">
        <p class="text-meta">{{ __('listing::service.hint') }}</p>
        @auth
            @if($canContact && (string) $viewer->status === 'active')
                <form method="POST" action="{{ route('conversations.start', $listing) }}" class="stack">
                    @csrf
                    <input type="hidden" name="service_request" value="1">
                    <label for="service-request-message">{{ __('listing::service.message') }}</label>
                    <textarea id="service-request-message" name="message" class="textarea" rows="3" maxlength="2000" required aria-describedby="service-request-hint @error('message') service-request-error @enderror" @error('message') aria-invalid="true" @enderror placeholder="{{ __('listing::service.placeholder') }}">{{ old('message') }}</textarea>
                    <p class="text-meta" id="service-request-hint">{{ __('listing::service.privacy') }}</p>
                    @error('message')<p class="text-danger" role="alert" id="service-request-error">{{ $message }}</p>@enderror
                    <button type="submit" class="button button--primary">{{ __('listing::service.send') }}</button>
                </form>
            @else
                <p class="text-meta">{{ __('listing::service.unavailable') }}</p>
            @endif
        @else
            <a class="button button--primary" href="{{ route('login') }}">{{ __('listing::service.login') }}</a>
        @endauth
    </div>
</section>
