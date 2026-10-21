<?php

declare(strict_types=1);

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Modules\Advertising\Models\AdvertisingInvoice;
use Modules\Advertising\Models\AdvertisingPayment;
use Modules\Category\Models\Category;
use Modules\Listing\Models\Listing;
use Modules\Promotion\Models\PromotionOrder;
use Modules\Promotion\Models\PromotionPlan;
use Modules\Promotion\Models\SellerPackage;
use Modules\Promotion\Models\SellerPackagePurchase;
use Modules\Promotion\Models\SellerPackageRedemption;
use Modules\User\App\Models\User;
use Spatie\Activitylog\Models\Activity;

require dirname(__DIR__, 2).'/vendor/autoload.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
if (app()->environment('production')) {
    throw new RuntimeException('Browser fixtures require development.');
}
$path = storage_path('framework/seller-package-review.json');
$action = $argv[1] ?? '';
Auth::login(User::where('email', 'a@a.com')->firstOrFail());
if ($action !== '') {
    $state = json_decode(file_get_contents($path), true, 32, JSON_THROW_ON_ERROR);
    $purchases = SellerPackagePurchase::where('user_id', $state['seller'])->get();
    if ($action === 'cleanup') {
        $redemptions = SellerPackageRedemption::whereIn('purchase_id', $purchases->modelKeys())->get();
        $promotions = PromotionOrder::whereIn('listing_id', $state['listings'])->pluck('id')->all();
        $invoices = $purchases->pluck('invoice_id')->filter()->all();
        $payments = AdvertisingPayment::whereIn('invoice_id', $invoices)->pluck('id')->all();
        DB::table('seller_package_redemptions')->whereIn('id', $redemptions->modelKeys())->delete();
        DB::table('promotion_orders')->whereIn('id', $promotions)->delete();
        DB::table('seller_package_purchases')->whereIn('id', $purchases->modelKeys())->delete();
        DB::table('advertising_payments')->whereIn('id', $payments)->delete();
        DB::table('advertising_invoices')->whereIn('id', $invoices)->delete();
        DB::table('seller_packages')->where('id', $state['package'])->delete();
        DB::table('promotion_plans')->where('id', $state['plan'])->delete();
        Listing::withTrashed()->whereIn('id', $state['listings'])->forceDelete();
        User::whereKey($state['seller'])->forceDelete();
        foreach ([SellerPackage::class => [$state['package']], SellerPackagePurchase::class => $purchases->modelKeys(), Listing::class => $state['listings'], User::class => [$state['seller']], AdvertisingInvoice::class => $invoices, AdvertisingPayment::class => $payments] as $type => $ids) {
            Activity::where('subject_type', $type)->whereIn('subject_id', $ids)->delete();
        }
        unlink($path);
        exit;
    }
    if ($action === 'request') {
        Auth::login(User::findOrFail($state['seller']));
        $package = SellerPackage::findOrFail($state['package']);
        SellerPackagePurchase::requestPackage($package->id, SellerPackage::version($package->offer()));
        echo 'Requested.'.PHP_EOL;
        exit;
    }
    $purchase = $purchases->firstOrFail();
    if ($action === 'settle') {
        AdvertisingPayment::recordReceived($purchase->invoice, '1000000', 'PACKAGE-BROWSER-'.$purchase->id);
        echo 'Settled.'.PHP_EOL;
        exit;
    }
    if ($action === 'prepare-race') {
        $purchase->review('invoice');
        AdvertisingPayment::recordReceived($purchase->invoice, '1000000', 'PACKAGE-RACE-'.$purchase->id);
        $purchase->review('activate');
        echo 'Prepared.'.PHP_EOL;
        exit;
    }
    if ($action === 'verify') {
        if ($purchase->used_credits !== 1 || $purchase->redemptions()->count() !== 1) {
            throw new RuntimeException('Concurrent quota consumption failed');
        }
        echo 'Verified one credit consumed under concurrent requests.'.PHP_EOL;
        exit;
    }
    Auth::login(User::findOrFail($state['seller']));
    try {
        SellerPackageRedemption::consume($purchase->id, $state['listings'][$action === 'race-a' ? 0 : 1], (string) Str::uuid());
        echo 'Consumed.'.PHP_EOL;
    } catch (ValidationException $exception) {
        echo 'Quota rejected.'.PHP_EOL;
    }
    exit;
}
if (is_file($path)) {
    throw new RuntimeException('Clean previous fixtures first.');
}
$seller = User::factory()->create(['name' => 'فروشنده بسته آزمایش', 'email' => 'package-'.Str::uuid().'@example.test', 'password' => 'Browser-test-123!', 'status' => 'active']);
$seller->syncRoles([]);
$plan = PromotionPlan::create(['name' => 'ارتقای سه روزه آزمایش', 'slug' => (string) Str::uuid(), 'price' => 100, 'currency' => 'IRR', 'duration_days' => 3, 'is_active' => true]);
$package = SellerPackage::create(['name' => 'بسته حرفه‌ای آزمایش', 'description' => 'یک اعتبار ارتقای آگهی', 'promotion_plan_id' => $plan->id, 'price' => 1000000, 'credits' => 1, 'validity_days' => 30, 'is_active' => true]);
$listings = [];
foreach (range(1, 2) as $i) {
    $listings[] = Listing::unguarded(fn () => Listing::create(['title' => 'آگهی بسته آزمایش '.$i, 'description' => 'توضیحات آزمایش', 'price' => 100, 'currency' => 'IRR', 'user_id' => $seller->id, 'category_id' => Category::firstOrFail()->id, 'slug' => (string) Str::uuid(), 'status' => 'active']))->id;
}
file_put_contents($path, json_encode(['seller' => $seller->id, 'email' => $seller->email, 'package' => $package->id, 'plan' => $plan->id, 'listings' => $listings], JSON_THROW_ON_ERROR));
