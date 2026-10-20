<?php

declare(strict_types=1);

namespace Modules\Advertising\Http\Controllers;

use Illuminate\Http\Response;
use Modules\Advertising\Http\Requests\AdvertisingEventRequest;
use Modules\Advertising\Models\AdvertisingEvent;

final class AdvertisingEventController
{
    public function __invoke(AdvertisingEventRequest $request): Response
    {
        try {
            $data = $request->validated();
            AdvertisingEvent::record($data['token'], $data['kind'], $data['device'], isset($data['listing']) ? (int) $data['listing'] : null, $data['choice'] ?? null);
        } catch (\Throwable $exception) {
            report($exception);
        }

        return response()->noContent()->header('Cache-Control', 'no-store');
    }
}
