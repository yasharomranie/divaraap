@extends('panel::layouts.panel', ['panelSection' => 'seller-packages'])
@section('title', __('promotion::packages.title'))
@section('panel_content')
<section class="seller-packages stack stack--loose">
    <h1 class="title-page">{{ __('promotion::packages.title') }}</h1>
    <p>{{ __('promotion::packages.scope') }}</p>
    @if($errors->any())<div class="alert alert--critical" role="alert">@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>@endif
    <div class="seller-packages__catalog">
        @forelse($catalog as $package)
            <article class="card stack">
                <h2 class="title-section" dir="auto">{{ $package['offer']['name'] }}</h2>
                <p dir="auto">{{ $package['offer']['description'] }}</p>
                <strong>{{ number_format($package['offer']['price']) }} {{ __('advertising::billing.rial') }}</strong>
                <p>{{ __('promotion::packages.credits') }}: {{ $package['offer']['credits'] }}</p>
                <p>{{ __('promotion::packages.validity') }}: {{ $package['offer']['validity_days'] }}</p>
                <p>{{ __('promotion::packages.per_credit') }}: {{ $package['offer']['plan_name'] }} · {{ $package['offer']['promotion_days'] }} {{ __('promotion::admin.days') }}</p>
                <form method="post" action="{{ route('seller-packages.request', $package['id']) }}">
                    @csrf
                    <input type="hidden" name="version" value="{{ $package['version'] }}">
                    <button class="button button--primary" type="submit">{{ __('promotion::packages.request') }}</button>
                </form>
            </article>
        @empty
            <p>{{ __('promotion::packages.empty') }}</p>
        @endforelse
    </div>
    <h2 class="title-section">{{ __('promotion::packages.mine') }}</h2>
    @forelse($purchases as $purchase)
        <a class="card stack" href="{{ route('seller-packages.show', $purchase) }}">
            <strong dir="auto">{{ $purchase->snapshot['name'] }}</strong>
            <p>{{ __('promotion::packages.'.$purchase->displayState()) }} · {{ __('promotion::packages.remaining') }}: {{ $purchase->remaining() }}</p>
        </a>
    @empty
        <p>{{ __('promotion::packages.no_purchases') }}</p>
    @endforelse
    {{ $purchases->links() }}
</section>
@endsection
