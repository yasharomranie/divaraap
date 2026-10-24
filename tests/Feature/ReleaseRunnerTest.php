<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Support\Deployment\DatabaseBackup;
use App\Support\Deployment\ReleaseLayout;
use App\Support\Deployment\ReleaseRunner;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Artisan;
use Mockery;
use Tests\TestCase;

final class ReleaseRunnerTest extends TestCase
{
    public function test_verified_commands_activate_release_and_keep_private_state(): void
    {
        $this->exercise(false);
    }

    public function test_failed_migration_keeps_previous_code_and_requires_recovery(): void
    {
        $this->exercise(true);
    }

    private function exercise(bool $failure): void
    {
        $root = sys_get_temp_dir().'/runner-test-'.bin2hex(random_bytes(8));
        $id = bin2hex(random_bytes(16));
        mkdir($root.'/releases/'.$id.'/vendor', 0755, true);
        mkdir($root.'/releases/'.$id.'/bootstrap', 0755, true);
        file_put_contents($root.'/releases/'.$id.'/vendor/autoload.php', '<?php');
        file_put_contents($root.'/releases/'.$id.'/bootstrap/app.php', '<?php');
        file_put_contents($root.'/.env', 'persistent-secret');
        $layout = new ReleaseLayout($root, $root.'/public');
        $layout->write('deployment-maintenance.json', ['id' => $id]);
        $backup = Mockery::mock(DatabaseBackup::class);
        $backup->shouldReceive('create')->once();
        Artisan::shouldReceive('call')->with('migrate', ['--force' => true])->once()->andReturn($failure ? 1 : 0);
        if (! $failure) {
            Artisan::shouldReceive('call')->andReturn(0);
            Artisan::shouldReceive('output')->andReturn('Completed');
        }
        try {
            $result = (new ReleaseRunner($backup))->run($layout, ['id' => $id, 'token_hash' => 'one-use-secret']);
            $this->assertSame(! $failure, $result['ok']);
            $this->assertSame('persistent-secret', file_get_contents($root.'/.env'));
            $job = $layout->read('deployment-job.json');
            $this->assertArrayNotHasKey('token_hash', $job);
            $this->assertGreaterThanOrEqual(0, $job['total_duration_ms']);
            $this->assertSame(['database_backup', 'migrate'], array_slice(array_column($job['timings'], 'step'), 0, 2));
            foreach ($job['timings'] as $timing) {
                $this->assertGreaterThanOrEqual(0, $timing['duration_ms']);
            }
            if ($failure) {
                $this->assertFileDoesNotExist($root.'/active-release.json');
                $this->assertFileExists($root.'/deployment-maintenance.json');
                $this->assertSame('migrate', $job['step']);
                $this->assertSame('failed', $job['state']);
            } else {
                $this->assertSame($id, $layout->read('active-release.json')['current']);
                $this->assertFileDoesNotExist($root.'/deployment-maintenance.json');
                $this->assertSame('completed', $job['state']);
                $this->assertContains('migrate:settings', array_column($job['timings'], 'step'));
                $this->assertSame('activate', end($job['timings'])['step']);
            }
        } finally {
            (new Filesystem)->deleteDirectory($root);
        }
    }
}
