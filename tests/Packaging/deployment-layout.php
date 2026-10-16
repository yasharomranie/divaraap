<?php

declare(strict_types=1);

$source = dirname(__DIR__, 2);
$directory = sys_get_temp_dir().'/layout-review-'.bin2hex(random_bytes(8));
$root = $directory.'/divaraap';
$release = $root.'/releases/'.str_repeat('a', 32);
$uninstalled = $directory.'/checkout';
$nested = $root.'/unrelated/'.str_repeat('b', 32);
foreach ([$root, $release, $uninstalled, $nested] as $code) {
    mkdir($code.'/app/Support/Deployment', 0700, true);
    copy($source.'/app/Support/Deployment/DeploymentManager.php', $code.'/app/Support/Deployment/DeploymentManager.php');
}
file_put_contents($root.'/installed.lock', 'installed');
$driver = $directory.'/probe.php';
file_put_contents($driver, '<?php function config($name) { return $GLOBALS["configured"]; } $GLOBALS["configured"]=$argv[3] ?: null; require $argv[1]; require $argv[2]; try { echo (new App\\Support\\Deployment\\DeploymentManager)->layout()->root; } catch (RuntimeException $e) { echo "rejected"; }');
try {
    foreach ([[$root, '', $root], [$root, $uninstalled, $root], [$release, $release, $root], [$uninstalled, '', 'rejected'], [$nested, '', 'rejected'], [$uninstalled, $root, $root]] as [$code, $configured, $expected]) {
        $process = proc_open([PHP_BINARY, $driver, $source.'/app/Support/Deployment/ReleaseLayout.php', $code.'/app/Support/Deployment/DeploymentManager.php', $configured], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        fclose($pipes[0]);
        $output = stream_get_contents($pipes[1]);
        $error = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $status = proc_close($process);
        if ($status !== 0 || $output !== $expected) {
            throw new RuntimeException('Deployment root resolution failed: expected '.$expected.', received '.$output.' '.$error);
        }
    }
    echo 'Deployment root resolution: 6 cases passed for missing/stale config, initial installation, versioned release, uninstalled checkout, and configured test root.'.PHP_EOL;
} finally {
    $entries = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($entries as $entry) {
        $entry->isDir() ? rmdir($entry->getPathname()) : unlink($entry->getPathname());
    }
    rmdir($directory);
}
