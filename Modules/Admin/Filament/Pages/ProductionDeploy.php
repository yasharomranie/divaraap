<?php

declare(strict_types=1);

namespace Modules\Admin\Filament\Pages;

use App\Domain\Deployment\DeploymentTriggerService;
use App\Support\Filament\HasAccessControlledPage;
use App\Support\PersianLocale;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Throwable;
use UnitEnum;

final class ProductionDeploy extends Page
{
    use HasAccessControlledPage;

    protected string $view = 'filament.production-deploy';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-command-line';

    protected static function getAccessPermission(): string
    {
        return 'production-deploy.access';
    }

    public static function getNavigationLabel(): string
    {
        return __('admin::deployment.navigation');
    }

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return __('admin::admin.settings');
    }

    public function getTitle(): string
    {
        return __('admin::deployment.title');
    }

    public function isDeployPending(): bool
    {
        abort_unless(self::canAccess(), 403);

        return app(DeploymentTriggerService::class)->isPending();
    }

    public function lastDeployLog(): ?string
    {
        abort_unless(self::canAccess(), 403);

        return app(DeploymentTriggerService::class)->lastLog();
    }

    public function lastDeployAt(): ?string
    {
        abort_unless(self::canAccess(), 403);

        $date = app(DeploymentTriggerService::class)->lastDeployAt();

        return $date ? PersianLocale::date($date, withTime: true) : null;
    }

    public function requestDeploy(): void
    {
        abort_unless(self::canAccess(), 403);
        if ($this->isDeployPending()) {
            return;
        }
        try {
            app(DeploymentTriggerService::class)->requestDeploy();
        } catch (Throwable) {
            Notification::make()->danger()->title(__('admin::deployment.failed'))->send();

            return;
        }
        Notification::make()->success()->title(__('admin::deployment.requested'))->body(__('admin::deployment.requested_body'))->send();
    }
}
