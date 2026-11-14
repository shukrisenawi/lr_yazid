<?php

namespace Tests\Feature;

use App\Models\AppointmentService;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use PDO;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class MySqlSlotGenerationConcurrencyTest extends TestCase
{
    public static function races(): array
    {
        return ['two generators' => [false], 'generator and manual creation' => [true]];
    }

    #[DataProvider('races')]
    public function test_service_lock_prevents_overlapping_concurrent_slot_creation(bool $manual): void
    {
        if (getenv('MYSQL_CONCURRENCY_TEST') !== '1') {
            $this->markTestSkipped('Set MYSQL_CONCURRENCY_TEST=1 to test real MySQL locking in a temporary database.');
        }
        $connection = config('database.connections.mysql');
        $database = 'lr_yazid_test_'.bin2hex(random_bytes(8));
        $server = new PDO("mysql:host={$connection['host']};port={$connection['port']};charset=utf8mb4",
            $connection['username'], $connection['password'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $server->exec("CREATE DATABASE `$database` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        $file = tempnam(storage_path('framework/testing'), 'slots-');
        $processes = [];
        try {
            $connection['database'] = $database;
            file_put_contents($file, json_encode($connection, JSON_THROW_ON_ERROR));
            config(['database.default' => 'mysql', 'database.connections.mysql' => $connection]);
            DB::purge('mysql');
            $this->artisan('migrate', ['--force' => true])->assertSuccessful();
            $admin = User::factory()->create();
            $service = AppointmentService::create(['name' => 'QA service', 'location' => 'QA venue']);
            DB::beginTransaction();
            AppointmentService::whereKey($service->id)->lockForUpdate()->firstOrFail();
            foreach (['generate', $manual ? 'manual' : 'generate'] as $mode) {
                $process = new Process([PHP_BINARY, base_path('tests/Support/slot-generator-worker.php'), $file, (string) $service->id, (string) $admin->id, $mode], base_path(), ['APP_ENV' => 'testing']);
                $process->setTimeout(30);
                $process->start();
                $processes[] = $process;
            }
            foreach ($processes as $process) {
                $this->assertTrue($process->waitUntil(fn ($type, $output) => str_contains($output, 'READY')));
            }
            DB::commit();
            foreach ($processes as $process) {
                $process->wait();
                $this->assertTrue($process->isSuccessful(), $process->getErrorOutput().$process->getOutput());
                $output = substr($process->getOutput(), strpos($process->getOutput(), '{'));
                $this->assertContains(json_decode($output, true, flags: JSON_THROW_ON_ERROR)['result'], ['generated', 'manual', 'rejected']);
            }
            $slots = $service->slots()->orderBy('starts_at')->get();
            $this->assertContains($slots->count(), $manual ? [1, 2] : [2]);
            for ($index = 1; $index < $slots->count(); $index++) {
                $this->assertTrue($slots[$index]->starts_at->gte($slots[$index - 1]->ends_at));
            }
            $this->assertSame($manual ? 1 : 2, $service->schedules()->count());
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
