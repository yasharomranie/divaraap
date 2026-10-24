<?php

declare(strict_types=1);

namespace Modules\Advertising\Support;

use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;
use Modules\Advertising\Models\Banner;
use Modules\Location\Models\City;

final class AdvertisingToken
{
    public static function sessionKey(): string
    {
        return hash_hmac('sha256', request()->session()->getId(), config('app.key'));
    }

    public static function issue(Banner $banner, string $placement, string $device, array $context): string
    {
        if (isset($context['city_name'], $context['country_name']) && ! isset($context['city_id'])) {
            $context['city_id'] = City::advertisingIdForLocation($context['city_name'], $context['country_name']);
        }

        return Crypt::encryptString(json_encode([
            'experiment' => $banner->experimentExposure,
            'banner' => $banner->id, 'campaign' => $banner->campaign_id,
            'service' => $banner->creative_format === 'service' ? (int) $banner->service_listing_id : null,
            'sponsorship' => $banner->sponsorshipSignature(),
            'shop_signature' => $banner->shopSignature(),
            'deadline' => $banner->deadlineSignature(),
            'video' => $banner->videoSignature(),
            'guide' => $banner->guideSignature(),
            'filters' => array_keys($banner->preparedFilters()),
            'listings' => array_keys($banner->advertisedListings()),
            'placement' => $placement, 'device' => $device,
            'context' => ['category_id' => $context['category_id'] ?? null, 'city_id' => $context['city_id'] ?? null, 'path' => request()->path()],
            'pricing' => ['impression' => (int) ($banner->campaign?->impression_cost ?? 0), 'click' => (int) ($banner->campaign?->click_cost ?? 0)],
            'frequency' => $banner->frequencyLimits(), 'visitor' => $banner->frequencyLimits() === [] ? null : app(AdvertisingFrequency::class)->visitor(),
            'session' => self::sessionKey(), 'nonce' => Str::random(32),
            'issued' => now()->timestamp, 'expires' => now()->addHours(2)->timestamp,
        ], JSON_THROW_ON_ERROR));
    }

    public static function read(string $token): ?array
    {
        try {
            $data = json_decode(Crypt::decryptString($token), true, flags: JSON_THROW_ON_ERROR);
            if (! is_array($data) || ($data['expires'] ?? 0) <= now()->timestamp || ! hash_equals(self::sessionKey(), (string) ($data['session'] ?? ''))) {
                return null;
            }

            return $data;
        } catch (\Throwable) {
            return null;
        }
    }
}
