<?php

declare(strict_types=1);

namespace Modules\Advertising\Support;

use Illuminate\Support\Facades\Cookie;
use Modules\Admin\App\Settings\OperationsSettings;
use Modules\Advertising\Models\Banner;
use Modules\Advertising\Models\FrequencyCount;

final class AdvertisingFrequency
{
    public function visitor(): string
    {
        if ($visitor = request()->attributes->get('advertising.frequency.visitor')) {
            return $visitor;
        }
        $value = request()->cookie('advertising_frequency');
        if (! is_string($value) || ! preg_match('/^[a-f0-9]{64}$/', $value)) {
            $value = bin2hex(random_bytes(32));
            Cookie::queue(cookie('advertising_frequency', $value, 43200, '/', null, request()->isSecure(), true, false, 'lax'));
        }
        $visitor = hash_hmac('sha256', $value, config('app.key'));
        request()->attributes->set('advertising.frequency.visitor', $visitor);

        return $visitor;
    }

    public function allows(Banner $banner): bool
    {
        $limits = $banner->frequencyLimits();
        if ($limits === []) {
            return true;
        }
        if (! request()->hasSession() || ! app(OperationsSettings::class)->telemetry_enabled) {
            return false;
        }
        $key = 'advertising.frequency.counts.'.now()->toDateString();
        if (! request()->attributes->has($key)) {
            request()->attributes->set($key, FrequencyCount::forVisitor($this->visitor()));
        }
        $counts = request()->attributes->get($key);
        foreach ($limits as $subject => $limit) {
            if (($counts[$subject] ?? 0) >= $limit) {
                return false;
            }
        }

        return true;
    }
}
