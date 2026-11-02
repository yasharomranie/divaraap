@extends('panel::layouts.panel', ['panelSection' => 'listing-questions'])
@section('title', __('listing::questions.mine'))
@section('panel_content')
<div class="listing-questions stack stack--loose">
    <h1 class="title-page">{{ __('listing::questions.mine') }}</h1>
    <nav class="listing-questions__tabs" aria-label="{{ __('listing::questions.mine') }}">
        @foreach([false => 'asked', true => 'received'] as $role => $label)<a class="button button--ghost" @if($received === (bool) $role) aria-current="page" @endif href="{{ route('listing-questions.mine', ['role' => $role ? 'received' : 'asked']) }}">{{ __('listing::questions.'.$label) }}</a>@endforeach
    </nav>
    @if(session('success'))<p class="alert" role="status">{{ session('success') }}</p>@endif
    @if($errors->any())<div class="alert" role="alert">@foreach($errors->all() as $message)<p>{{ $message }}</p>@endforeach</div>@endif
    @forelse($records as $record)
        <article class="card stack" data-owned-question="{{ $record->id }}">
            <h2 dir="auto">{{ $listings[$record->listing_id]->title ?? __('listing::questions.listing') }}</h2>
            <p><span class="badge">{{ $record->stateLabel() }}</span></p>
            <p class="listing-questions__text" dir="auto">{{ $record->question }}</p>
            @if($record->answer)<h3 class="field__label">{{ __('listing::questions.answer') }}</h3><p class="listing-questions__text" dir="auto">{{ $record->answer }}</p>@endif
            @if($record->review_note)<p class="field__hint" dir="auto">{{ __('listing::questions.review_note') }}: {{ $record->review_note }}</p>@endif
            @if($received)
                <form class="stack" method="POST" action="{{ route('listing-questions.answer', $record) }}">@csrf
                    <input type="hidden" name="revision" value="{{ $record->revision }}"><input type="hidden" name="question_id" value="{{ $record->id }}">
                    <p class="field__hint" id="answer-hint-{{ $record->id }}">{{ __('listing::questions.reply_hint') }} {{ __('listing::questions.privacy') }}</p>
                    <label class="field__label" for="question-answer-{{ $record->id }}">{{ __('listing::questions.answer') }}</label><textarea class="textarea" rows="3" minlength="2" maxlength="1200" id="question-answer-{{ $record->id }}" name="answer" required aria-describedby="answer-hint-{{ $record->id }}" @if((string) old('question_id') === (string) $record->id && $errors->has('answer')) aria-invalid="true" @endif>{{ (string) old('question_id') === (string) $record->id ? old('answer') : $record->answer }}</textarea>
                    @if((string) old('question_id') === (string) $record->id) @error('answer')<p class="field__error" role="alert">{{ $message }}</p>@enderror @endif
                    <label class="listing-questions__consent"><input type="checkbox" name="public_consent" value="1" required><span>{{ __('listing::questions.consent') }}</span></label>
                    @if((string) old('question_id') === (string) $record->id) @error('public_consent')<p class="field__error" role="alert">{{ $message }}</p>@enderror @endif
                    <button class="button button--primary" type="submit">{{ __('listing::questions.save_answer') }}</button>
                </form>
            @else
                <form class="stack" method="POST" action="{{ route('listing-questions.withdraw', $record) }}">@csrf @method('DELETE')<input type="hidden" name="revision" value="{{ $record->revision }}">
                    <label class="listing-questions__consent"><input type="checkbox" name="confirm_withdraw" value="1" required><span>{{ __('listing::questions.withdraw_confirm') }}</span></label>
                    <button class="button button--ghost" type="submit">{{ __('listing::questions.withdraw') }}</button>
                </form>
            @endif
        </article>
    @empty<p>{{ __('listing::questions.private_empty') }}</p>@endforelse
    {{ $records->links() }}
</div>
@endsection
