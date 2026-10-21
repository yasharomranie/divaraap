@extends('panel::layouts.panel', ['panelSection' => 'advertising-orders'])
@section('title', __('advertising::orders.create'))
@section('panel_content')
<div class="advertising-orders stack stack--loose">
    <h1 class="title-page">{{ __('advertising::orders.create') }}</h1>
    <p>{{ __('advertising::orders.request_hint') }}</p>
    @if($errors->any())<div class="alert alert--critical" role="alert">@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>@endif
    <form class="card stack" method="post" action="{{ route('advertising.orders.store') }}">
        @csrf
        <input type="hidden" name="request_key" value="{{ old('request_key', $requestKey) }}">
        <label class="field__label" for="order-title">{{ __('advertising::orders.title') }}</label>
        <input class="input" id="order-title" name="title" maxlength="120" value="{{ old('title') }}" required>
        <label class="field__label" for="order-brief">{{ __('advertising::orders.brief') }}</label>
        <textarea class="input" id="order-brief" name="brief" maxlength="2000" rows="4" required>{{ old('brief') }}</textarea>
        <label class="field__label" for="order-goal">{{ __('advertising::campaign.goal') }}</label>
        <select class="input" id="order-goal" name="goal" required>
            @foreach(['awareness', 'traffic', 'conversation'] as $goal)<option value="{{ $goal }}" @selected(old('goal', 'awareness') === $goal)>{{ __('advertising::campaign.'.$goal) }}</option>@endforeach
        </select>
        <label class="field__label" for="order-placement">{{ __('advertising::orders.placement') }}</label>
        <select class="input" id="order-placement" name="placement" required>
            @foreach($placements as $key => $label)<option value="{{ $key }}" @selected(old('placement') === $key)>{{ $label }}</option>@endforeach
        </select>
        <label class="field__label" for="order-days">{{ __('advertising::orders.duration') }}</label>
        <input class="input" id="order-days" name="duration_days" type="number" inputmode="numeric" min="1" max="90" value="{{ old('duration_days', 7) }}" required>
        <label class="field__label" for="order-budget">{{ __('advertising::orders.budget') }}</label>
        <input class="input" id="order-budget" name="requested_budget" type="number" inputmode="numeric" min="1" max="1000000000000" value="{{ old('requested_budget') }}" aria-describedby="order-budget-hint">
        <p class="field__hint" id="order-budget-hint">{{ __('advertising::orders.budget_hint') }}</p>
        <button class="button button--primary" type="submit" @disabled($placements === [])>{{ __('advertising::orders.submit') }}</button>
        @if($placements === [])<p>{{ __('advertising::orders.no_placements') }}</p>@endif
    </form>
</div>
@endsection
