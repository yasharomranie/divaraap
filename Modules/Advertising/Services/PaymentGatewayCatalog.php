<?php

declare(strict_types=1);

namespace Modules\Advertising\Services;

use Modules\Site\App\Settings\PaymentGatewaySettings;

final class PaymentGatewayCatalog
{
    public const GATEWAYS = ['zarinpal', 'zibal'];

    public function snapshot(string $gateway): ?array
    {
        if (! in_array($gateway, self::GATEWAYS, true)) {
            return null;
        }
        $settings = app(PaymentGatewaySettings::class);
        $sandbox = $settings->{$gateway.'_sandbox'};
        $merchant = $gateway === 'zibal' && $sandbox ? 'zibal' : trim($settings->{$gateway.'_merchant'});
        if (! $settings->{$gateway.'_enabled'} || ! self::validMerchant($gateway, $merchant, $sandbox)) {
            return null;
        }

        return compact('gateway', 'sandbox', 'merchant');
    }

    public function options(): array
    {
        $options = [];
        foreach (self::GATEWAYS as $gateway) {
            if ($snapshot = $this->snapshot($gateway)) {
                $options[] = ['gateway' => $gateway, 'sandbox' => $snapshot['sandbox']];
            }
        }

        return $options;
    }

    public static function validMerchant(string $gateway, string $merchant, bool $sandbox): bool
    {
        return $gateway === 'zarinpal'
            ? preg_match('/\A[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12}\z/', $merchant) === 1
            : ($sandbox ? $merchant === 'zibal' : strtolower($merchant) !== 'zibal' && preg_match('/\A[A-Za-z0-9_-]{6,200}\z/', $merchant) === 1);
    }
}
