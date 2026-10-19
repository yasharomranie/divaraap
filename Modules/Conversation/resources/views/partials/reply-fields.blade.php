@php
    $title = is_string($title) ? $title : '';
    $body = is_string($body) ? $body : '';
    $position = is_scalar($position) ? $position : 0;
@endphp
<div class="field">
    <label class="field__label" for="{{ $prefix }}-title">{{ __('conversation::replies.label') }}</label>
    <input id="{{ $prefix }}-title" class="input" name="title" dir="auto" value="{{ $title }}" maxlength="80" required>
</div>
<div class="field">
    <label class="field__label" for="{{ $prefix }}-body">{{ __('conversation::replies.body') }}</label>
    <textarea id="{{ $prefix }}-body" class="textarea" name="body" dir="auto" rows="4" maxlength="2000" required>{{ $body }}</textarea>
</div>
<div class="field">
    <label class="field__label" for="{{ $prefix }}-position">{{ __('conversation::replies.position') }}</label>
    <input id="{{ $prefix }}-position" class="input" name="position" type="number" dir="ltr" min="0" max="9999" value="{{ $position }}" aria-describedby="{{ $prefix }}-order-hint">
    <p class="field__hint" id="{{ $prefix }}-order-hint">{{ __('conversation::replies.position_hint') }}</p>
</div>
