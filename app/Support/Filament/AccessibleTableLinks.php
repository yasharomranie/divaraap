<?php

declare(strict_types=1);

namespace App\Support\Filament;

use Illuminate\Database\Eloquent\Model;

final class AccessibleTableLinks
{
    public static function attributes(Model|array $record): array
    {
        $title = $record instanceof Model
            ? ($record->getAttribute('title') ?: $record->getAttribute('name') ?: $record->getAttribute('email'))
            : ($record['title'] ?? $record['name'] ?? $record['email'] ?? '');
        $key = $record instanceof Model ? $record->getKey() : ($record['id'] ?? '');

        return ['aria-label' => e(__('admin::admin.open_record', ['record' => trim((string) $title.' #'.$key)]))];
    }
}
