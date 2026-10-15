<?php

declare(strict_types=1);

namespace App\Support\Deployment;

use RuntimeException;
use ZipArchive;

final class ReleaseArchive
{
    public function extract(string $archive, ReleaseLayout $layout, string $id, string $url): void
    {
        if (! preg_match('/\A[a-f0-9]{32}\z/', $id)) {
            throw new RuntimeException('Invalid release identifier.');
        }
        $zip = new ZipArchive;
        if ($zip->open($archive) !== true) {
            throw new RuntimeException('Invalid update archive.');
        }
        $code = $layout->root.'/releases/'.$id;
        $assets = $layout->public.'/_releases/'.$id;
        if (file_exists($code) || file_exists($assets)) {
            throw new RuntimeException('Release already exists.');
        }
        $entries = [];
        $size = 0;
        try {
            if ($zip->numFiles > 100000) {
                throw new RuntimeException('Too many archive entries.');
            }
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $stat = $zip->statIndex($i);
                $name = $stat['name'];
                $size += $stat['size'];
                $zip->getExternalAttributesIndex($i, $system, $attributes);
                if ($size > 1500000000 || preg_match('~[\\\\\x00-\x1f]|(?:^|/)\.\.(?:/|$)|^/~', $name) || (($attributes >> 16) & 0170000) === 0120000 || isset($entries[$name])) {
                    throw new RuntimeException('Unsafe archive entry.');
                }
                $entries[$name] = true;
                if (str_ends_with($name, '/')) {
                    continue;
                }
                if (str_starts_with($name, 'divaraap/')) {
                    $relative = substr($name, 9);
                    $top = explode('/', $relative)[0];
                    if (! in_array($top, ['app', 'bootstrap', 'config', 'database', 'lang', 'Modules', 'resources', 'routes', 'vendor', 'artisan', 'composer.json', 'composer.lock', 'modules_statuses.json', 'release.json'], true)
                        || str_starts_with($relative, 'bootstrap/cache/') || in_array($relative, ['bootstrap/hosting-public.php', 'bootstrap/hosting-runtime.php'], true)
                        || in_array(basename($relative), ['.env', '.user.ini', 'auth.json'], true)) {
                        throw new RuntimeException('Protected path in update archive.');
                    }
                } elseif (str_starts_with($name, 'public_html/')) {
                    $relative = substr($name, 12);
                    if (! preg_match('/\.(?:js|mjs|css|map|json|wasm|woff2?|ttf|otf|eot|svg|png|jpe?g|gif|webp|avif|ico|txt|webmanifest)\z/i', $relative) || str_contains($relative, '.php') || str_starts_with($relative, '_releases/')) {
                        throw new RuntimeException('Non-static public update entry.');
                    }
                } else {
                    throw new RuntimeException('Unexpected update layout.');
                }
            }
            foreach (array_keys($entries) as $name) {
                if (str_ends_with($name, '/')) {
                    continue;
                }
                $target = str_starts_with($name, 'divaraap/') ? $code.'/'.substr($name, 9) : $assets.'/'.substr($name, 12);
                if (! is_dir(dirname($target)) && ! mkdir(dirname($target), 0755, true)) {
                    throw new RuntimeException('Cannot create release directory.');
                }
                $input = $zip->getStream($name);
                $output = fopen($target, 'xb');
                if (! $input || ! $output || stream_copy_to_stream($input, $output) === false) {
                    throw new RuntimeException('Cannot extract release.');
                }
                fclose($input);
                fclose($output);
            }
        } finally {
            $zip->close();
        }
        foreach (['vendor/autoload.php', 'bootstrap/app.php', 'release.json'] as $required) {
            if (! is_file($code.'/'.$required)) {
                throw new RuntimeException('Incomplete production update.');
            }
        }
        if (! is_file($assets.'/build/manifest.json')) {
            throw new RuntimeException('Frontend build missing.');
        }
        $metadata = json_decode(file_get_contents($code.'/release.json'), true, 32, JSON_THROW_ON_ERROR);
        if (($metadata['deployment_protocol'] ?? null) !== 1) {
            throw new RuntimeException('Unsupported deployment protocol.');
        }
        $installed = json_decode(file_get_contents($code.'/vendor/composer/installed.json'), true, 512, JSON_THROW_ON_ERROR);
        if ($installed['dev'] ?? true) {
            throw new RuntimeException('Development dependencies in update.');
        }
        mkdir($code.'/bootstrap/cache', 0755, true);
        foreach (glob($code.'/Modules/*', GLOB_ONLYDIR) as $module) {
            if (! is_dir($module.'/resources/views')) {
                mkdir($module.'/resources/views', 0755, true);
            }
        }
        $runtime = ['root' => $layout->root, 'public' => $assets, 'asset_url' => rtrim($url, '/').'/_releases/'.$id];
        file_put_contents($code.'/bootstrap/hosting-runtime.php', "<?php\n\ndeclare(strict_types=1);\n\nreturn ".var_export($runtime, true).";\n");
        file_put_contents($assets.'/.htaccess', "Options -Indexes\n<FilesMatch \"\\.(php[0-9]?|phtml|phar)$\">\nRequire all denied\n</FilesMatch>\n");
    }
}
