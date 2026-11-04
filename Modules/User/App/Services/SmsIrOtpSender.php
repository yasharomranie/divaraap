<?php

declare(strict_types=1);

namespace Modules\User\App\Services;

use Illuminate\Support\Facades\Http;
use Modules\Site\App\Settings\AuthenticationSettings;
use Throwable;

class SmsIrOtpSender
{
    public function send(string $phone, string $code): bool
    {
        $settings = app(AuthenticationSettings::class);
        try {
            $response = Http::acceptJson()->asJson()->withHeaders(['X-API-KEY' => $settings->smsir_api_key])
                ->connectTimeout(3)->timeout(10)->withOptions(['allow_redirects' => false])
                ->post('https://api.sms.ir/v1/send/verify', [
                    'mobile' => '0'.substr($phone, 3),
                    'templateId' => $settings->smsir_template_id,
                    'parameters' => [['name' => $settings->smsir_parameter, 'value' => $code]],
                ]);

            return $response->successful() && $response->json('status') === 1
                && is_numeric($response->json('data.messageId')) && (int) $response->json('data.messageId') > 0;
        } catch (Throwable) {
            return false;
        }
    }
}
