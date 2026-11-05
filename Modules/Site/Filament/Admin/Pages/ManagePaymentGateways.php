<?php

declare(strict_types=1);

namespace Modules\Site\Filament\Admin\Pages;

use BackedEnum;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Pages\SettingsPage;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Validation\ValidationException;
use Modules\Advertising\Services\PaymentGatewayCatalog;
use Modules\Site\App\Settings\PaymentGatewaySettings;
use UnitEnum;

final class ManagePaymentGateways extends SettingsPage
{
    protected static string $settings = PaymentGatewaySettings::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-credit-card';

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
        return __('advertising::gateway.settings');
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
        $sections = [];
        foreach (PaymentGatewayCatalog::GATEWAYS as $gateway) {
            $sections[] = Section::make(__('advertising::gateway.'.$gateway))->description(__('advertising::gateway.settings_help'))->schema([
                Toggle::make($gateway.'_enabled')->label(__('advertising::gateway.enabled')),
                Toggle::make($gateway.'_sandbox')->label(__('advertising::gateway.sandbox'))->helperText(__('advertising::gateway.sandbox_help')),
                TextInput::make($gateway.'_merchant')->label(__('advertising::gateway.merchant'))->password()->autocomplete('new-password')->maxLength(200)->extraInputAttributes(['dir' => 'ltr'])->helperText(fn (): string => filled(app(PaymentGatewaySettings::class)->{$gateway.'_merchant'}) ? __('advertising::gateway.key_saved') : __('advertising::gateway.key_empty')),
            ]);
        }

        return $schema->components($sections);
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        foreach (PaymentGatewayCatalog::GATEWAYS as $gateway) {
            $data[$gateway.'_merchant'] = '';
        }

        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        abort_unless(self::canAccess(), 403);
        $settings = app(PaymentGatewaySettings::class);
        foreach (PaymentGatewayCatalog::GATEWAYS as $gateway) {
            $field = $gateway.'_merchant';
            if (blank($data[$field] ?? null)) {
                unset($data[$field]);
            } else {
                $data[$field] = trim($data[$field]);
            }
            $sandbox = (bool) ($data[$gateway.'_sandbox'] ?? false);
            $merchant = $gateway === 'zibal' && $sandbox ? 'zibal' : ($data[$field] ?? $settings->{$field});
            if (($data[$gateway.'_enabled'] ?? false) && ! PaymentGatewayCatalog::validMerchant($gateway, $merchant, $sandbox)) {
                throw ValidationException::withMessages(['data.'.$field => __('advertising::gateway.invalid_merchant')]);
            }
        }

        return $data;
    }

    protected function afterSave(): void
    {
        foreach (PaymentGatewayCatalog::GATEWAYS as $gateway) {
            $this->data[$gateway.'_merchant'] = '';
        }
    }
}
