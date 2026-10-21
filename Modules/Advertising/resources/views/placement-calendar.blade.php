<x-filament-panels::page>
    <style>
        .placement-calendar{color:#1d1d1f;background:#fff;border:1px solid #d6d6db;border-radius:14px;padding:16px;min-width:0;overflow-wrap:anywhere}.placement-calendar__filters{display:flex;flex-wrap:wrap;gap:16px;margin-block:16px}.placement-calendar label{display:grid;gap:8px;min-width:min(100%,200px);flex:1}.placement-calendar input,.placement-calendar select{min-height:44px;border:1px solid #76767f;border-radius:8px;padding:8px;color:inherit;background:inherit;max-width:100%}.placement-calendar :is(input,select,a):focus-visible{outline:3px solid #1557ff;outline-offset:3px}.placement-calendar__days{display:grid;grid-template-columns:repeat(auto-fit,minmax(min(100%,240px),1fr));gap:12px}.placement-calendar__day{padding:12px;border:1px solid #d6d6db;border-radius:10px;min-width:0}.placement-calendar h2{font-size:15px;font-weight:650;margin-bottom:12px}.placement-calendar p{font-size:13px;line-height:1.8}.placement-calendar__entry{border-block-start:1px solid #d6d6db;padding-block:12px;display:grid;gap:6px}.placement-calendar a{display:inline-flex;align-items:center;min-height:44px;text-decoration:underline}.dark .placement-calendar{color:#fafafa;background:#18181b;border-color:#52525b}.dark .placement-calendar__day,.dark .placement-calendar__entry{border-color:#52525b}
    </style>
    <section class="placement-calendar">
        <p>{{ __('advertising::reservations.scope') }}</p>
        <div class="placement-calendar__filters">
            <label for="reservation-month">{{ __('advertising::reservations.month') }}<input id="reservation-month" type="month" wire:model.live="month" min="2000-01" max="2099-12" required></label>
            <label for="reservation-placement">{{ __('advertising::admin.placement') }}<select id="reservation-placement" wire:model.live="placementId"><option value="">{{ __('advertising::metrics.all') }}</option>@foreach($this->placements as $id => $label)<option value="{{ $id }}">{{ $label }}</option>@endforeach</select></label>
        </div>
        <a href="{{ \Modules\Advertising\Filament\Admin\Resources\PlacementReservationResource::getUrl('index') }}">{{ __('advertising::reservations.reservations') }}</a>
        @if($this->calendar['limited'])<p role="alert">{{ __('advertising::reservations.limited') }}</p>@endif
        <div class="placement-calendar__days">
            @forelse($this->calendar['days'] as $day)
                <section class="placement-calendar__day">
                    <h2><time datetime="{{ $day['date'] }}" dir="ltr">{{ $day['date'] }}</time></h2>
                    @forelse($day['items'] as $item)
                        <div class="placement-calendar__entry" data-reservation="{{ $item->id }}">
                            <strong dir="auto">{{ $item->title }}</strong>
                            <p>{{ $item->placement ? __('advertising::admin.'.$item->placement->key) : '#'.$item->placement_id }} · {{ __('advertising::admin.'.$item->device) }}</p>
                            <p>{{ __('advertising::reservations.starts_at') }}: <time datetime="{{ $item->starts_at->toIso8601String() }}" dir="ltr">{{ $item->starts_at->copy()->timezone('Asia/Tehran')->format('Y-m-d H:i') }}</time></p>
                            <p>{{ __('advertising::reservations.ends_at') }}: <time datetime="{{ $item->ends_at->toIso8601String() }}" dir="ltr">{{ $item->ends_at->copy()->timezone('Asia/Tehran')->format('Y-m-d H:i') }}</time></p>
                            <p>{{ __('advertising::reservations.'.$item->displayState()) }}</p>
                            @if($item->order_id)<a href="{{ route('advertising.orders.show', $item->order_id) }}">{{ __('advertising::orders.number') }} #{{ $item->order_id }}</a>@endif
                        </div>
                    @empty
                        <p>{{ __('advertising::reservations.empty_day') }}</p>
                    @endforelse
                </section>
            @empty
                <p role="alert">{{ __('advertising::reservations.invalid_month') }}</p>
            @endforelse
        </div>
    </section>
</x-filament-panels::page>
