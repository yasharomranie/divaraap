<?php

declare(strict_types=1);

namespace Modules\Admin\Filament\Pages;

use BackedEnum;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Modules\Admin\Models\StaffSecurityPolicy;
use UnitEnum;

final class ManageStaffSecurity extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-shield-check';

    protected string $view = 'admin::staff-security.policy';

    public ?array $data = [];

    public static function canAccess(): bool
    {
        return auth()->user()?->hasRole('admin') && (string) auth()->user()->status === 'active';
    }

    public static function getNavigationLabel(): string
    {
        return __('admin::security.policy');
    }

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return __('admin::admin.settings');
    }

    public function getTitle(): string
    {
        return self::getNavigationLabel();
    }

    public function mount(): void
    {
        abort_unless(self::canAccess(), 403);
        $this->form->fill(StaffSecurityPolicy::current()->only(['enabled', 'roles', 'step_up_minutes', 'version']) + ['current_password' => '']);
    }

    public function form(Schema $schema): Schema
    {
        return $schema->statePath('data')->components([
            Toggle::make('enabled')->label(__('admin::security.enabled'))->helperText(__('admin::security.enabled_help')),
            CheckboxList::make('roles')->label(__('admin::security.roles'))->options(collect(StaffSecurityPolicy::ROLES)->mapWithKeys(fn ($role) => [$role => __('admin::security.role_'.$role)])->all()),
            Select::make('step_up_minutes')->label(__('admin::security.step_up_minutes'))->options([5 => '5', 15 => '15', 30 => '30', 60 => '60'])->required(),
            Hidden::make('version')->required()->rules(['integer']),
            TextInput::make('current_password')->label(__('admin::security.current_password'))->password()->autocomplete('current-password')->required()->maxLength(1024),
        ]);
    }

    public function save(): void
    {
        abort_unless(self::canAccess(), 403);
        $data = $this->form->getState();
        try {
            $policy = StaffSecurityPolicy::change((bool) $data['enabled'], $data['roles'] ?? [], (int) $data['step_up_minutes'], (int) $data['version'], $data['current_password']);
            $this->data['version'] = $policy->version;
            Notification::make()->success()->title(__('admin::security.saved'))->send();
        } finally {
            $this->data['current_password'] = '';
        }
    }
}
