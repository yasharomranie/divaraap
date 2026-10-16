<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domain\Deployment\DeploymentTriggerService;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Mockery;
use Modules\Admin\Filament\Pages\ProductionDeploy;
use Modules\User\App\Models\User;
use RuntimeException;
use Spatie\Permission\Models\Permission;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

final class ProductionDeployTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        config(['app.locale' => 'fa']);
        app()->setLocale('fa');
        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    public function test_initial_page_has_a_deploy_button_and_no_log(): void
    {
        $this->actingAs(User::where('email', 'a@a.com')->firstOrFail());
        $this->assertTrue(Permission::where('name', 'production-deploy.access')->where('guard_name', 'web')->exists());
        $this->withSession(['locale' => 'fa'])->get('/admin/production-deploy')->assertOk();
        app()->setLocale('fa');
        Livewire::test(ProductionDeploy::class)->assertSee('اجرای دیپلوی')->assertSee('هنوز لاگی برای دیپلوی ثبت نشده است.')->assertDontSee('در انتظار اجرا');
        Storage::disk('local')->assertMissing('deploy.trigger');
    }

    public function test_request_writes_a_signal_and_replaces_the_button_with_pending_badge(): void
    {
        $this->actingAs(User::where('email', 'a@a.com')->firstOrFail());
        $page = Livewire::test(ProductionDeploy::class)->call('requestDeploy')->assertNotified('درخواست دیپلوی ثبت شد')->assertSee('در انتظار اجرا')->assertDontSee('wire:click="requestDeploy"', false);
        Storage::disk('local')->assertExists('deploy.trigger');
        Storage::disk('local')->delete('deploy.trigger');
        Storage::disk('local')->put('deploy_log.txt', 'Cron completed');
        $page->call('$refresh')->assertSee('Cron completed')->assertDontSee('در انتظار اجرا')->assertSee('wire:click="requestDeploy"', false);
    }

    public function test_log_is_escaped_and_timestamp_is_shamsi_without_a_three_hour_offset(): void
    {
        $this->actingAs(User::where('email', 'a@a.com')->firstOrFail());
        config(['app.timezone' => 'Asia/Tehran']);
        Storage::disk('local')->put('deploy_log.txt', '<script>alert("log")</script>');
        $path = Storage::disk('local')->path('deploy_log.txt');
        touch($path, strtotime('2025-03-20T21:00:00Z'));
        clearstatcache(true, $path);
        Livewire::test(ProductionDeploy::class)->assertSee('<script>alert("log")</script>')->assertDontSee('<script>alert("log")</script>', false)->assertSee('۱ فروردین ۱۴۰۴، ۰۰:۳۰');
    }

    public function test_member_without_permission_cannot_view_or_trigger_deployment(): void
    {
        $this->actingAs(User::where('email', 'b@b.com')->firstOrFail());
        $this->assertFalse(ProductionDeploy::canAccess());
        $this->get('/admin/production-deploy')->assertForbidden();
        try {
            (new ProductionDeploy)->requestDeploy();
            $this->fail('Unauthorized deployment request accepted.');
        } catch (HttpException $exception) {
            $this->assertSame(403, $exception->getStatusCode());
        }
        Storage::disk('local')->assertMissing('deploy.trigger');
    }

    public function test_page_permission_uses_the_existing_permission_gate(): void
    {
        $user = User::where('email', 'b@b.com')->firstOrFail();
        $user->givePermissionTo('production-deploy.access');
        $this->actingAs($user);
        $this->assertTrue(ProductionDeploy::canAccess());
    }

    public function test_write_failure_shows_an_error_instead_of_a_success_notification(): void
    {
        $this->actingAs(User::where('email', 'a@a.com')->firstOrFail());
        $service = Mockery::mock(new DeploymentTriggerService);
        $service->shouldReceive('isPending')->andReturnFalse();
        $service->shouldReceive('lastLog')->andReturnNull();
        $service->shouldReceive('lastDeployAt')->andReturnNull();
        $service->shouldReceive('requestDeploy')->once()->andThrow(new RuntimeException('private filesystem details'));
        $this->instance(DeploymentTriggerService::class, $service);
        Livewire::test(ProductionDeploy::class)->call('requestDeploy')->assertNotified('ثبت درخواست دیپلوی انجام نشد؛ دسترسی نوشتن فضای خصوصی را بررسی کنید.')->assertDontSee('private filesystem details');
        Storage::disk('local')->assertMissing('deploy.trigger');
    }
}
