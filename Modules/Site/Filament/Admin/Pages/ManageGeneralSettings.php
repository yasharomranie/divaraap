<?php

declare(strict_types=1);

namespace Modules\Site\Filament\Admin\Pages;

use App\Support\Money;
use BackedEnum;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Pages\SettingsPage;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Modules\Location\Support\CountryCodeManager;
use Modules\Site\App\Settings\GeneralSettings;
use Modules\Site\App\Support\HomeSlideDefaults;
use Modules\Site\App\Support\LocalMedia;
use Modules\Site\Support\Filament\HomeSlideFormSchema;
use Tapp\FilamentCountryCodeField\Forms\Components\CountryCodeSelect;
use UnitEnum;
use Ysfkaya\FilamentPhoneInput\Forms\PhoneInput;

class ManageGeneralSettings extends SettingsPage
{
    protected static string $settings = GeneralSettings::class;

    protected static ?string $title = 'General Settings';

    protected static ?string $navigationLabel = 'General Settings';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-cog-6-tooth';

    protected static string|UnitEnum|null $navigationGroup = 'Settings';

    protected static ?int $navigationSort = 1;

    public static function getNavigationLabel(): string
    {
        return __('site::admin.general_settings');
    }

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return __('site::admin.settings');
    }

    public function getTitle(): string
    {
        return __('site::admin.general_settings');
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $defaults = $this->defaultFormData();

        return [
            'site_name' => filled($data['site_name'] ?? null) ? $data['site_name'] : $defaults['site_name'],
            'site_description' => filled($data['site_description'] ?? null) ? $data['site_description'] : $defaults['site_description'],
            'home_slides' => $this->normalizeHomeSlides($data['home_slides'] ?? $defaults['home_slides']),
            'site_logo' => $data['site_logo'] ?? null,
            'sender_name' => filled($data['sender_name'] ?? null) ? $data['sender_name'] : $defaults['sender_name'],
            'sender_email' => filled($data['sender_email'] ?? null) ? $data['sender_email'] : $defaults['sender_email'],
            'default_language' => filled($data['default_language'] ?? null) ? $data['default_language'] : $defaults['default_language'],
            'default_country_code' => filled($data['default_country_code'] ?? null) ? $data['default_country_code'] : $defaults['default_country_code'],
            'currencies' => $this->normalizeCurrencies($data['currencies'] ?? $defaults['currencies']),
            'linkedin_url' => filled($data['linkedin_url'] ?? null) ? $data['linkedin_url'] : $defaults['linkedin_url'],
            'instagram_url' => filled($data['instagram_url'] ?? null) ? $data['instagram_url'] : $defaults['instagram_url'],
            'whatsapp' => filled($data['whatsapp'] ?? null) ? $data['whatsapp'] : $defaults['whatsapp'],
            'enable_google_maps' => (bool) ($data['enable_google_maps'] ?? $defaults['enable_google_maps']),
            'google_maps_api_key' => $data['google_maps_api_key'] ?? null,
            'enable_google_login' => (bool) ($data['enable_google_login'] ?? $defaults['enable_google_login']),
            'google_client_id' => $data['google_client_id'] ?? null,
            'google_client_secret' => $data['google_client_secret'] ?? null,
            'enable_facebook_login' => (bool) ($data['enable_facebook_login'] ?? $defaults['enable_facebook_login']),
            'facebook_client_id' => $data['facebook_client_id'] ?? null,
            'facebook_client_secret' => $data['facebook_client_secret'] ?? null,
            'enable_apple_login' => (bool) ($data['enable_apple_login'] ?? $defaults['enable_apple_login']),
            'apple_client_id' => $data['apple_client_id'] ?? null,
            'apple_client_secret' => $data['apple_client_secret'] ?? null,
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data['home_slides'] = $this->normalizeHomeSlides($data['home_slides'] ?? []);
        $data['currencies'] = $this->normalizeCurrencies($data['currencies'] ?? []);

        return $data;
    }

    public function form(Schema $schema): Schema
    {
        $defaults = $this->defaultFormData();

        $fields = [
            TextInput::make('site_name')->label(__('site::admin.site_name'))->extraInputAttributes(['dir' => 'auto'])
                ->label(__('site::admin.site_name'))
                ->default($defaults['site_name'])
                ->required()
                ->maxLength(255),
            Textarea::make('site_description')->label(__('site::admin.site_description'))
                ->default($defaults['site_description'])
                ->rows(3)
                ->columnSpanFull()
                ->maxLength(500),
            HomeSlideFormSchema::make(
                $defaults['home_slides'],
                fn ($state): array => $this->normalizeHomeSlides($state),
            ),
            FileUpload::make('site_logo')->label(__('site::admin.site_logo'))
                ->image()
                ->disk(LocalMedia::disk())
                ->directory(LocalMedia::managedDirectory('settings'))
                ->visibility('public'),
            TextInput::make('sender_name')->label(__('site::admin.sender_name'))->extraInputAttributes(['dir' => 'auto'])
                ->label(__('site::admin.sender_name'))
                ->default($defaults['sender_name'])
                ->required()
                ->maxLength(120),
            TextInput::make('sender_email')->label(__('site::admin.sender_email'))->extraInputAttributes(['dir' => 'ltr'])
                ->label(__('site::admin.sender_email'))
                ->email()
                ->default($defaults['sender_email'])
                ->required()
                ->maxLength(255),
            Select::make('default_language')->label(__('site::admin.default_language'))
                ->options($this->localeOptions())
                ->default($defaults['default_language'])
                ->required()
                ->searchable(),
            CountryCodeSelect::make('default_country_code')->label(__('site::admin.default_country'))
                ->default($defaults['default_country_code'])
                ->required()
                ->helperText(__('site::admin.used_as_the_default_country_in_panel_forms')),
            Select::make('currencies')->label(__('site::admin.currencies'))
                ->options(['IRR' => Money::currencyLabel('IRR')])
                ->multiple()
                ->default(['IRR'])
                ->disabled()
                ->dehydrated()
                ->afterStateHydrated(fn (Select $component) => $component->state(['IRR']))
                ->dehydrateStateUsing(fn (): array => ['IRR']),
            TextInput::make('linkedin_url')->label(__('site::admin.linkedin_url'))->extraInputAttributes(['dir' => 'ltr'])
                ->label(__('site::admin.linkedin_url'))
                ->url()
                ->default($defaults['linkedin_url'])
                ->nullable()
                ->maxLength(255),
            TextInput::make('instagram_url')->label(__('site::admin.instagram_url'))->extraInputAttributes(['dir' => 'ltr'])
                ->label(__('site::admin.instagram_url'))
                ->url()
                ->default($defaults['instagram_url'])
                ->nullable()
                ->maxLength(255),
            PhoneInput::make('whatsapp')->label(__('site::admin.whatsapp'))
                ->defaultCountry(CountryCodeManager::defaultCountryIso2())
                ->default($defaults['whatsapp'])
                ->nullable()
                ->formatAsYouType()
                ->helperText(__('site::admin.use_international_format_example_905551112233')),
            Toggle::make('enable_google_maps')->label(__('site::admin.google_maps_enabled'))
                ->default($defaults['enable_google_maps']),
            TextInput::make('google_maps_api_key')->label(__('site::admin.google_maps_api_key'))->extraInputAttributes(['dir' => 'ltr'])
                ->label(__('site::admin.google_maps_api_key'))
                ->password()
                ->revealable()
                ->nullable()
                ->maxLength(255)
                ->helperText(__('site::admin.required_to_enable_map_fields_in_listing_forms')),
            Toggle::make('enable_google_login')->label(__('site::admin.google_login_enabled'))
                ->default($defaults['enable_google_login']),
            TextInput::make('google_client_id')->label(__('site::admin.google_client_id'))->extraInputAttributes(['dir' => 'ltr'])
                ->label(__('site::admin.google_client_id'))
                ->nullable()
                ->maxLength(255),
            TextInput::make('google_client_secret')->label(__('site::admin.google_client_secret'))->extraInputAttributes(['dir' => 'ltr'])
                ->label(__('site::admin.google_client_secret'))
                ->password()
                ->revealable()
                ->nullable()
                ->maxLength(255),
            Toggle::make('enable_facebook_login')->label(__('site::admin.facebook_login_enabled'))
                ->default($defaults['enable_facebook_login']),
            TextInput::make('facebook_client_id')->label(__('site::admin.facebook_client_id'))->extraInputAttributes(['dir' => 'ltr'])
                ->label(__('site::admin.facebook_client_id'))
                ->nullable()
                ->maxLength(255),
            TextInput::make('facebook_client_secret')->label(__('site::admin.facebook_client_secret'))->extraInputAttributes(['dir' => 'ltr'])
                ->label(__('site::admin.facebook_client_secret'))
                ->password()
                ->revealable()
                ->nullable()
                ->maxLength(255),
            Toggle::make('enable_apple_login')->label(__('site::admin.apple_login_enabled'))
                ->default($defaults['enable_apple_login']),
            TextInput::make('apple_client_id')->label(__('site::admin.apple_client_id'))->extraInputAttributes(['dir' => 'ltr'])
                ->label(__('site::admin.apple_client_id'))
                ->nullable()
                ->maxLength(255),
            TextInput::make('apple_client_secret')->label(__('site::admin.apple_client_secret'))->extraInputAttributes(['dir' => 'ltr'])
                ->label(__('site::admin.apple_client_secret'))
                ->password()
                ->revealable()
                ->nullable()
                ->maxLength(255),
        ];
        $pick = static fn (array $names): array => array_values(array_filter(
            $fields,
            static fn ($field): bool => in_array($field->getName(), $names, true),
        ));

        return $schema->components([
            Section::make(__('site::mobile.settings_identity'))
                ->description(__('site::mobile.settings_identity_hint'))
                ->schema($pick(['site_name', 'site_description', 'site_logo']))
                ->columns(['default' => 1, 'md' => 2])->columnSpanFull()->collapsible(),
            Section::make(__('site::admin.homepage_slides'))
                ->schema($pick(['home_slides']))->columnSpanFull()->collapsible()->collapsed(),
            Section::make(__('site::mobile.settings_region'))
                ->schema($pick(['default_language', 'default_country_code', 'currencies']))
                ->columns(['default' => 1, 'md' => 2])->columnSpanFull()->collapsible(),
            Section::make(__('site::mobile.settings_contact'))
                ->schema($pick(['sender_name', 'sender_email', 'linkedin_url', 'instagram_url', 'whatsapp']))
                ->columns(['default' => 1, 'md' => 2])->columnSpanFull()->collapsible()->collapsed(),
            Section::make(__('site::mobile.settings_integrations'))
                ->schema($pick(['enable_google_maps', 'google_maps_api_key', 'enable_google_login', 'google_client_id', 'google_client_secret', 'enable_facebook_login', 'facebook_client_id', 'facebook_client_secret', 'enable_apple_login', 'apple_client_id', 'apple_client_secret']))
                ->columns(['default' => 1, 'md' => 2])->columnSpanFull()->collapsible()->collapsed(),
        ]);
    }

    private function defaultFormData(): array
    {
        $siteName = (string) config('app.name', 'OpenClassify');
        $siteHost = parse_url((string) config('app.url', 'https://oc2.test'), PHP_URL_HOST) ?: 'oc2.test';

        return [
            'site_name' => $siteName,
            'site_description' => __('site::admin.a_fast_and_secure_marketplace_for_buying_and_selling'),
            'home_slides' => $this->defaultHomeSlides(),
            'sender_name' => $siteName,
            'sender_email' => (string) config('mail.from.address', 'info@'.$siteHost),
            'default_language' => in_array(config('app.locale'), array_keys($this->localeOptions()), true) ? (string) config('app.locale') : 'en',
            'default_country_code' => CountryCodeManager::normalizeCountryCode(config('app.default_country_code', '+98')),
            'currencies' => $this->normalizeCurrencies(config('app.currencies', ['IRR'])),
            'linkedin_url' => 'https://www.linkedin.com/company/openclassify',
            'instagram_url' => 'https://www.instagram.com/openclassify',
            'whatsapp' => '+905551112233',
            'enable_google_maps' => false,
            'enable_google_login' => false,
            'enable_facebook_login' => false,
            'enable_apple_login' => false,
        ];
    }

    private function localeOptions(): array
    {
        $labels = config('app.locale_names', []);

        return collect(config('app.available_locales', ['en']))
            ->mapWithKeys(fn (string $locale) => [$locale => $labels[$locale] ?? strtoupper($locale)])
            ->all();
    }

    private function normalizeCurrencies(null|array|string $state): array
    {
        return ['IRR'];
    }

    private function defaultHomeSlides(): array
    {
        return HomeSlideDefaults::defaults();
    }

    private function normalizeHomeSlides(mixed $state): array
    {
        return HomeSlideDefaults::normalize($state);
    }
}
