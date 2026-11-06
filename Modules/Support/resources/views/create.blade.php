@extends('panel::layouts.panel', ['panelSection' => 'support'])
@section('title', __('support::messages.new'))
@section('panel_content')
<section class="card card__body stack support-ticket">
    <h1 class="text-title">{{ __('support::messages.new') }}</h1>
    <p class="text-meta">{{ __('support::messages.help') }}</p>
    <p class="text-meta">{{ __('support::messages.limit_help') }}</p>
    <form method="POST" action="{{ route('support.store') }}" class="stack">
        @csrf
        <input type="hidden" name="form_key" value="{{ old('form_key', $key) }}">
        <div class="field"><label for="support-subject" class="field__label">{{ __('support::messages.subject') }}</label><input id="support-subject" class="input" name="subject" dir="auto" maxlength="150" value="{{ old('subject') }}" required>@error('subject')<p class="field__error" role="alert">{{ $message }}</p>@enderror</div>
        <div class="field"><label for="support-category" class="field__label">{{ __('support::messages.category') }}</label><select id="support-category" class="input" name="category" required>@foreach(\Modules\Support\Models\SupportTicket::CATEGORIES as $category)<option value="{{ $category }}" @selected(old('category', 'technical') === $category)>{{ __('support::messages.category_'.$category) }}</option>@endforeach</select>@error('category')<p class="field__error" role="alert">{{ $message }}</p>@enderror</div>
        <div class="field"><label for="support-body" class="field__label">{{ __('support::messages.body') }}</label><textarea id="support-body" class="input" name="body" dir="auto" rows="5" maxlength="4000" required>{{ old('body') }}</textarea>@error('body')<p class="field__error" role="alert">{{ $message }}</p>@enderror</div>
        @foreach(['ticket', 'form_key', 'operation_key'] as $field) @error($field)<p class="field__error" role="alert">{{ $message }}</p>@enderror @endforeach
        <button class="button button--primary" type="submit">{{ __('support::messages.submit') }}</button>
    </form>
    <a class="button button--secondary" href="{{ route('support.index') }}">{{ __('support::messages.back') }}</a>
</section>
@endsection
