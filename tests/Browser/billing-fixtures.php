<?php

declare(strict_types=1);

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Modules\Advertising\Models\AdvertisingInvoice;
use Modules\Advertising\Models\AdvertisingPayment;
use Modules\User\App\Models\User;
use Spatie\Activitylog\Models\Activity;

require dirname(__DIR__, 2).'/vendor/autoload.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
if (app()->environment('production')) {
    throw new RuntimeException('Browser fixtures require development.');
}
$path = storage_path('framework/billing-review.json');
$action = $argv[1] ?? '';
Auth::login(User::where('email', 'a@a.com')->firstOrFail());
if ($action !== '') {
    $state = json_decode(file_get_contents($path), true, 32, JSON_THROW_ON_ERROR);
    if ($action === 'cleanup') {
        $ids = AdvertisingPayment::whereIn('invoice_id', $state['invoices'])->pluck('id')->all();
        DB::table('advertising_payments')->whereIn('id', $ids)->delete();
        DB::table('advertising_invoices')->whereIn('id', $state['invoices'])->delete();
        User::withTrashed()->whereKey($state['customer'])->forceDelete();
        foreach ([AdvertisingPayment::class => $ids, AdvertisingInvoice::class => $state['invoices'], User::class => [$state['customer']]] as $type => $subjects) {
            Activity::where('subject_type', $type)->whereIn('subject_id', $subjects)->delete();
        }
        unlink($path);
        exit;
    }
    if ($action === 'verify-races') {
        if (AdvertisingPayment::where('invoice_id', $state['invoices'][1])->count() !== 1 || (int) AdvertisingPayment::where('invoice_id', $state['invoices'][2])->sum('amount') !== 600000) {
            throw new RuntimeException('Concurrent receipt invariants failed.');
        }
        echo 'Concurrent replay and balance protection verified.'.PHP_EOL;
        exit;
    }
    $same = str_starts_with($action, 'replay');
    $invoice = AdvertisingInvoice::findOrFail($state['invoices'][$same ? 1 : 2]);
    try {
        AdvertisingPayment::recordReceived($invoice, $same ? '1000000' : '600000', $same ? 'RACE-SAME-'.$invoice->id : strtoupper($action).'-'.$invoice->id);
        echo 'Recorded.'.PHP_EOL;
    } catch (ValidationException $exception) {
        echo 'Rejected: unpaid balance protected.'.PHP_EOL;
    }
    exit;
}
if (is_file($path)) {
    throw new RuntimeException('Clean previous fixtures first.');
}
$customer = User::factory()->create(['name' => 'مشتری آزمایش مالی', 'email' => 'billing-'.Str::uuid().'@example.test', 'status' => 'active', 'password' => 'Browser-test-123!']);
$customer->syncRoles([]);
$ids = [];
foreach (range(1, 3) as $i) {
    $ids[] = AdvertisingInvoice::create(['user_id' => $customer->id, 'description' => 'خدمات تبلیغاتی آزمایش '.$i, 'amount' => '1000000'])->id;
}
file_put_contents($path, json_encode(['customer' => $customer->id, 'email' => $customer->email, 'invoices' => $ids], JSON_THROW_ON_ERROR));
