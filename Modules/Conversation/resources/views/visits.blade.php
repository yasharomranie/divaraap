@extends('panel::layouts.panel', ['panelSection' => 'inbox'])
@section('title', __('conversation::visits.title'))
@section('panel_content')
@php
    $viewerId = (int) auth()->id();
    $listing = $conversation->listing;
@endphp
<div class="visit-page stack stack--loose">
    <header class="stack stack--tight">
        <a class="button button--ghost" href="{{ route('panel.inbox.index', ['conversation' => $conversation->id]) }}">{{ __('conversation::visits.back') }}</a>
        <h1 class="title-page">{{ __('conversation::visits.title') }}</h1>
        @if($listing)<p dir="auto">{{ $listing->title }}</p>@endif
        <p class="text-meta">{{ __('conversation::visits.hint') }}</p>
    </header>
    @if($communicationBlocked)<p class="alert" role="status">{{ __('conversation::safety.unavailable') }}</p>@endif
    @if($hasUpcoming)
        <p class="alert" role="status">{{ __('conversation::visits.existing') }}</p>
    @elseif(!$communicationBlocked && $listing && $listing->canPauseDisplay() && (int) $listing->user_id === (int) $conversation->seller_id)
        <section class="card"><div class="card__body stack">
            <h2 class="title-section">{{ __('conversation::visits.propose') }}</h2>
            <form class="stack" method="post" action="{{ route('conversations.visits.store', $conversation) }}">
                @csrf
                <div class="field">
                    <label class="field__label" for="visit-time">{{ __('conversation::visits.time') }}</label>
                    <input class="input" type="datetime-local" id="visit-time" name="visit_time" dir="ltr" required min="{{ now('Asia/Tehran')->addMinutes(15)->ceilMinute()->format('Y-m-d\TH:i') }}" max="{{ now('Asia/Tehran')->addDays(90)->format('Y-m-d\TH:i') }}" value="{{ is_string(old('visit_time')) ? old('visit_time') : '' }}" aria-describedby="visit-time-hint visit-time-error" @error('visit_time') aria-invalid="true" @enderror>
                    <p id="visit-time-hint" class="text-meta">{{ __('conversation::visits.time_hint') }}</p>
                    <p id="visit-time-error" class="field__error" role="alert">@error('visit_time'){{ $message }}@enderror</p>
                </div>
                <div class="field">
                    <label class="field__label" for="visit-place">{{ __('conversation::visits.place') }}</label>
                    <input class="input" id="visit-place" name="meeting_place" dir="auto" required maxlength="200" value="{{ is_string(old('meeting_place')) ? old('meeting_place') : '' }}" aria-describedby="visit-place-error" @error('meeting_place') aria-invalid="true" @enderror>
                    <p id="visit-place-error" class="field__error" role="alert">@error('meeting_place'){{ $message }}@enderror</p>
                </div>
                <div class="field">
                    <label class="field__label" for="visit-note">{{ __('conversation::visits.note') }}</label>
                    <textarea class="textarea" id="visit-note" name="visit_note" dir="auto" maxlength="500" rows="2" aria-describedby="visit-note-error" @error('visit_note') aria-invalid="true" @enderror>{{ is_string(old('visit_note')) ? old('visit_note') : '' }}</textarea>
                    <p id="visit-note-error" class="field__error" role="alert">@error('visit_note'){{ $message }}@enderror</p>
                </div>
                <label class="visit-confirm"><input type="checkbox" name="visit_confirmed" value="1" required><span>{{ __('conversation::visits.confirm_proposal') }}</span></label>
                <button type="submit" class="button button--primary">{{ __('conversation::visits.send') }}</button>
            </form>
        </div></section>
    @endif
    <p class="field__error" role="alert">@error('visit_confirmed'){{ $message }}@enderror @error('visit_time')@if($hasUpcoming){{ $message }}@endif @enderror</p>
    <section class="stack" aria-labelledby="visit-history">
        <h2 class="title-section" id="visit-history">{{ __('conversation::visits.history') }}</h2>
        @forelse($visits as $visit)
            <article class="card" data-visit="{{ $visit->id }}" data-visit-status="{{ $visit->status }}"><div class="card__body stack">
                <div class="row row--wrap">
                    <span class="badge">{{ __('conversation::visits.status_'.$visit->displayStatus()) }}</span>
                    <span class="text-meta">{{ (int) $visit->proposed_by === $viewerId ? __('conversation::visits.from_you') : __('conversation::visits.from_partner') }}</span>
                </div>
                <time datetime="{{ $visit->starts_at->toIso8601String() }}">{{ \App\Support\PersianLocale::date($visit->starts_at->setTimezone('Asia/Tehran'), true) }} — {{ __('conversation::visits.duration') }}</time>
                <p class="text-meta">{{ __('conversation::visits.timezone') }}</p>
                <p class="visit-private" dir="auto">{{ $visit->meeting_place }}</p>
                @if($visit->note)<p class="text-meta visit-private" dir="auto">{{ $visit->note }}</p>@endif
                @if(in_array($visit->status, ['pending', 'accepted'], true) && $visit->starts_at->isFuture())
                    <form class="stack" method="post" action="{{ route('conversations.visits.decide', [$conversation, $visit]) }}">
                        @csrf
                        <label class="visit-confirm"><input type="checkbox" name="visit_confirmed" value="1" required><span>{{ __('conversation::visits.confirm_decision') }}</span></label>
                        <div class="row row--wrap">
                            @if(!$communicationBlocked && $visit->status === 'pending' && (int) $visit->proposed_by !== $viewerId)
                                <button class="button button--primary" type="submit" name="decision" value="accepted">{{ __('conversation::visits.accept') }}</button>
                                <button class="button button--secondary" type="submit" name="decision" value="declined">{{ __('conversation::visits.decline') }}</button>
                            @endif
                            <button class="button button--ghost" type="submit" name="decision" value="cancelled">{{ __('conversation::visits.cancel') }}</button>
                        </div>
                    </form>
                @endif
            </div></article>
        @empty
            <p class="text-muted">{{ __('conversation::visits.empty') }}</p>
        @endforelse
        {{ $visits->links('components.pagination') }}
    </section>
    <a class="button button--secondary" href="{{ route('conversations.visits.index', $conversation) }}">{{ __('conversation::visits.refresh') }}</a>
</div>
@endsection
