<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use LogicException;
use Tests\TestCase;

class TestDatabaseIsolationTest extends TestCase
{
    public function test_default_connection_is_disposable_with_foreign_keys_enabled(): void
    {
        $this->assertSame('testing', app()->environment());
        $this->assertSame('sqlite', DB::connection()->getDriverName());
        $this->assertSame(':memory:', DB::connection()->getDatabaseName());
        $this->assertSame(1, (int) DB::selectOne('PRAGMA foreign_keys')->foreign_keys);
        $this->assertSame(['sqlite'], array_keys(config('database.connections')));
        $this->assertSame(2, DB::table('bienes')->count());
    }

    /** @dataProvider forbiddenConnections */
    public function test_explicit_unsafe_connections_are_rejected_before_connecting(array $configuration): void
    {
        config(['database.connections.unsafe_test' => $configuration]);
        $this->expectException(LogicException::class);
        DB::connection('unsafe_test')->getPdo();
    }

    public static function forbiddenConnections(): array
    {
        return [
            'MySQL' => [['driver' => 'mysql', 'database' => 'patrimonio_entrega']],
            'PostgreSQL' => [['driver' => 'pgsql', 'database' => 'production']],
            'SQL Server' => [['driver' => 'sqlsrv', 'database' => 'production']],
            'SQLite file' => [['driver' => 'sqlite', 'database' => 'must-not-be-created.sqlite']],
            'SQLite URL to disk' => [['driver' => 'sqlite', 'database' => ':memory:', 'url' => 'sqlite:///must-not-be-created.sqlite']],
        ];
    }
}
