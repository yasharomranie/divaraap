<?php

declare(strict_types=1);

namespace Modules\Advertising\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Modules\Advertising\Models\Banner;

final class VideoCaptionController
{
    public function __invoke(Request $request, int $banner): Response
    {
        return response(Banner::videoCaptionContent($banner, $request->query('version')))
            ->header('Content-Type', 'text/vtt; charset=UTF-8')
            ->header('Cache-Control', 'private, no-store')
            ->header('X-Content-Type-Options', 'nosniff');
    }
}
