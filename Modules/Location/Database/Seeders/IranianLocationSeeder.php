<?php

declare(strict_types=1);

namespace Modules\Location\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Location\Models\City;

final class IranianLocationSeeder extends Seeder
{
    public function run(): void
    {
        City::ensureIranianCapitals();
    }
}
