<div class="space-y-2" data-shop-selection-preview>
@if($shop)<h3 dir="auto">{{ $shop['name'] }}</h3><p class="whitespace-pre-wrap break-words" dir="auto">{{ $shop['description'] }}</p><p dir="auto">{{ $shop['hours'] }}</p>@else<p>{{ __('user::neighborhoods.shop_changed') }}</p>@endif
</div>
