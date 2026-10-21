@extends('panel::layouts.panel', ['panelSection' => 'wanted'])
@section('title', __($record->exists ? 'listing::wanted.edit' : 'listing::wanted.create'))
@section('panel_content')
<div class="wanted stack stack--loose">
    <h1 class="title-page">{{ __($record->exists ? 'listing::wanted.edit' : 'listing::wanted.create') }}</h1>
    <p>{{ __('listing::wanted.hint') }}</p>
    @if($record->exists)<p class="alert">{{ __('listing::wanted.edit_hint') }}</p>@endif
    @include('listing::wanted.errors')
    <form class="card wanted-form" method="post" action="{{ $record->exists ? route('wanted.update', $record) : route('wanted.store') }}">
        @csrf
        @if($record->exists)@method('put')<input type="hidden" name="revision" value="{{ old('revision', $record->revision) }}">@else<input type="hidden" name="request_key" value="{{ old('request_key', $requestKey) }}">@endif
        <div class="field wanted-wide"><label class="field__label" for="wanted-title">{{ __('listing::wanted.title_field') }}</label><input class="input" id="wanted-title" name="title" minlength="5" maxlength="120" value="{{ old('title', $record->title) }}" required dir="auto" @if($errors->has('title')) aria-invalid="true" aria-describedby="wanted-title-error" autofocus @endif>@error('title')<p id="wanted-title-error" class="field__error">{{ $message }}</p>@enderror</div>
        <div class="field wanted-wide"><label class="field__label" for="wanted-description">{{ __('listing::wanted.description') }}</label><textarea class="input" id="wanted-description" name="description" rows="4" minlength="10" maxlength="2000" required dir="auto" @if($errors->has('description')) aria-invalid="true" aria-describedby="wanted-description-error" @endif>{{ old('description', $record->description) }}</textarea>@error('description')<p id="wanted-description-error" class="field__error">{{ $message }}</p>@enderror</div>
        @foreach(['category_id' => $categories, 'city_id' => $cities] as $field => $options)
            <div class="field"><label class="field__label" for="wanted-{{ $field }}">{{ __('listing::wanted.'.$field) }}</label><select class="input" id="wanted-{{ $field }}" name="{{ $field }}" @required($field === 'category_id') @if($errors->has($field)) aria-invalid="true" aria-describedby="wanted-{{ $field }}-error" @endif><option value="">{{ __('listing::wanted.'.($field === 'city_id' ? 'any_city' : 'choose_category')) }}</option>@foreach($options as $id => $label)<option value="{{ $id }}" @selected(old($field, $record->$field) == $id)>{{ $label }}</option>@endforeach</select>@error($field)<p id="wanted-{{ $field }}-error" class="field__error">{{ $message }}</p>@enderror</div>
        @endforeach
        <div class="field wanted-wide"><label class="field__label" for="wanted-budget">{{ __('listing::wanted.budget') }}</label><input class="input" type="number" inputmode="numeric" id="wanted-budget" name="budget" min="1" max="1000000000000" value="{{ old('budget', $record->budget) }}" aria-describedby="wanted-budget-hint{{ $errors->has('budget') ? ' wanted-budget-error' : '' }}" @if($errors->has('budget')) aria-invalid="true" @endif><p class="field__hint" id="wanted-budget-hint">{{ __('listing::wanted.budget_hint') }}</p>@error('budget')<p id="wanted-budget-error" class="field__error">{{ $message }}</p>@enderror</div>
        <div class="wanted-actions wanted-wide"><button class="button button--primary" type="submit">{{ __($record->exists ? 'listing::wanted.save' : 'listing::wanted.submit') }}</button><a class="button button--ghost" href="{{ route('wanted.mine') }}">{{ __('listing::wanted.back') }}</a></div>
    </form>
</div>
@endsection
