<?php

declare(strict_types=1);

namespace Modules\User\App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\User\App\Models\Profile;

final class ShopController extends Controller
{
    public function edit(Request $request)
    {
        $shop = Profile::shopFor($request->user());

        return response()->view('user::shop.edit', [
            'shop' => $shop,
            'version' => Profile::shopVersion($request->user(), $shop),
        ])->header('Cache-Control', 'private, no-store')->header('X-Robots-Tag', 'noindex, nofollow');
    }

    public function update(Request $request)
    {
        abort_unless((string) $request->user()->status === 'active', 403);
        $data = $request->validate([
            'shop_enabled' => ['required', 'boolean'],
            'shop_name' => ['required_if:shop_enabled,1', 'nullable', 'string', 'max:80'],
            'shop_description' => ['nullable', 'string', 'max:1200'],
            'shop_hours' => ['nullable', 'string', 'max:160'],
            'version' => ['required', 'string', 'size:64', 'regex:/^[a-f0-9]+$/'],
        ]);
        Profile::saveShopFor($request->user(), $data, $data['version']);

        return redirect()->route('shop.edit')->with('success', __('user::shop.saved'));
    }
}
