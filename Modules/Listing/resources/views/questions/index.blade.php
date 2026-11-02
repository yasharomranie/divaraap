@extends('site::layouts.app')
@section('title', __('listing::questions.title'))
@section('content')
<div class="shell listing-questions stack stack--loose">
    <a href="{{ route('listings.show', $listing) }}">{{ __('listing::questions.back') }}</a>
    <header class="stack stack--tight"><h1 class="title-page">{{ __('listing::questions.title') }}</h1><p dir="auto">{{ $listing->title }}</p><p class="text-muted">{{ __('listing::questions.intro') }}</p></header>
    @forelse($records as $record)
        <article class="card stack" data-public-question><h2 class="field__label">{{ __('listing::questions.public_user') }}</h2><p class="listing-questions__text" dir="auto">{{ $record->question }}</p><h3 class="field__label">{{ __('listing::questions.answer') }}</h3><p class="listing-questions__text" dir="auto">{{ $record->answer }}</p></article>
    @empty<p>{{ __('listing::questions.empty') }}</p>@endforelse
    {{ $records->links() }}
    @auth
        <a class="button button--ghost" href="{{ route('listing-questions.mine') }}">{{ __('listing::questions.manage') }}</a>
        @if((int) auth()->id() !== (int) $listing->user_id)
            <form class="card stack" method="POST" action="{{ route('listing-questions.store', $listing) }}">@csrf
                <h2>{{ __('listing::questions.ask') }}</h2><p id="question-privacy" class="field__hint">{{ __('listing::questions.privacy') }}</p>
                <label class="field__label" for="public-question">{{ __('listing::questions.question') }}</label><textarea class="textarea" id="public-question" name="question" rows="3" minlength="10" maxlength="600" required aria-describedby="question-privacy @error('question') question-error @enderror" @error('question') aria-invalid="true" @enderror>{{ old('question') }}</textarea>
                @error('question')<p class="field__error" id="question-error" role="alert">{{ $message }}</p>@enderror
                <label class="listing-questions__consent"><input type="checkbox" name="public_consent" value="1" required><span>{{ __('listing::questions.consent') }}</span></label>
                @error('public_consent')<p class="field__error" role="alert">{{ $message }}</p>@enderror
                <button class="button button--primary" type="submit">{{ __('listing::questions.ask') }}</button>
            </form>
        @else<p class="field__hint">{{ __('listing::questions.owner_hint') }}</p>@endif
    @else<a class="button button--primary" href="{{ route('login') }}">{{ __('listing::questions.login') }}</a>@endauth
</div>
@endsection
