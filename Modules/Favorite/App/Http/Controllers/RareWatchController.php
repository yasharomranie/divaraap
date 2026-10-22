<?php

declare(strict_types=1);

namespace Modules\Favorite\App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Modules\Favorite\App\Http\Requests\RareWatchRequest;
use Modules\Favorite\App\Models\BuyerAlert;
use Modules\Favorite\App\Models\RareItemWatch;
use Modules\Location\Models\City;

final class RareWatchController
{
    public function index(Request $request): Response
    {
        RareItemWatch::actor($request->user());
        BuyerAlert::refreshFor($request->user());

        return $this->privateView('favorite::rare.index', ['watches' => RareItemWatch::forOwner($request->user()), 'preferences' => BuyerAlert::preferencesFor($request->user()->id), 'categories' => RareItemWatch::categoryOptions(), 'cities' => City::wantedOptions()]);
    }

    public function create(Request $request): Response
    {
        RareItemWatch::actor($request->user());

        return $this->privateView('favorite::rare.create', ['categories' => RareItemWatch::categoryOptions(), 'cities' => City::wantedOptions()]);
    }

    public function store(RareWatchRequest $request): RedirectResponse
    {
        RareItemWatch::createFor($request->user(), $request->validated());

        return redirect()->route('rare-watches.index')->with('status', __('favorite::rare.saved'));
    }

    public function change(RareWatchRequest $request, int $watch): RedirectResponse
    {
        RareItemWatch::owned($request->user(), $watch)->changeFor($request->user(), $request->validated('action'));

        return redirect()->route('rare-watches.index')->with('status', __('favorite::rare.changed'));
    }

    private function privateView(string $view, array $data): Response
    {
        return response()->view($view, $data)->header('Cache-Control', 'private, no-store')->header('X-Robots-Tag', 'noindex, nofollow');
    }
}
