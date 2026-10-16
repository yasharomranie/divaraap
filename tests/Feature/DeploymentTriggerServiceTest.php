<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domain\Deployment\DeploymentTriggerService;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;
use Mockery;
use RuntimeException;
use Tests\TestCase;

final class DeploymentTriggerServiceTest extends TestCase
{
    public function test_deploy_request_only_creates_a_private_signal(): void
    {
        Storage::fake('local');
        $service = new DeploymentTriggerService;
        $this->assertFalse($service->isPending());
        $service->requestDeploy();
        $this->assertTrue($service->isPending());
        $this->assertTrue(CarbonImmutable::parse(Storage::disk('local')->get('deploy.trigger'))->isToday());
        $this->assertNull($service->lastLog());
        $this->assertSame(['deploy.trigger'], Storage::disk('local')->allFiles());
    }

    public function test_log_and_date_are_null_until_the_cron_writes_a_log(): void
    {
        Storage::fake('local');
        $service = new DeploymentTriggerService;
        $this->assertNull($service->lastLog());
        $this->assertNull($service->lastDeployAt());
        Storage::disk('local')->put('deploy_log.txt', "Git updated\nMigration completed\n");
        $this->assertSame("Git updated\nMigration completed\n", $service->lastLog());
        $this->assertTrue($service->lastDeployAt()->isToday());
    }

    public function test_last_modified_uses_the_application_timezone_at_tehran_midnight(): void
    {
        Storage::fake('local');
        config(['app.timezone' => 'Asia/Tehran']);
        Storage::disk('local')->put('deploy_log.txt', 'completed');
        $timestamp = CarbonImmutable::parse('2025-03-20 21:00:00', 'UTC')->getTimestamp();
        $path = Storage::disk('local')->path('deploy_log.txt');
        touch($path, $timestamp);
        clearstatcache(true, $path);
        $date = (new DeploymentTriggerService)->lastDeployAt();
        $this->assertSame('Asia/Tehran', $date->getTimezone()->getName());
        $this->assertSame('2025-03-21 00:30', $date->format('Y-m-d H:i'));
    }

    public function test_failed_write_does_not_report_a_successful_request(): void
    {
        $disk = Mockery::mock(Filesystem::class);
        $disk->shouldReceive('put')->once()->with('deploy.trigger', Mockery::type('string'))->andReturnFalse();
        Storage::shouldReceive('disk')->once()->with('local')->andReturn($disk);
        $this->expectException(RuntimeException::class);
        (new DeploymentTriggerService)->requestDeploy();
    }
}
