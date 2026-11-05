<?php

declare(strict_types=1);

namespace Modules\Advertising\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Str;
use Modules\Advertising\Http\Requests\RefundRequest;
use Modules\Advertising\Models\AdvertisingInvoice;
use Modules\Advertising\Models\AdvertisingPayment;
use Modules\Advertising\Models\AdvertisingPaymentAdjustment;
use Modules\Advertising\Models\AdvertisingRefundRequest;

final class RefundController extends Controller
{
    public function create(Request $request, string $invoice, string $payment): Response
    {
        abort_unless((string) $request->user()->status === 'active', 403);
        $record = AdvertisingInvoice::accessibleInvoice($invoice, $request->user()->id);
        $source = AdvertisingPayment::query()->where('invoice_id', $record->id)->findOrFail($payment);

        return response()->view('advertising::invoices.refund-create', ['invoice' => $record, 'payment' => $source, 'available' => AdvertisingPaymentAdjustment::availableForPayment($source), 'formKey' => (string) Str::uuid()])
            ->header('Cache-Control', 'private, no-store')->header('X-Robots-Tag', 'noindex, nofollow');
    }

    public function store(RefundRequest $request, string $invoice, string $payment): RedirectResponse
    {
        $record = AdvertisingInvoice::accessibleInvoice($invoice, $request->user()->id);
        $source = AdvertisingPayment::query()->where('invoice_id', $record->id)->findOrFail($payment);
        AdvertisingRefundRequest::requestFor($source, $request->validated('amount'), $request->validated('reason'), $request->validated('form_key'));

        return redirect()->route('advertising.invoices.show', $record)->with('success', __('advertising::refunds.saved'));
    }

    public function correct(RefundRequest $request, string $invoice, string $payment): RedirectResponse
    {
        $record = AdvertisingInvoice::accessibleInvoice($invoice, $request->user()->id);
        $source = AdvertisingPayment::query()->where('invoice_id', $record->id)->findOrFail($payment);
        AdvertisingPaymentAdjustment::correct($source, $request->validated('amount'), $request->validated('reason'), $request->validated('operation_key'), $request->validated('direction') ?? 'decrease');

        return redirect()->route('advertising.invoices.show', $record)->with('success', __('advertising::refunds.corrected'));
    }

    public function withdraw(RefundRequest $request, string $refund): RedirectResponse
    {
        $record = AdvertisingRefundRequest::query()->where('user_id', $request->user()->id)->findOrFail($refund);
        $record->withdraw((int) $request->validated('version'));

        return redirect()->route('advertising.invoices.show', $record->invoice_id)->with('success', __('advertising::refunds.withdrawn_notice'));
    }
}
