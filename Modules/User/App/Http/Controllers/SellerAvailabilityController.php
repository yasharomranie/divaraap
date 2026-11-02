<?php

declare(strict_types=1);

namespace Modules\User\App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Modules\User\App\Http\Requests\SellerAvailabilityRequest;
use Modules\User\App\Models\Profile;

final class SellerAvailabilityController extends Controller
{
    public function edit(Request $request): Response
    {
        return response()->view('user::availability.edit', ['availability' => Profile::availabilityFor($request->user())])
            ->header('Cache-Control', 'private, no-store')->header('X-Robots-Tag', 'noindex, nofollow');
    }

    public function update(SellerAvailabilityRequest $request): RedirectResponse
    {
        Profile::saveAvailabilityFor($request->user(), $request->validated());

        return redirect()->route('seller-availability.edit')->with('success', __('user::availability.saved'));
    }
}
