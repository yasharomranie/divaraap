@extends('site::layouts.app')
@section('title', __('listing::prices.title'))
@section('content')
<div class="shell price-guide wanted stack stack--loose">
    <header class="stack"><h1 class="title-page">{{ __('listing::prices.title') }}</h1><p>{{ __('listing::prices.intro') }}</p></header>
    @if($errors->any())<div class="alert alert--critical" role="alert"><p>{{ __('listing::prices.errors') }}</p><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
    <form class="card wanted-form" method="get" action="{{ route('price-guide.index') }}" role="search">
        <div class="field"><label class="field__label" for="guide-category_id">{{ __('listing::prices.category_id') }}</label><select class="input" id="guide-category_id" name="category_id" required @if($errors->has('category_id')) aria-invalid="true" aria-describedby="guide-category-error" @endif><option value="">{{ __('listing::prices.choose_category') }}</option>@foreach($categories as $id => $label)<option value="{{ $id }}" @selected(old('category_id', $filters['category_id'] ?? null) == $id)>{{ $label }}</option>@endforeach</select>@error('category_id')<p class="field__error" id="guide-category-error">{{ $message }}</p>@enderror</div>
        <div class="field"><label class="field__label" for="guide-city_id">{{ __('listing::prices.city_id') }}</label><select class="input" id="guide-city_id" name="city_id" @if($errors->has('city_id')) aria-invalid="true" aria-describedby="guide-city-error" @endif><option value="">{{ __('listing::prices.all_cities') }}</option>@foreach($cities as $id => $label)<option value="{{ $id }}" @selected(old('city_id', $filters['city_id'] ?? null) == $id)>{{ $label }}</option>@endforeach</select>@error('city_id')<p class="field__error" id="guide-city-error">{{ $message }}</p>@enderror</div>
        <div class="field wanted-wide"><label class="field__label" for="guide-search">{{ __('listing::prices.search') }}</label><input class="input" id="guide-search" name="search" minlength="2" maxlength="120" value="{{ is_string(old('search', $filters['search'] ?? '')) ? old('search', $filters['search'] ?? '') : '' }}" dir="auto" aria-describedby="guide-search-hint{{ $errors->has('search') ? ' guide-search-error' : '' }}" @if($errors->has('search')) aria-invalid="true" @endif><p class="field__hint" id="guide-search-hint">{{ __('listing::prices.search_hint') }}</p>@error('search')<p id="guide-search-error" class="field__error">{{ $message }}</p>@enderror</div>
        <div class="field"><label class="field__label" for="guide-condition">{{ __('listing::prices.condition') }}</label><select class="input" id="guide-condition" name="condition" @disabled($conditions === []) aria-describedby="guide-condition-hint{{ $errors->has('condition') ? ' guide-condition-error' : '' }}" @if($errors->has('condition')) aria-invalid="true" @endif><option value="">{{ __('listing::prices.all_conditions') }}</option>@foreach($conditions as $value => $label)<option value="{{ $value }}" @selected(old('condition', $filters['condition'] ?? null) === $value)>{{ $label }}</option>@endforeach</select><p class="field__hint" id="guide-condition-hint">{{ __('listing::prices.condition_hint') }}</p>@error('condition')<p id="guide-condition-error" class="field__error">{{ $message }}</p>@enderror</div>
        <div class="field"><label class="field__label" for="guide-currency">{{ __('listing::prices.currency') }}</label><select class="input" id="guide-currency" name="currency" @if($errors->has('currency')) aria-invalid="true" aria-describedby="guide-currency-error" @endif>@foreach($currencies as $code => $label)<option value="{{ $code }}" @selected(old('currency', $filters['currency'] ?? \Modules\Listing\Support\ListingPanelHelper::defaultCurrency()) === $code)>{{ $label }}</option>@endforeach</select>@error('currency')<p class="field__error" id="guide-currency-error">{{ $message }}</p>@enderror</div>
        <div class="wanted-actions wanted-wide"><button class="button button--primary" type="submit">{{ __('listing::prices.calculate') }}</button><a class="button button--ghost" href="{{ route('price-guide.index') }}">{{ __('listing::prices.reset') }}</a></div>
    </form>
    @if($report)
    <section class="stack" aria-labelledby="guide-result-title" data-price-guide-result>
        <h2 class="title-section" id="guide-result-title">{{ __('listing::prices.result') }} · {{ $categories[$filters['category_id']] }}</h2>
        <p>{{ $cities[$filters['city_id'] ?? 0] ?? __('listing::prices.all_cities') }} · {{ $conditions[$filters['condition'] ?? ''] ?? __('listing::prices.all_conditions') }}</p>
        <p class="text-muted">{{ __('listing::prices.period') }} <time datetime="{{ $report['from']->toIso8601String() }}">{{ \App\Support\PersianLocale::date($report['from']) }}</time> — <time datetime="{{ $report['until']->toIso8601String() }}">{{ \App\Support\PersianLocale::date($report['until']) }}</time></p>
        <p>{{ __('listing::prices.sample', ['count' => \App\Support\PersianLocale::number($report['count']), 'sellers' => \App\Support\PersianLocale::number($report['sellers'])]) }}</p>
        @if($report['limited'])<p class="alert">{{ __('listing::prices.limited') }}</p>@endif
        @if($report['sufficient'])
        <dl class="price-guide-stats">
            <div class="card stack price-guide-primary"><dt>{{ __('listing::prices.median') }}</dt><dd data-price-median><bdi>{{ \App\Support\Money::format($report['stats']['median'], $report['currency']) }}</bdi></dd></div>
            <div class="card stack"><dt>{{ __('listing::prices.range') }}</dt><dd><bdi>{{ \App\Support\Money::format($report['stats']['lower'], $report['currency']) }}</bdi> — <bdi>{{ \App\Support\Money::format($report['stats']['upper'], $report['currency']) }}</bdi><p class="field__hint">{{ __('listing::prices.range_hint') }}</p></dd></div>
            <div class="card stack"><dt>{{ __('listing::prices.min') }}</dt><dd><bdi>{{ \App\Support\Money::format($report['stats']['min'], $report['currency']) }}</bdi></dd></div>
            <div class="card stack"><dt>{{ __('listing::prices.max') }}</dt><dd><bdi>{{ \App\Support\Money::format($report['stats']['max'], $report['currency']) }}</bdi></dd></div>
        </dl>
        @else<p class="alert" role="status" data-price-insufficient>{{ __('listing::prices.insufficient') }}</p>@endif
    </section>
    @else<p>{{ __('listing::prices.start') }}</p>@endif
    <p class="alert">{{ __('listing::prices.disclaimer') }}</p>
    <details class="card stack"><summary>{{ __('listing::prices.method_title') }}</summary><p>{{ __('listing::prices.method') }}</p><p>{{ __('listing::prices.method_stats') }}</p></details>
</div>
@endsection
