<?php

declare(strict_types=1);

namespace Modules\Admin\Filament\Pages;

use BackedEnum;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Modules\Admin\Models\OperationalMonitor;
use UnitEnum;

final class ManageOperationalMonitor extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-adjustments-horizontal';

    protected string $view = 'admin::monitor.settings';

    public ?array $data = [];

    public static function canAccess(): bool
    {
        return OperationalHealth::canAccess();
    }

    public static function getNavigationLabel(): string
    {
        return __('admin::monitor.settings');
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
        OperationalMonitor::authorizeAdmin();
        $this->form->fill(OperationalMonitor::current()->only(['enabled', 'recipients', 'thresholds', 'version']));
    }

    public function form(Schema $schema): Schema
    {
        $fields = [
            Toggle::make('enabled')->label(__('admin::monitor.enabled')),
            Select::make('recipients')->label(__('admin::monitor.recipients'))->multiple()->options(fn () => OperationalMonitor::recipientOptions())->maxItems(5),
            Hidden::make('version')->required()->rules(['integer']),
        ];
        foreach (OperationalMonitor::LIMITS as $key => [$min, $max]) {
            $fields[] = TextInput::make('thresholds.'.$key)->label(__('admin::monitor.threshold_'.$key))->numeric()->minValue($min)->maxValue($max)->required();
        }

        return $schema->statePath('data')->components($fields)->columns(2);
    }

    public function save(): void
    {
        OperationalMonitor::authorizeAdmin();
        $monitor = OperationalMonitor::configure($this->form->getState());
        $this->data['version'] = $monitor->version;
        Notification::make()->success()->title(__('admin::monitor.saved'))->send();
    }
}
