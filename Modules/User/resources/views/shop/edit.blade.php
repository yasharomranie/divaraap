@extends('panel::layouts.panel', ['panelSection' => 'shop'])
@section('title', __('user::shop.title'))
@section('panel_content')
<header class="panel-head">
    <div class="panel-head__text">
        <h1 class="title-page">{{ __('user::shop.title') }}</h1>
        <p class="text-muted">{{ __('user::shop.lead') }}</p>
    </div>
    <a class="button button--secondary" href="{{ route('sellers.show', auth()->id()) }}">{{ __('user::shop.view') }}</a>
</header>
<section class="card">
    <form class="card__body stack stack--loose" method="post" action="{{ route('shop.update') }}">
        @csrf
        @method('PUT')
        <input type="hidden" name="version" value="{{ is_string(old('version', $version)) ? old('version', $version) : $version }}">
        @if($errors->any())
            <div class="alert alert--critical" role="alert" tabindex="-1" autofocus>
                @foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach
            </div>
        @endif
        <p class="field__hint">{{ __('user::shop.privacy') }}</p>
        <div class="field">
            <label class="field__label" for="shop-enabled">{{ __('user::shop.enabled') }}</label>
            <select class="select" id="shop-enabled" name="shop_enabled">
                <option value="0" @selected(old('shop_enabled', $shop['shop_enabled']) == 0)>{{ __('user::shop.off') }}</option>
                <option value="1" @selected(old('shop_enabled', $shop['shop_enabled']) == 1)>{{ __('user::shop.on') }}</option>
            </select>
        </div>
        @foreach(['shop_name' => ['name', 80], 'shop_description' => ['description', 1200], 'shop_hours' => ['hours', 160]] as $field => [$label, $limit])
            @php $value = old($field, $shop[$field]); @endphp
            <div class="field">
                <label class="field__label" for="{{ $field }}">{{ __('user::shop.'.$label) }}</label>
                @if($field === 'shop_description')
                    <textarea class="textarea" id="{{ $field }}" name="{{ $field }}" dir="auto" maxlength="{{ $limit }}" rows="4" @error($field) aria-invalid="true" aria-describedby="{{ $field }}-error" @enderror>{{ is_string($value) ? $value : '' }}</textarea>
                @else
                    <input class="input" id="{{ $field }}" name="{{ $field }}" dir="auto" maxlength="{{ $limit }}" value="{{ is_string($value) ? $value : '' }}" @error($field) aria-invalid="true" aria-describedby="{{ $field }}-error" @enderror>
                @endif
                @error($field)<p class="field__error" id="{{ $field }}-error">{{ $message }}</p>@enderror
            </div>
        @endforeach
        <button class="button button--primary" type="submit">{{ __('user::shop.save') }}</button>
        <div class="field">
            <label class="field__label" for="shop-link">{{ __('user::shop.link') }}</label>
            <input class="input" id="shop-link" type="url" dir="ltr" readonly value="{{ route('sellers.show', auth()->id()) }}">
        </div>
    </form>
</section>
@endsection
