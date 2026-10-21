<?php

declare(strict_types=1);

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Advertising\Models\AdvertisingBundle;
use Modules\Advertising\Models\AdvertisingInvoice;
use Modules\Advertising\Models\AdvertisingOrder;
use Modules\Advertising\Models\AdvertisingPayment;
use Modules\Advertising\Models\Campaign;
use Modules\User\App\Models\User;
use Spatie\Activitylog\Models\Activity;

require dirname(__DIR__, 2).'/vendor/autoload.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
if (app()->environment('production')) {
    throw new RuntimeException('Browser fixtures require development.');
}
$path = storage_path('framework/bundle-review.json');
$action = $argv[1] ?? '';
Auth::login(User::where('email', 'a@a.com')->firstOrFail());
if ($action !== '') {
    $state = json_decode(file_get_contents($path), true, 32, JSON_THROW_ON_ERROR);
    $orders = AdvertisingOrder::where('user_id', $state['customer'])->get();
    if ($action === 'cleanup') {
        $invoices = $orders->pluck('invoice_id')->filter()->all();
        $payments = AdvertisingPayment::whereIn('invoice_id', $invoices)->pluck('id')->all();
        DB::table('advertising_orders')->whereIn('id', $orders->modelKeys())->delete();
        DB::table('advertising_payments')->whereIn('id', $payments)->delete();
        DB::table('advertising_invoices')->whereIn('id', $invoices)->delete();
        DB::table('advertising_bundles')->where('id', $state['bundle'])->delete();
        DB::table('advertising_campaigns')->where('id', $state['campaign'])->delete();
        User::whereKey($state['customer'])->forceDelete();
        foreach ([AdvertisingOrder::class => $orders->modelKeys(), AdvertisingBundle::class => [$state['bundle']], AdvertisingInvoice::class => $invoices, AdvertisingPayment::class => $payments, Campaign::class => [$state['campaign']], User::class => [$state['customer']]] as $type => $ids) {
            Activity::where('subject_type', $type)->whereIn('subject_id', $ids)->delete();
        }
        unlink($path);
        exit;
    }
    if (str_starts_with($action, 'request')) {
        Auth::login(User::findOrFail($state['customer']));
        $bundle = AdvertisingBundle::findOrFail($state['bundle']);
        AdvertisingOrder::submitBundle($bundle->id, AdvertisingBundle::version($bundle->offer()), ['request_key' => $state['request_key'], 'brief' => 'Concurrent bundle fixture']);
        echo 'Requested.'.PHP_EOL;
        exit;
    }
    $order = $orders->firstOrFail();
    if ($action === 'prepare') {
        $order->quote('1000000', 'Bundle fixture terms');
        Auth::login(User::findOrFail($state['customer']));
        $order->customerAction('accept');
        Auth::login(User::where('email', 'a@a.com')->firstOrFail());
        AdvertisingPayment::recordReceived($order->invoice, '1000000', 'BUNDLE-RACE-'.$order->id);
        echo 'Prepared.'.PHP_EOL;
        exit;
    }
    if ($action === 'settle') {
        AdvertisingPayment::recordReceived($order->invoice, '1000000', 'BUNDLE-BROWSER-'.$order->id);
        echo 'Settled.'.PHP_EOL;
        exit;
    }
    if (str_starts_with($action, 'fulfill')) {
        $order->fulfillBundleItem($action === 'fulfill-a' ? 1 : 2, $state['campaign']);
        echo 'Fulfilled.'.PHP_EOL;
        exit;
    }
    if ($action === 'verify') {
        if ($orders->count() !== 1 || count($order->bundle_fulfillment) !== 2 || AdvertisingInvoice::where('user_id', $state['customer'])->count() !== 1) {
            throw new RuntimeException('Concurrent bundle invariants failed');
        }
        echo 'Verified one request/invoice and both concurrent component updates.'.PHP_EOL;
        exit;
    }
    throw new RuntimeException('Unknown fixture action');
}
if (is_file($path)) {
    throw new RuntimeException('Clean previous fixtures first.');
}
$customer = User::factory()->create(['name' => 'مشتری بسته ترکیبی آزمایش', 'email' => 'bundle-'.Str::uuid().'@example.test', 'password' => 'Browser-test-123!', 'status' => 'active']);
$customer->syncRoles([]);
$bundle = AdvertisingBundle::create(['name' => 'بسته ترکیبی آزمایش', 'description' => 'دو جایگاه با قیمت مشخص', 'price' => 1000000, 'items' => [['placement' => 'home-top', 'device' => 'both', 'duration_days' => 7], ['placement' => 'listing-details', 'device' => 'mobile', 'duration_days' => 3]], 'is_active' => true]);
$campaign = Campaign::create(['title' => 'Bundle browser campaign', 'is_active' => false]);
file_put_contents($path, json_encode(['customer' => $customer->id, 'email' => $customer->email, 'bundle' => $bundle->id, 'campaign' => $campaign->id, 'request_key' => (string) Str::uuid()], JSON_THROW_ON_ERROR));
