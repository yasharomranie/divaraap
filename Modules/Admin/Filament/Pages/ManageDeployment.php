<?php

declare(strict_types=1);

namespace Modules\Admin\Filament\Pages;

use App\Support\Deployment\DeploymentManager;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\SettingsPage;
use Filament\Schemas\Schema;
use Illuminate\Http\Client\ConnectionException;
use Livewire\Attributes\Locked;
use Modules\Site\App\Settings\DeploymentSettings;
use Throwable;
use UnitEnum;

final class ManageDeployment extends SettingsPage
{
    protected static string $settings = DeploymentSettings::class;

    protected string $view = 'filament.manage-deployment';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-arrow-up-tray';

    public ?string $recoveryCode = null;

    #[Locked]
    public ?array $pendingDeployment = null;

    #[Locked]
    public array $deploymentStatus = [];

    protected function afterFill(): void
    {
        $this->refreshDeploymentStatus();
    }

    public function refreshDeploymentStatus(): void
    {
        abort_unless(self::canAccess(), 403);
        try {
            $this->deploymentStatus = app(DeploymentManager::class)->status();
        } catch (Throwable) {
            $this->deploymentStatus = [];
        }
    }

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->hasRole('admin');
    }

    public function canEdit(): bool
    {
        return self::canAccess();
    }

    public static function getNavigationLabel(): string
    {
        return 'به‌روزرسانی سایت';
    }

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return __('admin::admin.settings');
    }

    public function getTitle(): string
    {
        return 'به‌روزرسانی سایت';
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('repository')->label('مخزن گیت‌هاب')->required()->regex('~\A[A-Za-z0-9_.-]+/[A-Za-z0-9_.-]+\z~')->maxLength(200)->extraInputAttributes(['dir' => 'ltr'])->helperText('نمونه: yasharomranie/divaraap'),
            TextInput::make('ref')->label('شاخه')->required()->regex('/\A[A-Za-z0-9_.-]{1,100}\z/')->maxLength(100)->extraInputAttributes(['dir' => 'ltr'])->helperText('بستهٔ آماده از انتشار deploy-main برای شاخهٔ main دریافت می‌شود.'),
            TextInput::make('github_token')->label('توکن گیت‌هاب')->password()->maxLength(500)->autocomplete('new-password')->extraInputAttributes(['dir' => 'ltr'])->helperText('برای مخزن خصوصی: دسترسی Contents: Read-only. خالی بگذارید تا توکن ذخیره‌شده حفظ شود.'),
        ]);
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data['github_token'] = '';

        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        abort_unless(self::canAccess(), 403);
        if (blank($data['github_token'] ?? null)) {
            unset($data['github_token']);
        }

        return $data;
    }

    protected function afterSave(): void
    {
        $this->data['github_token'] = '';
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('deploy')->extraAttributes(['style' => 'min-height:44px'])->label('دریافت و نصب آخرین نسخه')->requiresConfirmation()->modalDescription('از اطلاعات پایگاه داده پشتیبان گرفته می‌شود. هنگام نصب سایت موقتاً در دسترس نیست. کد بازیابی را از قبل ذخیره کنید.')->action('deploy'),
            Action::make('rollback')->color('gray')->extraAttributes(['style' => 'min-height:44px'])->label('برگشت کد نسخهٔ قبلی')->requiresConfirmation()->modalDescription('فقط کد برمی‌گردد؛ تغییرات پایگاه داده برنمی‌گردد. مهاجرت‌ها باید با نسخهٔ قبلی سازگار باشند.')->action('rollback'),
            Action::make('recovery')->color('gray')->extraAttributes(['style' => 'min-height:44px'])->label('ساخت کد بازیابی')->requiresConfirmation()->modalDescription('کد قبلی باطل می‌شود. کد جدید را خارج از سایت نگه دارید.')->action('createRecoveryCode'),
        ];
    }

    public function deploy(): void
    {
        abort_unless(self::canAccess(), 403);
        try {
            if (function_exists('set_time_limit')) {
                set_time_limit(240);
            }
            $job = app(DeploymentManager::class)->prepare();
            $this->pendingDeployment = $job;
            $this->deploymentStatus = ['state' => 'prepared', 'message' => 'بسته آماده شد؛ در حال فرستادن درخواست نصب. صفحه را نبندید.'];
            $this->dispatch('deployment-ready', ...$job);
        } catch (Throwable $exception) {
            $this->pendingDeployment = null;
            $diagnostic = class_basename($exception).' | '.basename($exception->getFile()).':'.$exception->getLine();
            $this->deploymentStatus = ['state' => 'failed', 'message' => $this->safeError($exception).' | '.$diagnostic];
            Notification::make()->danger()->title('آماده‌سازی انجام نشد')->body($this->deploymentStatus['message'])->persistent()->send();
        }
    }

    public function startPackageUpload(int $size, string $checksum): string
    {
        abort_unless(self::canAccess(), 403);

        return app(DeploymentManager::class)->startUpload($size, $checksum, (int) auth()->id());
    }

    public function uploadPackageChunk(string $id, int $offset, string $encoded): int
    {
        abort_unless(self::canAccess(), 403);

        return app(DeploymentManager::class)->uploadChunk($id, $offset, $encoded, (int) auth()->id());
    }

    public function finishPackageUpload(string $id): void
    {
        abort_unless(self::canAccess(), 403);
        if (function_exists('set_time_limit')) {
            set_time_limit(240);
        }
        $job = app(DeploymentManager::class)->finishUpload($id, (int) auth()->id());
        $this->pendingDeployment = $job;
        $this->deploymentStatus = ['state' => 'prepared', 'message' => 'صحت بستهٔ بارگذاری‌شده تأیید شد؛ نصب شروع می‌شود.'];
        $this->dispatch('deployment-ready', ...$job);
    }

    public function createRecoveryCode(): void
    {
        abort_unless(self::canAccess(), 403);
        try {
            $this->recoveryCode = app(DeploymentManager::class)->recoveryCode();
        } catch (Throwable) {
            Notification::make()->danger()->title('این قابلیت فقط در بستهٔ نصب هاست اشتراکی فعال است.')->send();
        }
    }

    public function rollback(): void
    {
        abort_unless(self::canAccess(), 403);
        try {
            app(DeploymentManager::class)->rollback();
            $this->redirect('/admin');
        } catch (Throwable $exception) {
            Notification::make()->danger()->title('برگشت انجام نشد')->body($this->safeError($exception))->send();
        }
    }

    private function safeError(Throwable $exception): string
    {
        if (preg_match('/\AGitHub release unavailable \(HTTP (\d{3})\)\.\z/', $exception->getMessage(), $match)) {
            return 'گیت‌هاب دریافت بسته را نپذیرفت؛ HTTP '.$match[1].'. دسترسی مخزن و توکن را بررسی کنید.';
        }

        if ($exception instanceof ConnectionException) {
            return 'ارتباط خروجی هاست برقرار نشد. از بخش «نصب بسته از دستگاه» استفاده کنید؛ این روش به اتصال هاست به گیت‌هاب نیاز ندارد.';
        }

        return match ($exception->getMessage()) {
            'Deployment is available only in the shared-hosting installation package.' => 'این قابلیت فقط در بستهٔ نصب هاست اشتراکی فعال است.',
            'Create and save a recovery code before deploying.' => 'ابتدا کد بازیابی بسازید و در جای امن ذخیره کنید.',
            'At least 1.8 GB of free disk space is required.' => 'برای آماده‌سازی نسخه حداقل ۱٫۸ گیگابایت فضای خالی لازم است.',
            'No previous release is available.' => 'نسخهٔ قبلی برای برگشت وجود ندارد.',
            'Another deployment is running.' => 'به‌روزرسانی دیگری در حال اجراست.',
            'Recover the interrupted deployment before starting another.' => 'ابتدا به‌روزرسانی متوقف‌شده را از صفحهٔ بازیابی بررسی کنید.',
            'An update is already prepared. Retry after ten minutes.' => 'نسخه‌ای آمادهٔ نصب است. ده دقیقه بعد دوباره امتحان کنید.',
            default => 'دریافت یا پردازش نسخه انجام نشد. دسترسی گیت‌هاب، انتشار بستهٔ آماده با نام deploy-main و فضای خالی هاست را بررسی کنید.',
        };
    }
}
