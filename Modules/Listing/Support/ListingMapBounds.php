<?php

declare(strict_types=1);

namespace Modules\Listing\Support;

use Illuminate\Support\Facades\Validator;

final class ListingMapBounds
{
    public static function fromQuery(array $query): ?array
    {
        $values = array_intersect_key($query, array_flip(['west', 'east', 'south', 'north']));
        if ($values === []) {
            return null;
        }
        $validator = Validator::make($values, [
            'west' => ['required', 'numeric', 'between:-180,180'],
            'east' => ['required', 'numeric', 'between:-180,180'],
            'south' => ['required', 'numeric', 'between:-85.05112878,85.05112878'],
            'north' => ['required', 'numeric', 'between:-85.05112878,85.05112878'],
        ]);
        abort_unless($validator->passes(), 422, __('listing::map.invalid_bounds'));
        $bounds = array_map('floatval', $validator->validated());
        abort_unless($bounds['south'] < $bounds['north'] && $bounds['west'] !== $bounds['east'] && ! ($bounds['west'] === 180.0 && $bounds['east'] === -180.0), 422, __('listing::map.invalid_bounds'));

        return $bounds;
    }
}
