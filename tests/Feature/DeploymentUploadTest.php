<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Support\Deployment\DeploymentManager;
use App\Support\Deployment\ReleaseLayout;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Http;
use Modules\Admin\Filament\Pages\ManageDeployment;
use Modules\User\App\Models\User;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;
use ZipArchive;

final class DeploymentUploadTest extends TestCase
{
    public function test_chunk_upload_prepares_verified_code_without_any_server_network_request(): void
    {
        $directory = sys_get_temp_dir().'/deployment-upload-'.bin2hex(random_bytes(8));
        $root = $directory.'/divaraap';
        mkdir($root, 0700, true);
        file_put_contents($root.'/installed.lock', 'installed');
        file_put_contents($root.'/recovery.token', '{}');
        file_put_contents($root.'/.env', 'persistent-environment');
        $zip = new ZipArchive;
        $zip->open($root.'/fixture.zip', ZipArchive::CREATE);
        foreach (['divaraap/vendor/autoload.php' => '<?php', 'divaraap/bootstrap/app.php' => '<?php', 'divaraap/vendor/composer/installed.json' => '{"dev":false}', 'divaraap/release.json' => '{"deployment_protocol":1}', 'public_html/build/manifest.json' => '{}'] as $name => $value) {
            $zip->addFromString($name, $value);
        }
        $zip->close();
        $bytes = file_get_contents($root.'/fixture.zip');
        config(['deployment.root' => $root]);
        Http::preventStrayRequests();
        $manager = new DeploymentManager;
        try {
            $upload = $manager->startUpload(strlen($bytes), hash('sha256', $bytes), 7);
            foreach ([[8, 0, 'Invalid uploaded package.'], [7, 1, 'Upload offset mismatch.']] as [$owner, $offset, $expected]) {
                try {
                    $manager->uploadChunk($upload, $offset, base64_encode('x'), $owner);
                    $this->fail('Invalid upload mutation accepted');
                } catch (RuntimeException $exception) {
                    $this->assertSame($expected, $exception->getMessage());
                }
            }
            $offset = 0;
            foreach (str_split($bytes, 100) as $chunk) {
                $offset = $manager->uploadChunk($upload, $offset, base64_encode($chunk), 7);
            }
            $job = $manager->finishUpload($upload, 7);
            $layout = new ReleaseLayout($root, dirname($root).'/public_html');
            $saved = $layout->read('deployment-job.json');
            $this->assertSame('prepared', $saved['state']);
            $this->assertSame('admin_upload', $saved['release']['transport']);
            $this->assertSame(hash('sha256', $bytes), $saved['release']['sha256']);
            $this->assertFileExists($root.'/releases/'.$job['id'].'/vendor/autoload.php');
            $this->assertSame('persistent-environment', file_get_contents($root.'/.env'));
            $this->assertFileDoesNotExist($root.'/active-release.json');
            $bad = $manager->startUpload(3, str_repeat('a', 64), 7);
            $manager->uploadChunk($bad, 0, base64_encode('bad'), 7);
            try {
                $manager->finishUpload($bad, 7);
                $this->fail('Tampered upload accepted');
            } catch (RuntimeException $exception) {
                $this->assertSame('Uploaded package checksum mismatch.', $exception->getMessage());
            }
            $this->assertSame($job['id'], $layout->read('deployment-job.json')['id']);
        } finally {
            (new Filesystem)->deleteDirectory($directory);
        }
    }

    public function test_member_cannot_start_package_upload(): void
    {
        $this->actingAs(User::where('email', 'b@b.com')->firstOrFail());
        $this->expectException(HttpException::class);
        (new ManageDeployment)->startPackageUpload(123, str_repeat('a', 64));
    }
}
