@extends('panel::layouts.panel', ['panelSection' => 'advertising-bundles'])
@section('title', __('advertising::bundles.title'))
@section('panel_content')
<section class="advertising-bundles stack stack--loose">
    <h1 class="title-page">{{ __('advertising::bundles.title') }}</h1>
    <p>{{ __('advertising::bundles.scope') }}</p>
    @if($errors->any())<div class="alert alert--critical" role="alert">@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>@endif
    <div class="advertising-bundles__catalog">
        @forelse($catalog as $bundle)
            <article class="card stack" data-bundle="{{ $bundle['id'] }}">
                <h2 class="title-section" dir="auto">{{ $bundle['offer']['name'] }}</h2>
                <p dir="auto">{{ $bundle['offer']['description'] }}</p>
                <strong>{{ number_format($bundle['offer']['price']) }} {{ __('advertising::billing.rial') }}</strong>
                <ul class="stack">
                    @foreach($bundle['offer']['items'] as $item)
                        <li>{{ __('advertising::admin.'.$item['placement']) }} · {{ __('advertising::admin.'.$item['device']) }} · {{ $item['duration_days'] }} {{ __('advertising::orders.days') }}</li>
                    @endforeach
                </ul>
                <form method="post" action="{{ route('advertising.bundles.store', $bundle['id']) }}" class="stack">
                    @csrf
                    <input type="hidden" name="version" value="{{ $bundle['version'] }}">
                    <input type="hidden" name="request_key" value="{{ (string) \Illuminate\Support\Str::uuid() }}">
                    <label class="field__label" for="bundle-brief-{{ $bundle['id'] }}">{{ __('advertising::orders.brief') }}</label>
                    <textarea class="input" id="bundle-brief-{{ $bundle['id'] }}" name="brief" rows="3" maxlength="2000" required>{{ old('brief') }}</textarea>
                    <button type="submit" class="button button--primary">{{ __('advertising::bundles.request') }}</button>
                </form>
            </article>
        @empty
            <p>{{ __('advertising::bundles.empty') }}</p>
        @endforelse
    </div>
    <a class="button button--secondary" href="{{ route('advertising.orders.index') }}">{{ __('advertising::orders.orders') }}</a>
</section>
@endsection
