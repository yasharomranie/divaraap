<?php

declare(strict_types=1);

namespace Modules\Site\Filament\Admin\Pages;

use BackedEnum;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\TextInput;
use Filament\Pages\SettingsPage;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Validation\ValidationException;
use Modules\Site\App\Settings\AuthenticationSettings;
use Modules\User\App\Support\AuthenticationMethods;
use UnitEnum;

final class ManageAuthentication extends SettingsPage
{
    protected static string $settings = AuthenticationSettings::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-key';

    public static function canAccess(): bool
    {
        return auth()->user()?->hasRole('admin') && (string) auth()->user()->status === 'active';
    }

    public function canEdit(): bool
    {
        return self::canAccess();
    }

    public static function getNavigationLabel(): string
    {
        return __('user::otp.settings_title');
    }

    public function getTitle(): string
    {
        return self::getNavigationLabel();
    }

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return __('admin::admin.settings');
    }

    public function form(Schema $schema): Schema
    {
        $options = collect(AuthenticationMethods::METHODS)->mapWithKeys(fn (string $method): array => [$method => __('user::otp.method_'.$method)])->all();

        return $schema->components([
            Section::make(__('user::otp.methods_title'))->description(__('user::otp.methods_help'))->schema([
                CheckboxList::make('login_methods')->label(__('user::otp.login_methods'))->options($options)->required()->minItems(1),
                CheckboxList::make('registration_methods')->label(__('user::otp.registration_methods'))->options($options)->helperText(__('user::otp.registration_help')),
            ]),
            Section::make('SMS.ir')->description(__('user::otp.provider_help'))->schema([
                TextInput::make('smsir_api_key')->label(__('user::otp.api_key'))->password()->autocomplete('new-password')->maxLength(500)->extraInputAttributes(['dir' => 'ltr'])->helperText(fn (): string => filled(app(AuthenticationSettings::class)->smsir_api_key) ? __('user::otp.key_saved') : __('user::otp.key_empty')),
                TextInput::make('smsir_template_id')->label(__('user::otp.template_id'))->integer()->minValue(0)->maxValue(2147483647)->required()->extraInputAttributes(['dir' => 'ltr']),
                TextInput::make('smsir_parameter')->label(__('user::otp.parameter'))->regex('/\A[A-Za-z][A-Za-z0-9_]{0,49}\z/')->maxLength(50)->required()->extraInputAttributes(['dir' => 'ltr'])->helperText(__('user::otp.parameter_help')),
                TextInput::make('sms_daily_limit')->label(__('user::otp.daily_limit'))->integer()->minValue(1)->maxValue(10000)->required()->helperText(__('user::otp.limit_help')),
            ]),
        ]);
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data['smsir_api_key'] = '';

        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        abort_unless(self::canAccess(), 403);
        foreach (['login_methods', 'registration_methods'] as $field) {
            $data[$field] = array_values(array_unique($data[$field] ?? []));
            if (array_diff($data[$field], AuthenticationMethods::METHODS)) {
                throw ValidationException::withMessages(['data.'.$field => __('user::otp.invalid_methods')]);
            }
        }
        if (blank($data['smsir_api_key'] ?? null)) {
            unset($data['smsir_api_key']);
        }
        $key = $data['smsir_api_key'] ?? app(AuthenticationSettings::class)->smsir_api_key;
        if ((in_array('sms', $data['login_methods'], true) || in_array('sms', $data['registration_methods'], true)) && (blank($key) || (int) $data['smsir_template_id'] < 1)) {
            throw ValidationException::withMessages(['data.smsir_template_id' => __('user::otp.sms_configuration_required')]);
        }
        if (array_diff($data['registration_methods'], $data['login_methods'])) {
            throw ValidationException::withMessages(['data.registration_methods' => __('user::otp.registration_requires_login')]);
        }
        $usable = collect($data['login_methods'])->contains(fn (string $method): bool => in_array($method, ['email', 'sms'], true)
            || ((bool) config("services.{$method}.enabled") && filled(config("services.{$method}.client_id")) && filled(config("services.{$method}.client_secret"))));
        if (! $usable) {
            throw ValidationException::withMessages(['data.login_methods' => __('user::otp.usable_required')]);
        }

        return $data;
    }

    protected function afterSave(): void
    {
        $this->data['smsir_api_key'] = '';
    }
}
