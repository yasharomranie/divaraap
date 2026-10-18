<!doctype html>
<html lang="{{ app()->getLocale() }}" dir="{{ app()->getLocale() === 'fa' ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex,nofollow">
    <title>{{ __('advertising::preview.title') }} — {{ $banner->title }}</title>
    <link rel="stylesheet" href="{{ asset('assets/fonts/vazirmatn/font.css') }}">
    @vite(['resources/css/app.css'])
    <style id="site-appearance">{!! \App\Support\AppearanceTheme::css(\App\Support\AppearanceTheme::current()) !!}</style>
    <style>
        body{margin:0;background:var(--appearance-page);color:var(--appearance-text)}.preview-content{max-width:1120px;margin:auto;padding:16px;display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:16px}.preview-note{grid-column:1/-1;font-size:13px;line-height:1.8;margin:0;overflow-wrap:anywhere}.preview-sample{padding:16px;border:1px solid var(--appearance-border);border-radius:12px;min-height:110px;font-size:13px}.preview-sample span{display:block;height:55px;background:var(--appearance-surface);border:1px solid var(--appearance-border);border-radius:8px;margin-bottom:10px}@media(max-width:1023px){.preview-content{grid-template-columns:repeat(2,minmax(0,1fr));padding:12px;gap:12px}}
    </style>
</head>
<body>
<main class="preview-content">
    <p class="preview-note">{{ __('advertising::admin.'.$placementKey) }} · {{ __('advertising::preview.sample_hint') }}</p>
    @if(str_ends_with($placementKey, '-feed'))
        <p class="preview-note">{{ __('advertising::preview.after', ['count' => \App\Support\PersianLocale::number($placement->first_after)]) }}</p>
        @for($index = 0; $index < min(6, $placement->first_after); $index++)<article class="preview-sample"><span aria-hidden="true"></span>{{ __('advertising::preview.sample') }}</article>@endfor
    @endif
    @if($visible)
        @include('advertising::creative')
    @else <p class="preview-note">{{ __('advertising::preview.device_mismatch') }}</p>@endif
    <p class="preview-note">{{ __('advertising::preview.publication_hint') }}</p>
</main>
</body>
</html>
