<?php

declare(strict_types=1);

namespace App\Support\Deployment;

use PDO;
use RuntimeException;
use Throwable;

class DatabaseBackup
{
    public function create(PDO $pdo, string $target): void
    {
        if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) !== 'mysql') {
            throw new RuntimeException('Web deployment requires MySQL or MariaDB.');
        }
        $tables = $pdo->query('SHOW FULL TABLES')->fetchAll(PDO::FETCH_NUM);
        foreach ($tables as $table) {
            if ($table[1] !== 'BASE TABLE') {
                throw new RuntimeException('Database views require an external backup strategy.');
            }
        }
        if ((int) $pdo->query('SELECT COUNT(*) FROM information_schema.TRIGGERS WHERE TRIGGER_SCHEMA = DATABASE()')->fetchColumn() > 0) {
            throw new RuntimeException('Database triggers require an external backup strategy.');
        }
        if ((int) $pdo->query("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND ENGINE <> 'InnoDB'")->fetchColumn() > 0) {
            throw new RuntimeException('Web deployment requires transactional InnoDB tables.');
        }
        $handle = fopen($target.'.partial', 'xb');
        if (! $handle) {
            throw new RuntimeException('Cannot create private database backup.');
        }
        chmod($target.'.partial', 0600);
        $write = static function (string $sql) use ($handle): void {
            if (fwrite($handle, $sql) !== strlen($sql)) {
                throw new RuntimeException('Database backup write failed.');
            }
        };
        try {
            $pdo->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');
            $pdo->beginTransaction();
            $write("SET FOREIGN_KEY_CHECKS=0;\n");
            foreach ($tables as [$table]) {
                $quoted = '`'.str_replace('`', '``', $table).'`';
                $create = $pdo->query('SHOW CREATE TABLE '.$quoted)->fetch(PDO::FETCH_NUM)[1];
                $columns = array_values(array_filter($pdo->query('SHOW COLUMNS FROM '.$quoted)->fetchAll(PDO::FETCH_ASSOC), static fn (array $column): bool => ! str_contains($column['Extra'], 'GENERATED')));
                $names = implode(',', array_map(static fn (array $column): string => '`'.str_replace('`', '``', $column['Field']).'`', $columns));
                $write('DROP TABLE IF EXISTS '.$quoted.";\n".$create.";\n");
                $pdo->setAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, false);
                $rows = $pdo->query('SELECT '.$names.' FROM '.$quoted);
                while ($row = $rows->fetch(PDO::FETCH_NUM)) {
                    $values = implode(',', array_map(static fn ($value): string => $value === null ? 'NULL' : "X'".bin2hex((string) $value)."'", $row));
                    $write('INSERT INTO '.$quoted.' ('.$names.') VALUES ('.$values.");\n");
                }
                $rows->closeCursor();
                $pdo->setAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, true);
            }
            $write("SET FOREIGN_KEY_CHECKS=1;\n");
            $pdo->commit();
            fclose($handle);
            if (! rename($target.'.partial', $target)) {
                throw new RuntimeException('Cannot finalize database backup.');
            }
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            if (is_resource($handle)) {
                fclose($handle);
            }
            $pdo->setAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, true);
            throw $exception;
        }
    }
}
