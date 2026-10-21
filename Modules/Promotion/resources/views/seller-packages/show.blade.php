@extends('panel::layouts.panel', ['panelSection' => 'seller-packages'])
@section('title', __('promotion::packages.purchase'))
@section('panel_content')
<article class="seller-packages stack stack--loose">
    <h1 class="title-page" dir="auto">{{ $purchase->snapshot['name'] }}</h1>
    @if(session('status'))<p role="status" class="alert">{{ session('status') }}</p>@endif
    @if($errors->any())<div class="alert alert--critical" role="alert">@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>@endif
    <section class="card stack">
        <strong>{{ __('promotion::packages.'.$purchase->displayState()) }}</strong>
        <p>{{ number_format($purchase->snapshot['price']) }} {{ __('advertising::billing.rial') }}</p>
        <p>{{ __('promotion::packages.remaining') }}: {{ $purchase->remaining() }} / {{ $purchase->snapshot['credits'] }}</p>
        <p>{{ __('promotion::packages.per_credit') }}: {{ $purchase->snapshot['plan_name'] }} · {{ $purchase->snapshot['promotion_days'] }} {{ __('promotion::admin.days') }}</p>
        @if($purchase->ends_at)<p>{{ __('promotion::packages.expires') }}: {{ $purchase->ends_at->copy()->timezone('Asia/Tehran')->format('Y/m/d H:i') }} {{ __('advertising::billing.tehran') }}</p>@endif
        @if($purchase->cancel_reason)<p dir="auto">{{ $purchase->cancel_reason }}</p>@endif
        @if($purchase->invoice)<a class="button button--secondary" href="{{ route('advertising.invoices.show', $purchase->invoice) }}">{{ __('advertising::billing.view') }}</a>@endif
    </section>
    @if($purchase->displayState() === 'active')
        <form method="post" action="{{ route('seller-packages.redeem', $purchase) }}" class="card stack">
            @csrf
            <input type="hidden" name="request_key" value="{{ old('request_key', $requestKey) }}">
            <label class="field__label" for="package-listing">{{ __('promotion::packages.listing') }}</label>
            <select class="input" id="package-listing" name="listing_id" required>
                @foreach($listings as $id => $title)<option value="{{ $id }}" @selected((int) old('listing_id') === (int) $id)>{{ $title }}</option>@endforeach
            </select>
            <p class="field__hint">{{ __('promotion::packages.redeem_hint') }}</p>
            <button class="button button--primary" type="submit" @disabled($listings === [])>{{ __('promotion::packages.redeem') }}</button>
        </form>
    @endif
    <section class="card stack">
        <h2 class="title-section">{{ __('promotion::packages.history') }}</h2>
        @forelse($purchase->redemptions as $redemption)
            <p>{{ __('promotion::packages.listing_number') }} #{{ $redemption->listing_id }} · {{ $redemption->created_at->copy()->timezone('Asia/Tehran')->format('Y/m/d H:i') }}</p>
        @empty
            <p>{{ __('promotion::packages.no_redemptions') }}</p>
        @endforelse
    </section>
    <p class="text-meta">{{ __('promotion::packages.scope') }}</p>
    <a class="button button--ghost" href="{{ route('seller-packages.index') }}">{{ __('promotion::packages.back') }}</a>
</article>
@endsection
