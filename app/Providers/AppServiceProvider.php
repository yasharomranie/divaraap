<?php

declare(strict_types=1);

namespace App\Providers;

use BezhanSalleh\LanguageSwitch\LanguageSwitch;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Modules\Location\Support\CountryCodeManager;
use SocialiteProviders\Apple\Provider;
use SocialiteProviders\Manager\SocialiteWasCalled;
use Ysfkaya\FilamentPhoneInput\Forms\PhoneInput;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        if (config('hosting.profile') === 'shared') {
            config([
                'queue.default' => 'sync',
                'broadcasting.default' => 'null',
                'demo.enabled' => false,
                'video.processing' => 'direct',
                'video.client_side.enabled' => false,
                'media-library.image_driver' => 'gd',
                'media-library.image_optimizers' => [],
                'media-library.queue_conversions_by_default' => false,
            ]);
        }
    }

    public function boot(): void
    {
        PhoneInput::configureUsing(function (PhoneInput $input): void {
            $input->enableIpLookup(false)->initialCountry(fn (): string => strtolower($input->getDefaultCountry() ?: CountryCodeManager::defaultCountryIso2()));
        });

        Gate::before(function ($user): ?bool {
            if (method_exists($user, 'hasRole') && $user->hasRole('admin')) {
                return true;
            }

            return null;
        });

        View::addNamespace('app', resource_path('views'));

        Event::listen(function (SocialiteWasCalled $event): void {
            $event->extendSocialite('apple', Provider::class);
        });

        $availableLocales = config('app.available_locales', ['en']);
        $localeLabels = config('app.locale_names', []);

        LanguageSwitch::configureUsing(function (LanguageSwitch $switch) use ($availableLocales, $localeLabels): void {
            $switch
                ->locales($availableLocales)
                ->userPreferredLocale(fn (): string => request()->cookie('filament_language_switch_locale', config('app.locale')))
                ->labels(collect($availableLocales)->mapWithKeys(
                    fn (string $locale) => [$locale => $localeLabels[$locale] ?? strtoupper($locale)]
                )->all())
                ->visible(insidePanels: count($availableLocales) > 1, outsidePanels: false);
        });
    }
}
