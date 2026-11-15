<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\CustomerContactChange;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use PDO;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class MySqlCustomerContactConcurrencyTest extends TestCase
{
    public function test_concurrent_admin_corrections_preserve_one_version_and_one_audit(): void
    {
        if (getenv('MYSQL_CONCURRENCY_TEST') !== '1') {
            $this->markTestSkipped('Set MYSQL_CONCURRENCY_TEST=1 for disposable MySQL checks.');
        }
        $connection = config('database.connections.mysql');
        $database = 'lr_yazid_test_'.bin2hex(random_bytes(8));
        $server = new PDO("mysql:host={$connection['host']};port={$connection['port']};charset=utf8mb4",
            $connection['username'], $connection['password'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $server->exec("CREATE DATABASE `$database` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        $file = tempnam(storage_path('framework/testing'), 'contact-');
        $processes = [];
        try {
            $connection['database'] = $database;
            file_put_contents($file, json_encode($connection, JSON_THROW_ON_ERROR));
            config(['database.default' => 'mysql', 'database.connections.mysql' => $connection]);
            DB::purge('mysql');
            $this->artisan('migrate', ['--force' => true])->assertSuccessful();
            $customer = User::factory()->create(['name' => 'Original Customer', 'phone' => '+60123456789']);
            $admins = User::factory()->count(2)->create();
            foreach ($admins as $admin) {
                $admin->role = UserRole::Admin;
                $admin->save();
            }
            DB::beginTransaction();
            User::whereKey($customer->id)->lockForUpdate()->firstOrFail();
            foreach ($admins as $index => $admin) {
                $process = new Process([PHP_BINARY, base_path('tests/Support/customer-contact-worker.php'), $file,
                    (string) $customer->id, (string) $admin->id, 'Concurrent Customer '.$index], base_path(), ['APP_ENV' => 'testing']);
                $process->setTimeout(30);
                $process->start();
                $processes[] = $process;
            }
            foreach ($processes as $process) {
                $this->assertTrue($process->waitUntil(fn ($type, $output) => str_contains($output, 'READY')));
            }
            DB::commit();
            $results = [];
            foreach ($processes as $process) {
                $process->wait();
                $this->assertTrue($process->isSuccessful(), $process->getErrorOutput().$process->getOutput());
                $output = substr($process->getOutput(), strpos($process->getOutput(), '{'));
                $results[] = json_decode($output, true, flags: JSON_THROW_ON_ERROR)['result'];
            }
            sort($results);
            $this->assertSame(['conflict', 'saved'], $results);
            $this->assertSame(2, $customer->fresh()->profile_version);
            $this->assertSame(1, CustomerContactChange::count());
            $audit = CustomerContactChange::firstOrFail();
            $this->assertSame('Original Customer', $audit->previous_values['name']);
            $this->assertSame($customer->fresh()->name, $audit->new_values['name']);
            $this->assertSame('+60198765432', $customer->fresh()->phone);
        } finally {
            foreach ($processes as $process) {
                if ($process->isRunning()) {
                    $process->stop();
                }
            }
            while (DB::transactionLevel() > 0) {
                DB::rollBack();
            }
            DB::purge('mysql');
            $server->exec("DROP DATABASE `$database`");
            if (is_file($file)) {
                unlink($file);
            }
        }
    }
}
