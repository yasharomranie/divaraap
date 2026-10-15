<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Support\Installation\WebInstaller;
use Illuminate\Filesystem\Filesystem;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class WebInstallerSafetyTest extends TestCase
{
    private string $base;

    private WebInstaller $installer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->base = sys_get_temp_dir().'/divaraap-installer-'.bin2hex(random_bytes(8));
        mkdir($this->base.'/storage', 0755, true);
        mkdir($this->base.'/bootstrap/cache', 0755, true);
        file_put_contents($this->base.'/install.token', hash('sha256', 'test-installation-code'));
        $this->installer = new WebInstaller($this->base, $this->base.'/public');
    }

    protected function tearDown(): void
    {
        (new Filesystem)->deleteDirectory($this->base);
        parent::tearDown();
    }

    public function test_installation_code_is_checked_against_a_hash(): void
    {
        $this->assertTrue($this->installer->available());
        $this->assertTrue($this->installer->authorized('test-installation-code'));
        $this->assertFalse($this->installer->authorized('wrong-code'));
        $this->assertNotSame('test-installation-code', file_get_contents($this->base.'/install.token'));
    }

    public function test_existing_environment_cannot_be_overwritten(): void
    {
        file_put_contents($this->base.'/.env', 'existing-secret');
        $this->assertFalse($this->installer->available());
        $this->assertFalse($this->installer->authorized('test-installation-code'));
        try {
            $this->installer->install($this->input());
            $this->fail('Existing installation accepted.');
        } catch (RuntimeException) {
            $this->assertSame('existing-secret', file_get_contents($this->base.'/.env'));
        }
    }

    public function test_installed_marker_blocks_reinstallation(): void
    {
        file_put_contents($this->base.'/installed.lock', 'installed');
        $this->assertFalse($this->installer->available());
        $this->assertFalse($this->installer->authorized('test-installation-code'));
    }

    public function test_unfinished_installation_requires_explicit_recovery(): void
    {
        file_put_contents($this->base.'/.env', 'preserved');
        file_put_contents($this->base.'/installation.pending', 'pending');
        try {
            $this->installer->install($this->input());
            $this->fail('Unfinished installation accepted.');
        } catch (RuntimeException) {
            $this->assertSame('preserved', file_get_contents($this->base.'/.env'));
            $this->assertFileExists($this->base.'/installation.pending');
            $this->assertFileDoesNotExist($this->base.'/installed.lock');
        }
    }

    public function test_newlines_and_url_credentials_are_rejected_before_writing_environment(): void
    {
        foreach ([['db_password' => "password\nAPP_DEBUG=true"], ['site_url' => 'https://user@example.com'], ['admin_confirmation' => 'different']] as $override) {
            try {
                $this->installer->install(array_replace($this->input(), $override));
                $this->fail('Invalid installation data accepted.');
            } catch (RuntimeException) {
                $this->assertFileDoesNotExist($this->base.'/.env');
                $this->assertFileDoesNotExist($this->base.'/installation.pending');
            }
        }
    }

    private function input(): array
    {
        return [
            'token' => 'test-installation-code', 'site_name' => 'سایت آزمایشی', 'site_url' => 'https://example.com',
            'db_host' => 'localhost', 'db_port' => '3306', 'db_name' => 'empty_testing', 'db_user' => 'testing', 'db_password' => 'test-password',
            'admin_name' => 'مدیر', 'admin_email' => 'admin@example.test', 'admin_password' => 'InstallerTest2026!', 'admin_confirmation' => 'InstallerTest2026!',
        ];
    }
}
