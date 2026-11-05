<?php

declare(strict_types=1);

namespace Modules\Advertising\Services;

use Illuminate\Support\Facades\Http;
use Modules\Advertising\Models\OnlinePaymentAttempt;
use Throwable;

class PaymentGatewayClient
{
    public function request(OnlinePaymentAttempt $attempt, string $callback): array
    {
        $payload = $attempt->gateway === 'zarinpal'
            ? ['merchant_id' => $attempt->merchant, 'amount' => $attempt->amount, 'currency' => 'IRR', 'description' => 'Advertising invoice '.$attempt->id, 'callback_url' => $callback]
            : ['merchant' => $attempt->merchant, 'amount' => $attempt->amount, 'description' => 'Advertising invoice '.$attempt->id, 'orderId' => $attempt->id, 'callbackUrl' => $callback];
        $data = $this->post($this->endpoint($attempt, 'request'), $payload);
        $code = $attempt->gateway === 'zarinpal' ? data_get($data, 'data.code') : data_get($data, 'result');
        $authority = $attempt->gateway === 'zarinpal' ? data_get($data, 'data.authority') : data_get($data, 'trackId');
        if ($code === 100 && $this->validAuthority($attempt->gateway, $authority)) {
            return ['authority' => (string) $authority, 'code' => '100'];
        }

        return ['authority' => null, 'code' => $this->code($code ?? data_get($data, 'errors.code'))];
    }

    public function verify(OnlinePaymentAttempt $attempt): array
    {
        if ($attempt->gateway === 'zarinpal') {
            $data = $this->post($this->endpoint($attempt, 'verify'), ['merchant_id' => $attempt->merchant, 'amount' => $attempt->amount, 'authority' => $attempt->authority]);
            $code = data_get($data, 'data.code') ?? data_get($data, 'errors.code');
            $reference = data_get($data, 'data.ref_id');
            if (in_array($code, [100, 101], true) && $this->validReference($reference)) {
                return ['state' => 'verified', 'reference' => (string) $reference, 'code' => (string) $code];
            }

            return ['state' => in_array($code, [-11, -51, -54], true) ? 'failed' : 'pending', 'reference' => null, 'code' => $this->code($code)];
        }
        $payload = ['merchant' => $attempt->merchant, 'trackId' => (int) $attempt->authority];
        $data = $this->post($this->endpoint($attempt, 'verify'), $payload);
        if (data_get($data, 'result') === 201) {
            $data = $this->post($this->endpoint($attempt, 'inquiry'), $payload);
        }
        $code = data_get($data, 'result');
        if ($code === 100) {
            if (data_get($data, 'status') === 1 && data_get($data, 'amount') === $attempt->amount
                && data_get($data, 'orderId') === $attempt->id && $this->validReference(data_get($data, 'refNumber'))) {
                return ['state' => 'verified', 'reference' => (string) $data['refNumber'], 'code' => '100'];
            }

            return ['state' => 'needs_review', 'reference' => null, 'code' => 'mismatch'];
        }

        return ['state' => $code === 202 ? 'failed' : 'pending', 'reference' => null, 'code' => $this->code($code)];
    }

    public function startUrl(OnlinePaymentAttempt $attempt): string
    {
        abort_unless($this->validAuthority($attempt->gateway, $attempt->authority), 422);
        if ($attempt->gateway === 'zibal') {
            return 'https://gateway.zibal.ir/start/'.$attempt->authority;
        }

        return ($attempt->sandbox ? 'https://sandbox.zarinpal.com' : 'https://www.zarinpal.com').'/pg/StartPay/'.$attempt->authority;
    }

    private function endpoint(OnlinePaymentAttempt $attempt, string $action): string
    {
        return $attempt->gateway === 'zibal' ? 'https://gateway.zibal.ir/v1/'.$action
            : ($attempt->sandbox ? 'https://sandbox.zarinpal.com' : 'https://api.zarinpal.com').'/pg/v4/payment/'.$action.'.json';
    }

    private function post(string $url, array $payload): ?array
    {
        try {
            $response = Http::acceptJson()->asJson()->connectTimeout(3)->timeout(10)->withOptions(['allow_redirects' => false])->post($url, $payload);
            $data = $response->json();

            return $response->successful() && is_array($data) ? $data : null;
        } catch (Throwable) {
            return null;
        }
    }

    private function validAuthority(string $gateway, mixed $value): bool
    {
        return is_scalar($value) && ($gateway === 'zibal'
            ? ctype_digit((string) $value) && (int) $value > 0 && (string) (int) $value === (string) $value
            : preg_match('/\A[A-Za-z0-9]{20,80}\z/', (string) $value) === 1);
    }

    private function validReference(mixed $value): bool
    {
        return (is_string($value) || is_int($value)) && preg_match('/\A[1-9][0-9]{0,49}\z/', (string) $value) === 1;
    }

    private function code(mixed $code): string
    {
        return is_int($code) ? (string) $code : 'unavailable';
    }
}
