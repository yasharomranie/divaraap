@if(auth()->check() && auth()->user()->hasRole('admin'))
    @php
        $adminTabs = [
            ['label' => __('site::mobile.admin_home'), 'icon' => 'chart', 'url' => url('/admin'), 'active' => request()->routeIs('filament.admin.pages.dashboard')],
            ['label' => __('site::mobile.admin_listings'), 'icon' => 'tag', 'url' => url('/admin/listings'), 'active' => request()->routeIs('filament.admin.resources.listings.*')],
            ['label' => __('site::mobile.admin_site'), 'icon' => 'globe', 'url' => url('/'), 'active' => false],
            ['label' => __('site::mobile.admin_settings'), 'icon' => 'settings', 'url' => url('/admin/manage-general-settings'), 'active' => request()->routeIs('filament.admin.pages.manage-general-settings', 'filament.admin.pages.manage-appearance-settings', 'filament.admin.pages.manage-deployment')],
        ];
    @endphp
    <nav class="admin-mobile-tabs" aria-label="{{ __('site::mobile.admin_navigation') }}" x-data>
        @foreach($adminTabs as $tab)
            <a href="{{ $tab['url'] }}" @if($tab['active']) aria-current="page" @endif><x-ui.icon :name="$tab['icon']"/><span>{{ $tab['label'] }}</span></a>
        @endforeach
        <button type="button" x-on:click="$store.sidebar.open()" x-bind:aria-expanded="$store.sidebar.isOpen"><x-ui.icon name="menu"/><span>{{ __('site::mobile.admin_menu') }}</span></button>
    </nav>
@endif
