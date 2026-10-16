<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Support\Deployment\DeploymentManager;
use App\Support\Deployment\GitHubRelease;
use App\Support\Deployment\ReleaseLayout;
use Filament\Facades\Filament;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Mockery;
use Modules\Admin\Filament\Pages\ManageDeployment;
use Modules\User\App\Models\User;
use RuntimeException;
use Tests\TestCase;

final class DeploymentHandoffTest extends TestCase
{
    public function test_prepared_release_is_resumed_without_downloading_or_waiting_ten_minutes(): void
    {
        $root = sys_get_temp_dir().'/handoff-'.bin2hex(random_bytes(8));
        $id = bin2hex(random_bytes(16));
        mkdir($root.'/releases/'.$id.'/vendor', 0755, true);
        mkdir($root.'/releases/'.$id.'/bootstrap', 0755, true);
        file_put_contents($root.'/installed.lock', 'installed');
        file_put_contents($root.'/recovery.token', '{}');
        file_put_contents($root.'/.env', 'persistent-environment');
        file_put_contents($root.'/releases/'.$id.'/vendor/autoload.php', '<?php');
        file_put_contents($root.'/releases/'.$id.'/bootstrap/app.php', '<?php');
        $layout = new ReleaseLayout($root, $root.'/public');
        $oldToken = str_repeat('a', 64);
        $layout->write('deployment-job.json', ['id' => $id, 'state' => 'prepared', 'expires' => time() + 600, 'token_hash' => hash('sha256', $oldToken)]);
        config(['deployment.root' => $root]);
        Http::preventStrayRequests();
        try {
            $job = (new DeploymentManager)->prepare();
            $this->assertSame($id, $job['id']);
            $this->assertNotSame($oldToken, $job['token']);
            $saved = $layout->read('deployment-job.json');
            $this->assertSame(hash('sha256', $job['token']), $saved['token_hash']);
            $this->assertSame('prepared', $saved['state']);
            $this->assertSame(hash('sha256', $job['token']), $saved['status_token_hash']);
            $this->assertSame('persistent-environment', file_get_contents($root.'/.env'));
            $status = (new DeploymentManager)->status();
            $this->assertSame('prepared', $status['state']);
            $this->assertArrayNotHasKey('token_hash', $status);
            file_put_contents($root.'/deployment-maintenance.json', '{}');
            $this->expectException(RuntimeException::class);
            $this->expectExceptionMessage('Recover the interrupted deployment');
            (new DeploymentManager)->prepare();
        } finally {
            (new Filesystem)->deleteDirectory($root);
        }
    }

    public function test_missing_prepared_release_is_downloaded_again_without_touching_active_code(): void
    {
        $parent = sys_get_temp_dir().'/handoff-missing-'.bin2hex(random_bytes(8));
        $root = $parent.'/divaraap';
        mkdir($root, 0700, true);
        file_put_contents($root.'/installed.lock', 'installed');
        file_put_contents($root.'/recovery.token', '{}');
        file_put_contents($root.'/.env', 'persistent-environment');
        $layout = new ReleaseLayout($root, $parent.'/public_html');
        $id = str_repeat('a', 32);
        $layout->write('deployment-job.json', ['id' => $id, 'state' => 'prepared', 'expires' => time() + 600]);
        config(['deployment.root' => $root]);
        $download = Mockery::mock(new GitHubRelease);
        $download->shouldReceive('download')->once()->andReturnUsing(function ($repository, $ref, $token, $target) {
            $zip = new \ZipArchive;
            $zip->open($target, \ZipArchive::CREATE);
            foreach (['divaraap/vendor/autoload.php' => '<?php', 'divaraap/bootstrap/app.php' => '<?php', 'divaraap/vendor/composer/installed.json' => '{"dev":false}', 'divaraap/release.json' => '{"deployment_protocol":1}', 'public_html/build/manifest.json' => '{}'] as $name => $bytes) {
                $zip->addFromString($name, $bytes);
            }
            $zip->close();

            return ['sha256' => hash_file('sha256', $target)];
        });
        $this->instance(GitHubRelease::class, $download);
        try {
            $job = (new DeploymentManager)->prepare();
            $this->assertNotSame($id, $job['id']);
            $this->assertSame('prepared', $layout->read('deployment-job.json')['state']);
            $this->assertFileExists($layout->code($job['id']).'/vendor/autoload.php');
            $this->assertFileDoesNotExist($root.'/active-release.json');
            $this->assertSame('persistent-environment', file_get_contents($root.'/.env'));
        } finally {
            (new Filesystem)->deleteDirectory($parent);
        }
    }

    public function test_admin_response_contains_a_dom_handoff_even_if_the_browser_event_is_lost(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->actingAs(User::where('email', 'a@a.com')->firstOrFail());
        $job = ['id' => str_repeat('a', 32), 'token' => str_repeat('b', 64)];
        $manager = Mockery::mock(new DeploymentManager);
        $manager->shouldReceive('status')->andReturn([]);
        $manager->shouldReceive('prepare')->once()->andReturn($job);
        $this->instance(DeploymentManager::class, $manager);
        Livewire::test(ManageDeployment::class)->call('deploy')->assertSet('pendingDeployment', $job)
            ->assertSee('data-deployment-job', false)->assertDispatched('deployment-ready', id: $job['id'], token: $job['token']);
    }

    public function test_refresh_dispatches_the_same_status_on_every_click(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->actingAs(User::where('email', 'a@a.com')->firstOrFail());
        $manager = Mockery::mock(new DeploymentManager);
        $manager->shouldReceive('status')->andReturn(['state' => 'prepared', 'message' => 'unchanged prepared state']);
        $this->instance(DeploymentManager::class, $manager);
        $page = Livewire::test(ManageDeployment::class);
        $page->call('refreshDeploymentStatus')->assertDispatched('deployment-status-updated', state: 'prepared', message: 'unchanged prepared state');
        $page->call('refreshDeploymentStatus')->assertDispatched('deployment-status-updated', state: 'prepared', message: 'unchanged prepared state');
    }

    public function test_preparation_error_exposes_only_safe_diagnostics(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->actingAs(User::where('email', 'a@a.com')->firstOrFail());
        $manager = Mockery::mock(new DeploymentManager);
        $manager->shouldReceive('status')->andReturn([]);
        $manager->shouldReceive('prepare')->andThrow(new RuntimeException('private-secret-must-not-leak'));
        $this->instance(DeploymentManager::class, $manager);
        Livewire::test(ManageDeployment::class)->call('deploy')->assertSet('pendingDeployment', null)
            ->assertSee('RuntimeException')->assertDontSee('private-secret-must-not-leak');
    }
}
