<?php

declare(strict_types=1);

$source = dirname(__DIR__, 2);
$temporary = sys_get_temp_dir().'/cpanel-test-'.bin2hex(random_bytes(8));
mkdir($temporary.'/private', 0700, true);
mkdir($temporary.'/public', 0700, true);
file_put_contents($temporary.'/private/.env', "APP_URL=https://example.test\nAPP_KEY=preserved\n");
file_put_contents($temporary.'/private/active-release.json', '{"current":null}');
file_put_contents($temporary.'/public/index.php', 'original');
$revision = str_repeat('a', 40);
$archive = $temporary.'/cpanel-'.$revision.'.zip';
$zip = new ZipArchive;
$zip->open($archive, ZipArchive::CREATE);
foreach ([
    'divaraap/vendor/autoload.php' => '<?php',
    'divaraap/vendor/composer/installed.json' => '{"dev":false}',
    'divaraap/bootstrap/app.php' => '<?php',
    'divaraap/release.json' => json_encode(['source_revision' => $revision, 'deployment_protocol' => 1]),
    'public_html/build/manifest.json' => '{}',
    'public_html/build/app.css' => 'body {color:black}',
] as $name => $contents) {
    $zip->addFromString($name, $contents);
}
$zip->close();
$checksum = hash_file('sha256', $archive).'  '.basename($archive)."\n";
$run = static function (string $requested) use ($source, $temporary, $archive): int {
    $process = proc_open([PHP_BINARY, $source.'/scripts/cpanel-release.php', 'prepare', $temporary.'/private', $temporary.'/public', $archive, $requested], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
    fclose($pipes[0]);
    stream_get_contents($pipes[1]);
    stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);

    return proc_close($process);
};
$assert = static function (bool $value): void {
    if (! $value) {
        throw new RuntimeException('cPanel release test failed.');
    }
};
file_put_contents($archive.'.sha256', str_repeat('0', 64).'  '.basename($archive));
$assert($run($revision) !== 0);
$assert(! is_dir($temporary.'/private/releases'));
file_put_contents($archive.'.sha256', $checksum);
$assert($run(str_repeat('b', 40)) !== 0);
$assert(! is_dir($temporary.'/private/releases'));
$assert($run($revision) === 0);
$id = trim(file_get_contents($temporary.'/release-id'));
$assert(is_file($temporary.'/private/releases/'.$id.'/vendor/autoload.php'));
$assert(is_file($temporary.'/public/_releases/'.$id.'/build/app.css'));
$runtime = require $temporary.'/private/releases/'.$id.'/bootstrap/hosting-runtime.php';
$assert($runtime['root'] === $temporary.'/private');
$assert($runtime['asset_url'] === 'https://example.test/_releases/'.$id);
$assert(file_get_contents($temporary.'/public/index.php') === 'original');
$assert(file_get_contents($temporary.'/private/active-release.json') === '{"current":null}');
$assert(str_contains(file_get_contents($temporary.'/private/.env'), 'APP_KEY=preserved'));
echo "cPanel release: checksum/revision rejection, paired assets, unchanged live state passed.\n";
