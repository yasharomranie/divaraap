<?php

declare(strict_types=1);

namespace Modules\User\App\Http\Controllers;

use Illuminate\Http\Response;
use Modules\Location\Models\City;
use Modules\User\App\Http\Requests\NeighborhoodShopRequest;
use Modules\User\App\Models\NeighborhoodShop;

final class NeighborhoodShopController
{
    public function index(NeighborhoodShopRequest $request): Response
    {
        $filters = $request->validated();

        return response()->view('user::neighborhoods.index', ['filters' => $filters, 'cities' => City::wantedOptions(), 'records' => NeighborhoodShop::browse($filters)])
            ->header('Cache-Control', 'private, no-store')->header('X-Robots-Tag', 'noindex, nofollow');
    }
}
