@php
    $creatingListing = request()->routeIs('panel.listings.create');
    $inInbox = request()->routeIs('panel.inbox.*');
    $mobileItems = [
        ['key' => 'home', 'label' => __('site::messages.home'), 'icon' => 'home', 'url' => route('home'), 'active' => request()->routeIs('home')],
        ['key' => 'browse', 'label' => __('site::messages.browse'), 'icon' => 'grid', 'url' => route('listings.index'), 'active' => request()->routeIs('listings.*', 'categories.*')],
        ['key' => 'create', 'label' => __('site::messages.sell'), 'icon' => 'plus', 'url' => $sellRoute, 'active' => $creatingListing],
        ['key' => 'inbox', 'label' => __('site::messages.inbox'), 'icon' => 'mail', 'url' => $isAuthenticated ? route('panel.inbox.index') : route('login'), 'active' => $inInbox],
        ['key' => 'account', 'label' => __('site::mobile.my_account'), 'icon' => 'user', 'url' => $isAuthenticated ? route('panel.index') : route('login'), 'active' => !$creatingListing && !$inInbox && request()->routeIs('panel.*', 'favorites.*', 'login', 'register')],
    ];
@endphp
<nav class="mobile-tabs" aria-label="{{ __('site::mobile.navigation') }}" data-mobile-navigation>
    @foreach($mobileItems as $item)
        <a href="{{ $item['url'] }}" class="mobile-tabs__item {{ $item['key'] === 'create' ? 'mobile-tabs__item--create' : '' }}" @if($item['active']) aria-current="page" @endif>
            <span class="mobile-tabs__icon">
                <x-ui.icon :name="$item['icon']"/>
                @if($item['key'] === 'inbox' && $isAuthenticated)
                    <span class="mobile-tabs__badge {{ $messageCount > 0 ? '' : 'is-hidden' }}" data-inbox-badge="{{ $messageCount }}">{{ $badge($messageCount) }}</span>
                @endif
            </span>
            <span>{{ $item['label'] }}</span>
        </a>
    @endforeach
</nav>
