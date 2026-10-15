<?php

declare(strict_types=1);

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Lang;

$root = dirname(__DIR__);
require $root.'/vendor/autoload.php';
$app = require $root.'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

function translationLeaves(array $values, string $prefix = ''): array
{
    $result = [];
    foreach ($values as $key => $value) {
        $path = $prefix === '' ? (string) $key : $prefix.'.'.$key;
        if (is_array($value)) {
            $result += translationLeaves($value, $path);
        } else {
            $result[$path] = $value;
        }
    }

    return $result;
}

function placeholderNames(string $value): array
{
    preg_match_all('/(?<![A-Za-z]):([A-Za-z_][A-Za-z0-9_]*)/', $value, $matches);
    $tokens = array_values(array_unique(array_map(static function (string $token): string {
        $token = preg_replace('/(?<=[a-z_])(?:MB|GB|KB)$/', '', $token);

        return strtoupper($token) === $token ? strtolower($token) : lcfirst($token);
    }, $matches[1])));
    sort($tokens);

    return $tokens;
}

$errors = [];
$catalogs = [];
$keys = 0;
foreach (glob($root.'/Modules/*/lang/en/*.php') as $file) {
    $catalogs[] = [$file, str_replace('/lang/en/', '/lang/fa/', $file)];
}
foreach (glob($root.'/vendor/laravel/framework/src/Illuminate/Translation/lang/en/*.php') as $file) {
    $catalogs[] = [$file, $root.'/lang/fa/'.basename($file)];
}
foreach ($catalogs as [$englishFile, $persianFile]) {
    $relative = substr($englishFile, strlen($root) + 1);
    if (! is_file($persianFile)) {
        $errors[] = 'Missing Persian catalog: '.$relative;

        continue;
    }
    $english = translationLeaves(require $englishFile);
    $persian = translationLeaves(require $persianFile);
    $keys += count($english);
    foreach ($english as $key => $value) {
        if (! array_key_exists($key, $persian)) {
            $errors[] = $relative.': missing '.$key;
        } elseif (is_string($value) && (! is_string($persian[$key]) || trim($persian[$key]) === '' || placeholderNames($value) !== placeholderNames($persian[$key]))) {
            $errors[] = $relative.': invalid translation or placeholders for '.$key;
        }
    }
}
$json = json_decode(file_get_contents($root.'/lang/fa.json'), true, flags: JSON_THROW_ON_ERROR);
foreach ($json as $english => $persian) {
    if (! is_string($persian) || trim($persian) === '' || placeholderNames($english) !== placeholderNames($persian)) {
        $errors[] = 'lang/fa.json: invalid translation or placeholders for '.$english;
    }
}
$vendorKeys = 0;
foreach (glob($root.'/vendor/filament/*/resources/lang/en') as $directory) {
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS)) as $file) {
        if ($file->getExtension() !== 'php') {
            continue;
        }
        $package = basename(dirname($directory, 3));
        $namespace = match ($package) {
            'support' => 'filament',
            'filament' => 'filament-panels',
            default => 'filament-'.$package,
        };
        $group = substr($file->getPathname(), strlen($directory) + 1, -4);
        foreach (translationLeaves(require $file->getPathname()) as $key => $value) {
            $vendorKeys++;
            $translationKey = $namespace.'::'.$group.'.'.$key;
            if (! Lang::hasForLocale($translationKey, 'fa')) {
                $errors[] = 'Missing Persian vendor key: '.$translationKey;
            } elseif (is_string($value) && placeholderNames($value) !== placeholderNames((string) Lang::get($translationKey, [], 'fa', false))) {
                $errors[] = 'Vendor placeholder mismatch: '.$translationKey;
            }
        }
    }
}
$literalKeys = [];
foreach ([$root.'/Modules', $root.'/app', $root.'/resources/views'] as $directory) {
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS)) as $file) {
        if (! str_ends_with($file->getPathname(), '.php') || str_contains($file->getPathname(), '/lang/')) {
            continue;
        }
        preg_match_all('/(?:__|trans|trans_choice|@lang)\(\s*[\'\"]([A-Za-z][A-Za-z0-9_-]*::[A-Za-z0-9_.-]+)[\'\"]\s*[,)]/', file_get_contents($file->getPathname()), $matches);
        foreach ($matches[1] as $key) {
            $literalKeys[$key] = substr($file->getPathname(), strlen($root) + 1);
        }
    }
}
foreach ($literalKeys as $key => $file) {
    foreach (['fa', 'en'] as $locale) {
        if (! Lang::hasForLocale($key, $locale)) {
            $errors[] = $file.': unresolved '.$locale.' key '.$key;
        }
    }
}
echo json_encode(['catalogs' => count($catalogs), 'catalog_keys' => $keys, 'json_keys' => count($json), 'vendor_keys' => $vendorKeys, 'literal_keys' => count($literalKeys), 'errors' => $errors], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR), PHP_EOL;
exit($errors === [] ? 0 : 1);
