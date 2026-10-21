<?php

declare(strict_types=1);

namespace Modules\Promotion\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Str;
use Modules\Promotion\Models\SellerPackage;
use Modules\Promotion\Models\SellerPackagePurchase;
use Modules\Promotion\Models\SellerPackageRedemption;

final class SellerPackageController extends Controller
{
    public function index(): Response
    {
        SellerPackagePurchase::assertActive();

        return $this->privateView('promotion::seller-packages.index', ['catalog' => SellerPackage::catalog(), 'purchases' => SellerPackagePurchase::forCustomer((int) auth()->id())]);
    }

    public function request(Request $request, string $package): RedirectResponse
    {
        SellerPackagePurchase::assertActive();
        $data = $request->validate(['version' => ['required', 'string', 'size:64', 'regex:/^[a-f0-9]{64}$/D']]);
        $purchase = SellerPackagePurchase::requestPackage((int) $package, $data['version']);

        return redirect()->route('seller-packages.show', $purchase)->with('status', __('promotion::packages.saved'));
    }

    public function show(string $purchase): Response
    {
        return $this->privateView('promotion::seller-packages.show', ['purchase' => SellerPackagePurchase::owned((int) $purchase), 'listings' => SellerPackageRedemption::listingOptions(), 'requestKey' => (string) Str::uuid()]);
    }

    public function redeem(Request $request, string $purchase): RedirectResponse
    {
        SellerPackagePurchase::assertActive();
        $data = $request->validate(['listing_id' => ['required', 'integer', 'min:1'], 'request_key' => ['required', 'uuid']]);
        SellerPackageRedemption::consume((int) $purchase, (int) $data['listing_id'], $data['request_key']);

        return redirect()->route('seller-packages.show', $purchase)->with('status', __('promotion::packages.redeemed'));
    }

    private function privateView(string $view, array $data): Response
    {
        return response()->view($view, $data)->header('Cache-Control', 'private, no-store')->header('X-Robots-Tag', 'noindex, nofollow');
    }
}
