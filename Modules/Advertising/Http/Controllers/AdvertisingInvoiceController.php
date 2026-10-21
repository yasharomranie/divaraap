<?php

declare(strict_types=1);

namespace Modules\Advertising\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Modules\Advertising\Models\AdvertisingInvoice;

final class AdvertisingInvoiceController extends Controller
{
    public function index(Request $request): Response
    {
        abort_unless((string) $request->user()->status === 'active', 403);
        $invoices = AdvertisingInvoice::invoicesForCustomer((int) $request->user()->id);

        return $this->privateView('advertising::invoices.index', compact('invoices'));
    }

    public function show(Request $request, string $invoice): Response
    {
        abort_unless((string) $request->user()->status === 'active', 403);
        $record = AdvertisingInvoice::accessibleInvoice($invoice, (int) $request->user()->id);

        return $this->privateView('advertising::invoices.show', ['invoice' => $record]);
    }

    private function privateView(string $view, array $data): Response
    {
        return response()->view($view, $data)->header('Cache-Control', 'private, no-store')->header('X-Robots-Tag', 'noindex, nofollow');
    }
}
