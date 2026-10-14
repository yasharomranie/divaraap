<?php

declare(strict_types=1);

namespace App\Support\Filament;

use Closure;
use Illuminate\Support\Facades\Lang;

final class FileManagerText
{
    private static int $depth = 0;

    public static function run(Closure $action): void
    {
        self::$depth++;
        try {
            $action();
        } finally {
            self::$depth--;
        }
    }

    public static function active(): bool
    {
        return self::$depth > 0;
    }

    public static function translate(string $text): string
    {
        if (app()->getLocale() !== 'fa') {
            return $text;
        }
        foreach (['Failed to create folder' => 'failed_create_folder', 'Failed to upload file' => 'failed_to_upload_file', 'Failed to rename' => 'failed_rename', 'Failed to move' => 'failed_move', 'Failed to delete' => 'failed_delete'] as $prefix => $key) {
            if (str_starts_with($text, $prefix.': ')) {
                return __('admin::filemanager.'.$key);
            }
        }
        if (str_contains($text, "\n")) {
            return implode("\n", array_map(static fn (string $line): string => self::translate($line), explode("\n", $text)));
        }
        $messages = Lang::get('admin::filemanager', [], 'en');
        foreach ($messages as $key => $english) {
            if (strcasecmp($text, $english) === 0) {
                return __('admin::filemanager.'.$key);
            }
            if (preg_match('/:[a-z_]+/', $english) === 1) {
                $segments = preg_split('/(:[a-z_]+)/', $english, -1, PREG_SPLIT_DELIM_CAPTURE);
                $pattern = implode('', array_map(static fn (string $part): string => str_starts_with($part, ':') ? '(?<'.substr($part, 1).'>.+?)' : preg_quote($part, '/'), $segments));
                if (preg_match('/^'.$pattern.'$/u', $text, $matches) === 1) {
                    return __('admin::filemanager.'.$key, array_filter($matches, static fn ($key): bool => is_string($key), ARRAY_FILTER_USE_KEY));
                }
            }
        }

        return $text;
    }
}
