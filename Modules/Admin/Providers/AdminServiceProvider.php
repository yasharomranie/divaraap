<?php

declare(strict_types=1);

namespace Modules\Admin\Providers;

use App\Support\Filament\FileManagerNotificationMailbox;
use Filament\Events\ServingFilament;
use Filament\Notifications\NotificationMailbox;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class AdminServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadTranslationsFrom(module_path('Admin', 'lang'), 'admin');
        Event::listen(ServingFilament::class, function (): void {
            config()->set('filemanager.file_manager.navigation.label', __('admin::admin.file_manager'));
            config()->set('filemanager.file_manager.navigation.group', __('admin::admin.files'));
            config()->set('filemanager.sidebar.root_label', __('admin::admin.root'));
        });
        $this->loadMigrationsFrom(module_path('Admin', 'Database/migrations'));
    }

    public function register(): void
    {
        $this->app->scoped(NotificationMailbox::class, FileManagerNotificationMailbox::class);
    }
}
