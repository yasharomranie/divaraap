@php
    $banners = app(\Modules\Advertising\Support\BannerRenderer::class)->at($placementKey, $position ?? 0, $advertisingContext ?? []);
@endphp
@foreach($banners as $device => $banner)
    @php($trackingToken = request()->hasSession() && app(\Modules\Admin\App\Settings\OperationsSettings::class)->telemetry_enabled ? \Modules\Advertising\Support\AdvertisingToken::issue($banner, $placementKey, $device, $advertisingContext ?? []) : null)
    @include('advertising::creative', ['isPreview' => false])
@endforeach
