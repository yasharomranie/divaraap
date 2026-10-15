<?php

declare(strict_types=1);

namespace App\Support\Installation;

use Illuminate\Support\Facades\DB;
use Modules\Category\Database\Seeders\CategorySeeder;
use Modules\Location\Database\Seeders\IranianLocationSeeder;
use Modules\Site\App\Settings\GeneralSettings;
use Modules\User\App\Models\User;
use Spatie\Permission\Models\Role;

final class InstallationData
{
    public static function initialize(array $input): void
    {
        DB::transaction(function () use ($input): void {
            $user = User::registerFrom(['name' => $input['admin_name'], 'email' => $input['admin_email'], 'password' => $input['admin_password']]);
            $user->forceFill(['email_verified_at' => now(), 'status' => 'active'])->save();
            $user->assignRole(Role::findOrCreate('admin', 'web'));
            $settings = app(GeneralSettings::class);
            $settings->site_name = $input['site_name'];
            $settings->default_language = 'fa';
            $settings->default_country_code = '+98';
            $settings->currencies = ['IRR'];
            $settings->save();
        });
        (new CategorySeeder)->run();
        (new IranianLocationSeeder)->run();
    }
}
