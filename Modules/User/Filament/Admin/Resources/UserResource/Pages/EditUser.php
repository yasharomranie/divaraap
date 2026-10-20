<?php

declare(strict_types=1);

namespace Modules\User\Filament\Admin\Resources\UserResource\Pages;

use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Modules\User\App\Models\Profile;
use Modules\User\Filament\Admin\Resources\UserResource;
use STS\FilamentImpersonate\Actions\Impersonate;

class EditUser extends EditRecord
{
    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('manageVerification')
                ->label(__('user::verification.manage'))
                ->visible(fn (): bool => auth()->user() && Profile::canManageVerification(auth()->user(), $this->getRecord()))
                ->modalDescription(__('user::verification.staff_hint'))
                ->schema([
                    Select::make('decision')->label(__('user::verification.decision'))->options(['grant' => __('user::verification.grant'), 'revoke' => __('user::verification.revoke')])->required()->in(['grant', 'revoke']),
                    Textarea::make('reason')->label(__('user::verification.reason'))->helperText(__('user::verification.private'))->required()->minLength(3)->maxLength(1000),
                    Hidden::make('version')->default(fn () => Profile::verificationVersion($this->getRecord()->fresh()))->required()->rules(['string', 'size:64', 'regex:/^[a-f0-9]+$/']),
                ])
                ->action(function (array $data): void {
                    Profile::setVerificationFor(auth()->user(), $this->getRecord(), $data['decision'] === 'grant', $data['reason'], $data['version']);
                    Notification::make()->title(__('user::verification.saved'))->success()->send();
                }),
            Impersonate::make()->record($this->getRecord()),
            DeleteAction::make(),
        ];
    }
}
