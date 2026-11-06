<?php

declare(strict_types=1);

namespace Modules\Admin\Filament\Pages;

use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Livewire\WithPagination;
use Modules\Admin\Models\OperationalIncident;
use Modules\Admin\Models\OperationalMonitor;
use UnitEnum;

final class OperationalHealth extends Page implements HasActions
{
    use InteractsWithActions;
    use WithPagination;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-heart';

    protected string $view = 'admin::monitor.dashboard';

    public static function canAccess(): bool
    {
        return auth()->user()?->hasRole('admin') && (string) auth()->user()->status === 'active';
    }

    public static function getNavigationLabel(): string
    {
        return __('admin::monitor.title');
    }

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return __('admin::operations.group');
    }

    public function getTitle(): string
    {
        return self::getNavigationLabel();
    }

    public function sample(): void
    {
        OperationalMonitor::authorizeAdmin();
        $result = OperationalMonitor::sample();
        Notification::make()->title(__('admin::monitor.result_'.$result))->success()->send();
    }

    public function acknowledgeAction(): Action
    {
        return Action::make('acknowledge')->label(__('admin::monitor.acknowledge'))
            ->schema([Textarea::make('note')->label(__('admin::monitor.note'))->required()->maxLength(500)])
            ->action(function (array $arguments, array $data): void {
                OperationalIncident::acknowledge((int) ($arguments['id'] ?? 0), (int) ($arguments['version'] ?? -1), $data['note']);
                Notification::make()->success()->title(__('admin::monitor.acknowledged'))->send();
            });
    }

    protected function getViewData(): array
    {
        OperationalMonitor::authorizeAdmin();

        return ['monitor' => OperationalMonitor::current(), 'checks' => OperationalMonitor::snapshot(), 'incidents' => OperationalIncident::history()];
    }
}
