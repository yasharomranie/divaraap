<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Database\MariaDbConnection;
use Illuminate\Database\MySqlConnection;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\Grammars\MariaDbGrammar;
use Illuminate\Database\Schema\Grammars\MySqlGrammar;
use LogicException;
use Tests\TestCase;

final class InnoDbSchemaTest extends TestCase
{
    public function test_mysql_and_mariadb_table_statements_explicitly_request_innodb(): void
    {
        foreach (['mysql', 'mariadb'] as $driver) {
            $config = config('database.connections.'.$driver);
            $pdo = static fn () => throw new LogicException('SQL compilation must not connect to a database.');
            $connection = $driver === 'mysql' ? new MySqlConnection($pdo, 'testing', '', $config) : new MariaDbConnection($pdo, 'testing', '', $config);
            $connection->setSchemaGrammar($driver === 'mysql' ? new MySqlGrammar($connection) : new MariaDbGrammar($connection));
            $blueprint = new Blueprint($connection, 'password_reset_tokens', function (Blueprint $table): void {
                $table->create();
                $table->string('email')->primary();
                $table->string('token');
            });
            $sql = implode(';', $blueprint->toSql());
            $this->assertStringContainsString('engine = InnoDB', $sql);
            $this->assertStringContainsString('varchar(255)', $sql);
            $this->assertStringContainsString('utf8mb4', $sql);
        }
    }
}
