<?php

declare(strict_types=1);

namespace Modules\Advertising\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;
use Modules\Advertising\Http\Requests\AdvertisingBundleRequest;
use Modules\Advertising\Models\AdvertisingBundle;
use Modules\Advertising\Models\AdvertisingOrder;

final class AdvertisingBundleController extends Controller
{
    public function index(): Response
    {
        AdvertisingOrder::assertActive();

        return response()->view('advertising::bundles.catalog', ['catalog' => AdvertisingBundle::catalog()])->header('Cache-Control', 'private, no-store')->header('X-Robots-Tag', 'noindex, nofollow');
    }

    public function store(AdvertisingBundleRequest $request, string $bundle): RedirectResponse
    {
        $data = $request->validated();
        $order = AdvertisingOrder::submitBundle((int) $bundle, $data['version'], $data);

        return redirect()->route('advertising.orders.show', $order)->with('status', __('advertising::orders.saved'));
    }
}
