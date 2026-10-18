<form method="POST" action="{{ route('favorites.alerts.configure') }}">
    @csrf
    <input type="hidden" name="kind" value="{{ $kind }}">
    <input type="hidden" name="source_id" value="{{ $sourceId }}">
    <input type="hidden" name="enabled" value="{{ $enabled ? '0' : '1' }}">
    <button type="submit" class="button button--secondary button--small" aria-label="{{ __('favorite::alerts.'.$kind.'_toggle') }}: {{ $subject }}" aria-pressed="{{ $enabled ? 'true' : 'false' }}">{{ __('favorite::alerts.'.$kind.'_toggle') }} · {{ __('favorite::alerts.'.($enabled ? 'on' : 'off')) }}</button>
</form>
