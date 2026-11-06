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
use Modules\Notification\Services\NotificationEmailSender;
use Modules\Site\App\Settings\EmailNotificationSettings;
use UnitEnum;

final class ManageEmailNotifications extends SettingsPage
{
    protected static string $settings = EmailNotificationSettings::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-envelope';

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
        return __('notification::email.settings');
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
        return $schema->components([Section::make(__('notification::email.settings'))->description(__('notification::email.admin_help'))->schema([
            Toggle::make('enabled')->label(__('notification::email.site_enabled'))->helperText(NotificationEmailSender::ready() ? __('notification::email.transport_ready') : __('notification::email.transport_missing')),
            TextInput::make('per_user_daily')->label(__('notification::email.per_user_daily'))->numeric()->required()->minValue(1)->maxValue(100),
            TextInput::make('site_daily')->label(__('notification::email.site_daily'))->numeric()->required()->minValue(1)->maxValue(10000),
        ])]);
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        abort_unless(self::canAccess(), 403);
        if (($data['enabled'] ?? false) && ! NotificationEmailSender::ready()) {
            throw ValidationException::withMessages(['data.enabled' => __('notification::email.transport_missing')]);
        }

        return $data;
    }
}
