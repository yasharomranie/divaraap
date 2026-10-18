<?php

declare(strict_types=1);

namespace Modules\Advertising\Http\Controllers;

use Illuminate\Http\Response;
use Modules\Advertising\Http\Requests\BannerPreviewRequest;
use Modules\Advertising\Models\Banner;
use Modules\Advertising\Models\BannerPlacement;

final class BannerPreviewController
{
    public function __invoke(BannerPreviewRequest $request, int $banner): Response
    {
        $data = $request->validated();
        $device = $data['device'] ?? 'mobile';
        $record = Banner::forPreview($banner);
        $placementKey = $data['placement'] ?? 'home-top';
        $placement = BannerPlacement::forPreview($placementKey);

        return response()->view('advertising::preview-frame', [
            'banner' => $record, 'placementKey' => $placementKey, 'placement' => $placement,
            'device' => $device, 'visible' => in_array($record->device, ['both', $device], true) && in_array($placement->device, ['both', $device], true),
            'isPreview' => true, 'trackingToken' => null,
        ])->header('Cache-Control', 'private, no-store')->header('X-Robots-Tag', 'noindex, nofollow')->header('X-Frame-Options', 'SAMEORIGIN');
    }
}
