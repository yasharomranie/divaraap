<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Filesystem\Filesystem;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;

final class InstallerDiagnosticsTest extends TestCase
{
    public function test_diagnostics_require_installation_ownership_and_redact_private_values(): void
    {
        $directory = sys_get_temp_dir().'/installer-diagnostics-'.bin2hex(random_bytes(8));
        mkdir($directory.'/divaraap/vendor', 0755, true);
        mkdir($directory.'/divaraap/bootstrap', 0755, true);
        mkdir($directory.'/public_html', 0755, true);
        copy(dirname(__DIR__, 2).'/distribution/installer-diagnostics.php', $directory.'/public_html/installer-diagnostics.php');
        file_put_contents($directory.'/divaraap/install.token', hash('sha256', 'private-diagnostic-test-code'));
        file_put_contents($directory.'/divaraap/installation.pending', 'pending');
        file_put_contents($directory.'/divaraap/.env', 'DB_PASSWORD=database-test-secret');
        file_put_contents($directory.'/divaraap/vendor/autoload.php', '<?php namespace Dotenv; class Dotenv { public static function parse($contents) { return ["DB_PASSWORD" => "database-test-secret"]; } }');
        file_put_contents($directory.'/divaraap/bootstrap/app.php', '<?php throw new ErrorException("file_exists(): database-test-secret failed to open stream", 0, E_WARNING, __DIR__."/warning.php", 19);');
        file_put_contents($directory.'/driver.php', <<<'DRIVER'
<?php
$_SERVER['HTTPS'] = 'on';
$_SERVER['REQUEST_METHOD'] = 'POST';
session_name('divaraap_install_diagnostics');
session_id('diagnostic-'.bin2hex(random_bytes(12)));
session_start();
$_SESSION['csrf'] = 'known-csrf';
session_write_close();
$_POST = ['csrf' => 'known-csrf', 'token' => 'private-diagnostic-test-code'];
if ($argv[1] === 'bad-token') { $_POST['token'] = 'incorrect'; }
if ($argv[1] === 'bad-csrf') { $_POST['csrf'] = 'incorrect'; }
if ($argv[1] === 'installed') { file_put_contents(__DIR__.'/divaraap/installed.lock', 'installed'); }
ob_start();
register_shutdown_function(static function (): void { $html = ob_get_clean(); echo json_encode(['status' => http_response_code() ?: 200, 'html' => $html]); });
require __DIR__.'/public_html/installer-diagnostics.php';
DRIVER);
        try {
            foreach (['valid' => 200, 'bad-token' => 403, 'bad-csrf' => 403, 'installed' => 404] as $mode => $status) {
                $process = new Process([PHP_BINARY, $directory.'/driver.php', $mode]);
                $process->mustRun();
                $result = json_decode($process->getOutput(), true, 512, JSON_THROW_ON_ERROR);
                $this->assertSame($status, $result['status']);
                $this->assertStringNotContainsString('database-test-secret', $result['html']);
                $this->assertStringNotContainsString('private-diagnostic-test-code', $result['html']);
                if ($mode === 'valid') {
                    $this->assertStringContainsString('ErrorException', $result['html']);
                    $this->assertStringContainsString('warning.php', $result['html']);
                    $this->assertStringContainsString('[redacted]', $result['html']);
                }
            }
            $this->assertSame('DB_PASSWORD=database-test-secret', file_get_contents($directory.'/divaraap/.env'));
            $this->assertSame('pending', file_get_contents($directory.'/divaraap/installation.pending'));
        } finally {
            (new Filesystem)->deleteDirectory($directory);
        }
    }
}
