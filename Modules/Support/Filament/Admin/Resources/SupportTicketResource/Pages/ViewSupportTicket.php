<?php

declare(strict_types=1);

namespace Modules\Support\Filament\Admin\Resources\SupportTicketResource\Pages;

use Filament\Actions\Action;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Support\Str;
use Modules\Support\Filament\Admin\Resources\SupportTicketResource;
use Modules\Support\Models\SupportTicket;

final class ViewSupportTicket extends ViewRecord
{
    protected static string $resource = SupportTicketResource::class;

    protected string $view = 'support::staff-show';

    public function getTitle(): string
    {
        return $this->getRecord()->reference;
    }

    protected function getHeaderActions(): array
    {
        $actions = [];
        foreach (['staff', 'internal', 'resolved', 'closed'] as $kind) {
            $actions[] = Action::make($kind)->label(__('support::messages.action_'.$kind))->visible(fn () => $this->getRecord()->canWork() && $this->getRecord()->state !== 'closed' && ($this->getRecord()->state !== 'resolved' || in_array($kind, ['internal', 'closed'], true)))
                ->schema([Hidden::make('version')->default(fn () => $this->getRecord()->version), Hidden::make('operation_key')->default(fn () => (string) Str::uuid()), Textarea::make('body')->label(__('support::messages.body'))->helperText(__('support::messages.'.($kind === 'internal' ? 'internal_help' : 'public_help')))->required()->maxLength(4000)->rows(5)])
                ->action(function (array $data) use ($kind): void {
                    $this->getRecord()->write($kind, $data['body'], (int) $data['version'], $data['operation_key'], true);
                    $this->getRecord()->refresh();
                    Notification::make()->success()->title(__('support::messages.saved'))->send();
                });
        }
        $actions[] = Action::make('claim')->label(__('support::messages.claim'))->visible(fn () => ! $this->getRecord()->assignee_id && $this->getRecord()->state !== 'closed' && (int) $this->getRecord()->user_id !== (int) auth()->id())->requiresConfirmation()
            ->schema([Hidden::make('version')->default(fn () => $this->getRecord()->version)])
            ->action(function (array $data): void {
                $this->getRecord()->assign(auth()->id(), (int) $data['version']);
                $this->getRecord()->refresh();
            });
        $actions[] = Action::make('assign')->label(__('support::messages.assign'))->visible(fn () => auth()->user()->hasRole('admin') && $this->getRecord()->state !== 'closed')
            ->schema([Hidden::make('version')->default(fn () => $this->getRecord()->version), Select::make('assignee_id')->label(__('support::messages.assignee'))->options(fn () => SupportTicket::assigneeOptions())->searchable()->placeholder(__('support::messages.unassigned'))])
            ->action(function (array $data): void {
                $this->getRecord()->assign(filled($data['assignee_id'] ?? null) ? (int) $data['assignee_id'] : null, (int) $data['version']);
                $this->getRecord()->refresh();
            });

        return $actions;
    }
}
