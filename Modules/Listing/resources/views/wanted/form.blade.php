@extends('panel::layouts.panel', ['panelSection' => $panelKey])
@section('title', __($record->exists ? $copy.'.edit' : $copy.'.create'))
@section('panel_content')
<div class="wanted stack stack--loose">
    <h1 class="title-page">{{ __($record->exists ? $copy.'.edit' : $copy.'.create') }}</h1>
    <p>{{ __($copy.'.hint') }}</p>
    @if($record->exists)<p class="alert">{{ __($copy.'.edit_hint') }}</p>@endif
    @include('listing::wanted.errors')
    <form class="card wanted-form" method="post" action="{{ $record->exists ? route($routePrefix.'.update', $record) : route($routePrefix.'.store') }}">
        @csrf
        @if($record->exists)@method('put')<input type="hidden" name="revision" value="{{ old('revision', $record->revision) }}">@else<input type="hidden" name="request_key" value="{{ old('request_key', $requestKey) }}">@endif
        <div class="field wanted-wide"><label class="field__label" for="wanted-title">{{ __($copy.'.title_field') }}</label><input class="input" id="wanted-title" name="title" minlength="5" maxlength="120" value="{{ old('title', $record->title) }}" required dir="auto" @if($errors->has('title')) aria-invalid="true" aria-describedby="wanted-title-error" autofocus @endif>@error('title')<p id="wanted-title-error" class="field__error">{{ $message }}</p>@enderror</div>
        <div class="field wanted-wide"><label class="field__label" for="wanted-description">{{ __($copy.'.description') }}</label><textarea class="input" id="wanted-description" name="description" rows="4" minlength="10" maxlength="2000" required dir="auto" @if($errors->has('description')) aria-invalid="true" aria-describedby="wanted-description-error" @endif>{{ old('description', $record->description) }}</textarea>@error('description')<p id="wanted-description-error" class="field__error">{{ $message }}</p>@enderror</div>
        @foreach(['category_id' => $categories, 'city_id' => $cities] as $field => $options)
            <div class="field"><label class="field__label" for="wanted-{{ $field }}">{{ __($copy.'.'.$field) }}</label><select class="input" id="wanted-{{ $field }}" name="{{ $field }}" @required($field === 'category_id' || $serviceMode) @if($errors->has($field)) aria-invalid="true" aria-describedby="wanted-{{ $field }}-error" @endif><option value="">{{ __($copy.'.'.($field === 'city_id' ? ($serviceMode ? 'choose_city' : 'any_city') : 'choose_category')) }}</option>@foreach($options as $id => $label)<option value="{{ $id }}" @selected(old($field, $record->$field) == $id)>{{ $label }}</option>@endforeach</select>@error($field)<p id="wanted-{{ $field }}-error" class="field__error">{{ $message }}</p>@enderror</div>
        @endforeach
        @if($serviceMode)
        <div class="field wanted-wide"><label class="field__label" for="wanted-preferred_time">{{ __($copy.'.preferred_time') }}</label><input class="input" id="wanted-preferred_time" name="preferred_time" maxlength="120" value="{{ old('preferred_time', $record->preferred_time) }}" dir="auto" aria-describedby="wanted-time-hint{{ $errors->has('preferred_time') ? ' wanted-time-error' : '' }}" @if($errors->has('preferred_time')) aria-invalid="true" @endif><p class="field__hint" id="wanted-time-hint">{{ __($copy.'.time_hint') }}</p>@error('preferred_time')<p class="field__error" id="wanted-time-error">{{ $message }}</p>@enderror</div>
        @endif
        <div class="field wanted-wide"><label class="field__label" for="wanted-budget">{{ __($copy.'.budget') }}</label><input class="input" type="number" inputmode="numeric" id="wanted-budget" name="budget" min="1" max="1000000000000" value="{{ old('budget', $record->budget) }}" aria-describedby="wanted-budget-hint{{ $errors->has('budget') ? ' wanted-budget-error' : '' }}" @if($errors->has('budget')) aria-invalid="true" @endif><p class="field__hint" id="wanted-budget-hint">{{ __($copy.'.budget_hint') }}</p>@error('budget')<p id="wanted-budget-error" class="field__error">{{ $message }}</p>@enderror</div>
        <div class="wanted-actions wanted-wide"><button class="button button--primary" type="submit">{{ __($record->exists ? $copy.'.save' : $copy.'.submit') }}</button><a class="button button--ghost" href="{{ route($routePrefix.'.mine') }}">{{ __($copy.'.back') }}</a></div>
    </form>
</div>
@endsection
