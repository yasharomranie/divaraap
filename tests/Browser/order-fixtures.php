<?php

declare(strict_types=1);

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
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
$path = storage_path('framework/order-review.json');
$action = $argv[1] ?? '';
if ($action !== '') {
    $state = json_decode(file_get_contents($path), true, 32, JSON_THROW_ON_ERROR);
    if ($action === 'cleanup') {
        $orders = AdvertisingOrder::where('user_id', $state['customer'])->get();
        $invoices = $orders->pluck('invoice_id')->filter()->all();
        $payments = AdvertisingPayment::whereIn('invoice_id', $invoices)->pluck('id')->all();
        DB::table('advertising_orders')->whereIn('id', $orders->modelKeys())->delete();
        DB::table('advertising_payments')->whereIn('id', $payments)->delete();
        DB::table('advertising_invoices')->whereIn('id', $invoices)->delete();
        DB::table('advertising_campaigns')->where('id', $state['campaign'])->delete();
        User::whereKey($state['customer'])->forceDelete();
        foreach ([AdvertisingOrder::class => $orders->modelKeys(), AdvertisingInvoice::class => $invoices, AdvertisingPayment::class => $payments, Campaign::class => [$state['campaign']], User::class => [$state['customer']]] as $type => $ids) {
            Activity::where('subject_type', $type)->whereIn('subject_id', $ids)->delete();
        }
        unlink($path);
        exit;
    }
    if (str_starts_with($action, 'submit')) {
        Auth::login(User::findOrFail($state['customer']));
        $order = AdvertisingOrder::submit(['request_key' => $state['request_key'], 'title' => 'Concurrent order fixture', 'brief' => 'Race fixture', 'goal' => 'traffic', 'placement' => 'home-top', 'duration_days' => 7]);
        echo 'Submitted '.$order->id.PHP_EOL;
        exit;
    }
    Auth::login(User::where('email', 'a@a.com')->firstOrFail());
    if (str_starts_with($action, 'quote')) {
        AdvertisingOrder::where('user_id', $state['customer'])->where('request_key', $state['request_key'])->firstOrFail()->quote('500000', 'Concurrent quote', $state['campaign']);
        echo 'Quoted.'.PHP_EOL;
        exit;
    }
    if ($action === 'verify') {
        $orders = AdvertisingOrder::where('user_id', $state['customer'])->where('request_key', $state['request_key'])->get();
        if ($orders->count() !== 1 || ! $orders->first()->invoice_id || AdvertisingInvoice::where('user_id', $state['customer'])->count() !== 1) {
            throw new RuntimeException('Concurrent order invariants failed');
        }
        echo 'Verified concurrent submission and single invoice.'.PHP_EOL;
        exit;
    }
    if ($action === 'settle') {
        $order = AdvertisingOrder::where('user_id', $state['customer'])->where('title', 'درخواست آزمایش مرورگر')->firstOrFail();
        AdvertisingPayment::recordReceived($order->invoice, '1000000', 'ORDER-BROWSER-'.$order->id);
        echo 'Settled.'.PHP_EOL;
        exit;
    }
    throw new RuntimeException('Unknown fixture action');
}
if (is_file($path)) {
    throw new RuntimeException('Clean previous fixtures first.');
}
$customer = User::factory()->create(['name' => 'مشتری سفارش آزمایش', 'email' => 'order-'.Str::uuid().'@example.test', 'status' => 'active', 'password' => 'Browser-test-123!']);
$customer->syncRoles([]);
$campaign = Campaign::create(['title' => 'Order browser campaign', 'is_active' => false]);
file_put_contents($path, json_encode(['customer' => $customer->id, 'email' => $customer->email, 'campaign' => $campaign->id, 'request_key' => (string) Str::uuid()], JSON_THROW_ON_ERROR));
