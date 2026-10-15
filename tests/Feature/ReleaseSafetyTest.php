<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Support\Deployment\ReleaseArchive;
use App\Support\Deployment\ReleaseLayout;
use Illuminate\Filesystem\Filesystem;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use ZipArchive;

final class ReleaseSafetyTest extends TestCase
{
    private string $directory;

    private ReleaseLayout $layout;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir().'/release-test-'.bin2hex(random_bytes(8));
        mkdir($this->directory.'/divaraap/storage', 0755, true);
        mkdir($this->directory.'/public_html', 0755, true);
        $this->layout = new ReleaseLayout($this->directory.'/divaraap', $this->directory.'/public_html');
        file_put_contents($this->layout->root.'/.env', 'unchanged-secret');
        file_put_contents($this->layout->root.'/storage/upload.jpg', 'unchanged-upload');
    }

    protected function tearDown(): void
    {
        (new Filesystem)->deleteDirectory($this->directory);
    }

    private function archive(array $files): string
    {
        $path = $this->directory.'/release.zip';
        $zip = new ZipArchive;
        $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        foreach ($files as $name => $contents) {
            $zip->addFromString($name, $contents);
        }
        $zip->close();

        return $path;
    }

    public function test_protected_and_traversal_paths_never_replace_live_files(): void
    {
        foreach (['divaraap/.env', 'divaraap/storage/upload.jpg', 'divaraap/../escape.php', 'public_html/shell.php', 'divaraap/bootstrap/hosting-runtime.php'] as $path) {
            try {
                (new ReleaseArchive)->extract($this->archive([$path => 'malicious']), $this->layout, bin2hex(random_bytes(16)), 'https://example.test');
                $this->fail('Unsafe archive accepted: '.$path);
            } catch (RuntimeException) {
                $this->assertSame('unchanged-secret', file_get_contents($this->layout->root.'/.env'));
                $this->assertSame('unchanged-upload', file_get_contents($this->layout->root.'/storage/upload.jpg'));
                $this->assertFileDoesNotExist($this->layout->root.'/active-release.json');
            }
        }
    }

    public function test_complete_release_is_staged_and_activation_can_return_to_initial_code(): void
    {
        $id = bin2hex(random_bytes(16));
        $files = ['divaraap/vendor/autoload.php' => '<?php', 'divaraap/vendor/composer/installed.json' => '{"dev":false}', 'divaraap/bootstrap/app.php' => '<?php', 'divaraap/release.json' => '{"deployment_protocol":1}', 'public_html/build/manifest.json' => '{}'];
        (new ReleaseArchive)->extract($this->archive($files), $this->layout, $id, 'https://example.test');
        $this->assertFileDoesNotExist($this->layout->root.'/active-release.json');
        $runtime = require $this->layout->root.'/releases/'.$id.'/bootstrap/hosting-runtime.php';
        $this->assertSame($this->layout->root, $runtime['root']);
        $this->assertSame('https://example.test/_releases/'.$id, $runtime['asset_url']);
        mkdir($this->layout->root.'/vendor');
        mkdir($this->layout->root.'/bootstrap');
        file_put_contents($this->layout->root.'/vendor/autoload.php', '<?php');
        file_put_contents($this->layout->root.'/bootstrap/app.php', '<?php');
        $this->layout->activate($id);
        $this->assertSame($id, $this->layout->read('active-release.json')['current']);
        $this->layout->rollback();
        $this->assertNull($this->layout->read('active-release.json')['current']);
        $this->assertSame('unchanged-secret', file_get_contents($this->layout->root.'/.env'));
    }

    public function test_missing_dependency_fails_without_switching_release(): void
    {
        $this->expectException(RuntimeException::class);
        try {
            (new ReleaseArchive)->extract($this->archive(['divaraap/release.json' => '{"deployment_protocol":1}']), $this->layout, bin2hex(random_bytes(16)), 'https://example.test');
        } finally {
            $this->assertFileDoesNotExist($this->layout->root.'/active-release.json');
        }
    }

    public function test_cleanup_preserves_current_previous_and_persistent_data(): void
    {
        $ids = [bin2hex(random_bytes(16)), bin2hex(random_bytes(16)), bin2hex(random_bytes(16))];
        foreach ($ids as $id) {
            mkdir($this->layout->root.'/releases/'.$id.'/vendor', 0755, true);
            mkdir($this->layout->root.'/releases/'.$id.'/bootstrap', 0755, true);
            mkdir($this->layout->public.'/_releases/'.$id, 0755, true);
            file_put_contents($this->layout->root.'/releases/'.$id.'/vendor/autoload.php', '<?php');
            file_put_contents($this->layout->root.'/releases/'.$id.'/bootstrap/app.php', '<?php');
            $this->layout->activate($id);
        }
        $this->layout->pruneUnused();
        $this->assertDirectoryDoesNotExist($this->layout->root.'/releases/'.$ids[0]);
        $this->assertDirectoryDoesNotExist($this->layout->public.'/_releases/'.$ids[0]);
        $this->assertDirectoryExists($this->layout->root.'/releases/'.$ids[1]);
        $this->assertDirectoryExists($this->layout->root.'/releases/'.$ids[2]);
        $this->assertSame('unchanged-secret', file_get_contents($this->layout->root.'/.env'));
        $this->assertSame('unchanged-upload', file_get_contents($this->layout->root.'/storage/upload.jpg'));
    }

    public function test_concurrent_deployment_is_rejected(): void
    {
        $lock = $this->layout->lock();
        try {
            $this->expectException(RuntimeException::class);
            $this->layout->lock();
        } finally {
            fclose($lock);
        }
    }
}
