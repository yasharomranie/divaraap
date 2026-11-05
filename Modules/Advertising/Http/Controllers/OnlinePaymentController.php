<?php

declare(strict_types=1);

namespace Modules\Advertising\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Modules\Advertising\Http\Requests\StartOnlinePaymentRequest;
use Modules\Advertising\Models\AdvertisingInvoice;
use Modules\Advertising\Models\OnlinePaymentAttempt;
use Modules\Advertising\Services\PaymentGatewayClient;

final class OnlinePaymentController extends Controller
{
    public function start(StartOnlinePaymentRequest $request, string $invoice): RedirectResponse
    {
        $record = AdvertisingInvoice::accessibleInvoice($invoice, $request->user()->id);
        $attempt = OnlinePaymentAttempt::start($record, $request->user(), $request->validated('gateway'));
        if ($attempt->status !== 'pending') {
            return redirect()->route('advertising.invoices.show', $record)->withErrors(['gateway' => __('advertising::gateway.request_failed')]);
        }

        return $this->redirectToGateway($attempt);
    }

    public function resume(Request $request, string $attempt): RedirectResponse
    {
        $record = OnlinePaymentAttempt::query()->findOrFail($attempt);
        $record->assertOwned($request->user());
        $invoice = AdvertisingInvoice::query()->findOrFail($record->invoice_id);
        abort_unless($record->status === 'pending' && $invoice->collectionOpen() && $invoice->balance() === $record->amount, 422);

        return $this->redirectToGateway($record);
    }

    public function check(Request $request, string $attempt): RedirectResponse
    {
        $record = OnlinePaymentAttempt::query()->findOrFail($attempt);
        $record->assertOwned($request->user());
        $record->check();

        return redirect()->route('advertising.invoices.show', $record->invoice_id);
    }

    public function callback(Request $request, string $attempt, string $token): Response
    {
        $record = OnlinePaymentAttempt::query()->findOrFail($attempt);
        $authority = $request->query($record->gateway === 'zarinpal' ? 'Authority' : 'trackId');
        abort_unless($record->matchesCallback($token, $authority), 404);
        $checked = $record->check();

        return response()->view('advertising::invoices.payment-result', ['state' => $checked->status, 'sandbox' => $checked->sandbox])
            ->header('Cache-Control', 'private, no-store')->header('Referrer-Policy', 'no-referrer')->header('X-Robots-Tag', 'noindex, nofollow');
    }

    private function redirectToGateway(OnlinePaymentAttempt $attempt): RedirectResponse
    {
        return redirect()->away(app(PaymentGatewayClient::class)->startUrl($attempt))
            ->header('Cache-Control', 'private, no-store')->header('Referrer-Policy', 'origin');
    }
}
