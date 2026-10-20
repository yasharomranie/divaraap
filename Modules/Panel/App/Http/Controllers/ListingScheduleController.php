<?php

declare(strict_types=1);

namespace Modules\Panel\App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Modules\Listing\Models\Listing;

final class ListingScheduleController extends Controller
{
    public function show(Request $request, Listing $listing)
    {
        $listing->assertOwnedBy($request->user());

        return response()->view('panel::listing-schedule', ['listing' => $listing])->header('Cache-Control', 'private, no-store')->header('X-Robots-Tag', 'noindex, nofollow');
    }

    public function save(Request $request, Listing $listing)
    {
        $listing->assertOwnedBy($request->user());
        $data = $request->validate(['publish_at' => ['required', 'date_format:Y-m-d\TH:i'], 'version' => ['required', 'string', 'size:64', 'regex:/^[a-f0-9]+$/']]);
        $time = Carbon::createFromFormat('!Y-m-d\TH:i', $data['publish_at'], 'Asia/Tehran')->setTimezone(config('app.timezone'));
        $listing->setScheduleFor($request->user(), $time, $data['version']);

        return redirect()->route('panel.listings.schedule', $listing)->with('success', __('listing::schedule.saved'));
    }

    public function cancel(Request $request, Listing $listing)
    {
        $listing->assertOwnedBy($request->user());
        $data = $request->validate(['version' => ['required', 'string', 'size:64', 'regex:/^[a-f0-9]+$/']]);
        $listing->setScheduleFor($request->user(), null, $data['version']);

        return redirect()->route('panel.listings.schedule', $listing)->with('success', __('listing::schedule.cancelled'));
    }
}
