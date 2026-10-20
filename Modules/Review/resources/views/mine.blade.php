@extends('panel::layouts.panel', ['panelSection' => 'reviews'])
@section('title', __('review::quality.mine'))
@section('panel_content')
<header class="stack stack--tight">
    <h1 class="title-page">{{ __('review::quality.mine') }}</h1>
    <p>{{ __('review::quality.lead') }}</p>
</header>
@forelse($reviews as $review)
    <article class="card"><div class="card__body stack stack--tight">
        <div class="row row--wrap row--between">
            <a href="{{ route('sellers.show', $review->sellerId()) }}">{{ $sellers[$review->sellerId()]['name'] ?? __('review::quality.seller') }}</a>
            <span class="badge">{{ $review->moderationLabel() }}</span>
        </div>
        <x-ui.rating :average="$review->ratingValue()" :total="1" :show-count="false"/>
        @include('review::partials.interaction')
        @if($review->title)<h2 class="title-card" dir="auto">{{ $review->title }}</h2>@endif
        @if($review->body)<p dir="auto" style="white-space:pre-line;overflow-wrap:anywhere">{{ $review->body }}</p>@endif
        <time class="text-meta" datetime="{{ $review->created_at->toIso8601String() }}">{{ \App\Support\PersianLocale::date($review->created_at->copy()->timezone('Asia/Tehran'), true) }}</time>
        <form method="post" action="{{ route('reviews.destroy', $review) }}" data-confirm="{{ __('review::quality.confirm_delete') }}">
            @csrf
            @method('DELETE')
            <button class="button button--critical" type="submit">{{ __('review::quality.delete') }}</button>
        </form>
    </div></article>
@empty
    <p>{{ __('review::quality.empty') }}</p>
@endforelse
{{ $reviews->links('components.pagination') }}
@endsection
