<?php

declare(strict_types=1);
use App\Support\Deployment\ReleaseArchive;
use App\Support\Deployment\ReleaseLayout;
use App\Support\Deployment\ReleaseRunner;
use Illuminate\Contracts\Console\Kernel;

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

[$script, $mode, $root, $public, $archive, $revision] = $argv;
require dirname(__DIR__).'/app/Support/Deployment/ReleaseLayout.php';
$layout = new ReleaseLayout($root, $public);
$lock = $layout->lock();
$idFile = dirname($archive).'/release-id';

if ($mode === 'prepare') {
    $checksum = trim(file_get_contents($archive.'.sha256'));
    if (! preg_match('/\A([a-f0-9]{64})\s+cpanel-'.preg_quote($revision, '/').'\.zip\z/', $checksum, $match)
        || ! hash_equals($match[1], hash_file('sha256', $archive))) {
        throw new RuntimeException('Release checksum mismatch; live installation unchanged.');
    }
    $zip = new ZipArchive;
    if ($zip->open($archive) !== true) {
        throw new RuntimeException('Cannot open release archive.');
    }
    $metadata = json_decode($zip->getFromName('divaraap/release.json') ?: '{}', true, 32, JSON_THROW_ON_ERROR);
    $zip->close();
    if (($metadata['source_revision'] ?? null) !== $revision) {
        throw new RuntimeException('Artifact does not match the checked-out commit.');
    }
    $environment = file_get_contents($root.'/.env');
    if (! preg_match('/^APP_URL=(.+)$/m', $environment, $url)) {
        throw new RuntimeException('APP_URL is missing.');
    }
    $url = trim($url[1], " \t\r\n\"'");
    if (! filter_var($url, FILTER_VALIDATE_URL) || ! in_array(parse_url($url, PHP_URL_SCHEME), ['http', 'https'], true)) {
        throw new RuntimeException('APP_URL is invalid.');
    }
    $id = bin2hex(random_bytes(16));
    require dirname(__DIR__).'/app/Support/Deployment/ReleaseArchive.php';
    (new ReleaseArchive)->extract($archive, $layout, $id, $url);
    file_put_contents($idFile, $id);
    echo "Verified code, dependencies and assets staged.\n";
    exit;
}

if ($mode !== 'activate') {
    throw new RuntimeException('Unknown deployment command.');
}
$id = trim(file_get_contents($idFile));
$base = $layout->code($id);
if (! is_dir($root.'/deployments')) {
    mkdir($root.'/deployments', 0700, true);
}
if (! copy($public.'/index.php', $root.'/deployments/'.$id.'-index.php')) {
    throw new RuntimeException('Cannot back up current public entry point.');
}
define('LARAVEL_START', microtime(true));
require $base.'/vendor/autoload.php';
$app = require $base.'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
$job = ['id' => $id, 'state' => 'running', 'step' => 'bootstrap', 'output' => []];
$layout->write('deployment-maintenance.json', ['id' => $id, 'started' => time()]);
$result = $app->make(ReleaseRunner::class)->run($layout, $job);
if (! $result['ok']) {
    throw new RuntimeException($result['message']);
}
$dispatcher = file_get_contents(dirname(__DIR__).'/distribution/index.php');
$dispatcher = str_replace("dirname(__DIR__).'/divaraap'", var_export($root, true), $dispatcher);
$temporary = $public.'/.cpanel-index-'.$id;
if (file_put_contents($temporary, $dispatcher) === false || ! rename($temporary, $public.'/index.php')) {
    throw new RuntimeException('Release activated but public entry point could not be restored.');
}
echo 'Deployment completed: '.$revision."\n";
