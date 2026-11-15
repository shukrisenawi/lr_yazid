<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use PDO;

/**
 * Run the same display/parity/payload regressions against real MySQL, without
 * ever migrating or truncating the developer's configured business database.
 */
class MySqlStorefrontPerformanceTest extends StorefrontPerformanceTest
{
    private ?PDO $testServer = null;

    private ?string $testDatabase = null;

    public function refreshDatabase(): void
    {
        if (getenv('MYSQL_CONCURRENCY_TEST') !== '1') {
            $this->markTestSkipped('Set MYSQL_CONCURRENCY_TEST=1 for isolated MySQL checks.');
        }
        $connection = config('database.connections.mysql');
        $this->testDatabase = 'lr_yazid_test_'.bin2hex(random_bytes(8));
        $this->testServer = new PDO("mysql:host={$connection['host']};port={$connection['port']};charset=utf8mb4",
            $connection['username'], $connection['password'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $this->testServer->exec("CREATE DATABASE `$this->testDatabase` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        $connection['database'] = $this->testDatabase;
        config(['database.default' => 'mysql', 'database.connections.mysql' => $connection]);
        DB::purge('mysql');
        $this->artisan('migrate', ['--force' => true])->assertSuccessful();
    }

    protected function tearDown(): void
    {
        try {
            if ($this->testServer !== null && $this->testDatabase !== null) {
                DB::purge('mysql');
                $this->testServer->exec("DROP DATABASE `$this->testDatabase`");
            }
        } finally {
            parent::tearDown();
        }
    }
}
