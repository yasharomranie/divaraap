<?php

declare(strict_types=1);

namespace Modules\Advertising\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Str;
use Modules\Advertising\Http\Requests\AdvertisingOrderRequest;
use Modules\Advertising\Models\AdvertisingOrder;

final class AdvertisingOrderController extends Controller
{
    public function index(): Response
    {
        AdvertisingOrder::assertActive();

        return $this->privateView('advertising::orders.index', ['orders' => AdvertisingOrder::forCustomer((int) auth()->id())]);
    }

    public function create(): Response
    {
        AdvertisingOrder::assertActive();

        return $this->privateView('advertising::orders.create', ['placements' => AdvertisingOrder::placementOptions(), 'requestKey' => (string) Str::uuid()]);
    }

    public function store(AdvertisingOrderRequest $request): RedirectResponse
    {
        $order = AdvertisingOrder::submit($request->validated());

        return redirect()->route('advertising.orders.show', $order)->with('status', __('advertising::orders.saved'));
    }

    public function show(string $order): Response
    {
        return $this->privateView('advertising::orders.show', ['order' => AdvertisingOrder::accessible($order)]);
    }

    public function accept(string $order): RedirectResponse
    {
        $record = AdvertisingOrder::accessible($order);
        $record->customerAction('accept');

        return redirect()->route('advertising.orders.show', $record)->with('status', __('advertising::orders.saved'));
    }

    public function cancel(string $order): RedirectResponse
    {
        $record = AdvertisingOrder::accessible($order);
        $record->customerAction('cancel');

        return redirect()->route('advertising.orders.show', $record)->with('status', __('advertising::orders.saved'));
    }

    private function privateView(string $view, array $data): Response
    {
        return response()->view($view, $data)->header('Cache-Control', 'private, no-store')->header('X-Robots-Tag', 'noindex, nofollow');
    }
}
