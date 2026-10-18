@if($collections !== [])
<form method="POST" action="{{ route('collections.move', $listing->id) }}" class="collection-move">
    @csrf
    <label class="visually-hidden" for="collection-listing-{{ $listing->id }}">{{ __('favorite::collections.choose') }}: {{ $listing->title }}</label>
    <select class="select" id="collection-listing-{{ $listing->id }}" name="collection_id">
        <option value="">{{ __('favorite::collections.none') }}</option>
        @foreach($collections as $id => $name)<option value="{{ $id }}" @selected((int) $listing->pivot->collection_id === (int) $id)>{{ $name }}</option>@endforeach
    </select>
    <button type="submit" class="button button--secondary button--small">{{ __('favorite::collections.move') }}</button>
</form>
@endif
