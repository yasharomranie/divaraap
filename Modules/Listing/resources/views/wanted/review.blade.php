<div class="space-y-3" data-wanted-review="{{ $record->id }}">
    <h2 class="font-semibold" dir="auto">{{ $record->title }}</h2>
    <p>{{ __('listing::wanted.revision') }}: {{ $record->revision }} · {{ $record->stateLabel() }}</p>
    @include('listing::wanted.summary')
    <p class="whitespace-pre-wrap break-words" dir="auto">{{ $record->description }}</p>
    @if($record->moderation_note)<p>{{ __('listing::wanted.review_note') }}: {{ $record->moderation_note }}</p>@endif
</div>
