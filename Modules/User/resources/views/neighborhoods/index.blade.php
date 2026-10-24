@extends('site::layouts.app')
@section('title', __('user::neighborhoods.title'))
@section('content')
<div class="shell wanted local-shops stack stack--loose">
    <header class="stack"><h1 class="title-page">{{ __('user::neighborhoods.title') }}</h1><p>{{ __('user::neighborhoods.intro') }}</p></header>
    @foreach($errors->all() as $error)<p class="alert alert--critical" role="alert">{{ $error }}</p>@endforeach
    <form method="get" action="{{ route('neighborhood-shops.index') }}" class="card wanted-form" role="search">
        <div class="field"><label class="field__label" for="shops-city_id">{{ __('user::neighborhoods.city_id') }}</label><select class="input" id="shops-city_id" name="city_id" required @if($errors->has('city_id')) aria-invalid="true" aria-describedby="shops-city-error" @endif><option value="">{{ __('user::neighborhoods.choose_city') }}</option>@foreach($cities as $id => $label)<option value="{{ $id }}" @selected(old('city_id', $filters['city_id'] ?? null) == $id)>{{ $label }}</option>@endforeach</select>@error('city_id')<p class="field__error" id="shops-city-error">{{ $message }}</p>@enderror</div>
        <div class="field"><label class="field__label" for="shops-search">{{ __('user::neighborhoods.search') }}</label><input class="input" id="shops-search" name="search" maxlength="120" @if($errors->has('search')) aria-invalid="true" aria-describedby="shops-search-error" @endif dir="auto" value="{{ is_string(old('search', $filters['search'] ?? '')) ? old('search', $filters['search'] ?? '') : '' }}">@error('search')<p class="field__error" id="shops-search-error">{{ $message }}</p>@enderror</div>
        <div class="wanted-actions wanted-wide"><button class="button button--primary" type="submit">{{ __('user::neighborhoods.find') }}</button><a class="button button--ghost" href="{{ route('neighborhood-shops.index') }}">{{ __('user::neighborhoods.reset') }}</a></div>
    </form>
    @if(!empty($filters['city_id']))
    <h2 class="title-section">{{ $cities[$filters['city_id']] }}</h2>
    <div class="wanted-grid">
    @forelse($records as $record)
        <article class="card stack wanted-card" data-local-shop="{{ $record['id'] }}"><span class="badge">{{ __('user::neighborhoods.selected') }}</span><h3 dir="auto"><a href="{{ route('sellers.show', $record['owner']) }}">{{ $record['name'] }}</a></h3><p dir="auto">{{ $record['neighborhood'] }}</p>@if($record['description'])<p class="wanted-description" dir="auto">{{ \Illuminate\Support\Str::limit($record['description'], 180) }}</p>@endif @if($record['hours'])<p dir="auto">{{ __('user::neighborhoods.hours') }}: <bdi dir="auto">{{ $record['hours'] }}</bdi></p>@endif<a class="button button--secondary" href="{{ route('sellers.show', $record['owner']) }}">{{ __('user::neighborhoods.open') }}</a></article>
    @empty<p>{{ __('user::neighborhoods.empty') }}</p>@endforelse
    </div>{{ $records->links() }}
    @else<p>{{ __('user::neighborhoods.choose_city_hint') }}</p>@endif
    <p class="alert">{{ __('user::neighborhoods.disclaimer') }}</p>
</div>
@endsection
