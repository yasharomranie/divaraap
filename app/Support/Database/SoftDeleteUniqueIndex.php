<?php

declare(strict_types=1);

namespace App\Support\Database;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class SoftDeleteUniqueIndex
{
    public static function create(string $table, array $columns, string $name): void
    {
        $connection = DB::connection();
        if (in_array($connection->getDriverName(), ['mysql', 'mariadb'], true)) {
            Schema::table($table, function (Blueprint $blueprint) use ($columns, $name): void {
                $blueprint->unsignedTinyInteger('active_record')->nullable()
                    ->storedAs('CASE WHEN deleted_at IS NULL THEN 1 ELSE NULL END');
                $blueprint->unique([...$columns, 'active_record'], $name);
            });

            return;
        }

        $grammar = $connection->getQueryGrammar();
        $wrappedColumns = implode(', ', array_map($grammar->wrap(...), $columns));
        $connection->statement('CREATE UNIQUE INDEX '.$grammar->wrap($name).' ON '.$grammar->wrapTable($table)
            .' ('.$wrappedColumns.') WHERE '.$grammar->wrap('deleted_at').' IS NULL');
    }
}
