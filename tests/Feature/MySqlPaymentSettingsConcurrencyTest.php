<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\PaymentCallback;
use App\Models\PaymentGatewaySetting;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use PDO;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Process\InputStream;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class MySqlPaymentSettingsConcurrencyTest extends TestCase
{
    public static function versions(): array
    {
        return ['first configuration' => [0], 'existing configuration' => [1]];
    }

    #[DataProvider('versions')]
    public function test_parallel_settings_updates_preserve_one_version_without_lost_credentials(int $version): void
    {
        if (getenv('MYSQL_CONCURRENCY_TEST') !== '1') {
            $this->markTestSkipped('Set MYSQL_CONCURRENCY_TEST=1 for isolated MySQL concurrency checks.');
        }
        $connection = config('database.connections.mysql');
        $database = 'lr_yazid_test_'.bin2hex(random_bytes(8));
        $server = new PDO("mysql:host={$connection['host']};port={$connection['port']};charset=utf8mb4",
            $connection['username'], $connection['password'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $server->exec("CREATE DATABASE `$database` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        $file = tempnam(storage_path('framework/testing'), 'gateway-settings-');
        $processes = [];
        try {
            $connection['database'] = $database;
            file_put_contents($file, json_encode($connection, JSON_THROW_ON_ERROR));
            config(['database.default' => 'mysql', 'database.connections.mysql' => $connection]);
            DB::purge('mysql');
            $this->artisan('migrate', ['--force' => true])->assertSuccessful();
            $admin = User::factory()->create();
            $admin->role = UserRole::Admin;
            $admin->save();
            if ($version === 1) {
                PaymentGatewaySetting::create(['id' => 1, 'environment' => 'sandbox', 'enabled' => false,
                    'sandbox_secret_key' => 'initial-isolated-settings-key', 'sandbox_category_code' => 'INITIAL', 'version' => 1]);
            }
            $inputs = [];
            foreach (['first', 'second'] as $name) {
                $input = new InputStream;
                $process = new Process([PHP_BINARY, base_path('tests/Support/payment-settings-worker.php'), $file,
                    (string) $admin->id, (string) $version, $name], base_path(), ['APP_ENV' => 'testing'], $input);
                $process->setTimeout(30);
                $process->start();
                $inputs[] = $input;
                $processes[] = $process;
            }
            foreach ($processes as $process) {
                $this->assertTrue($process->waitUntil(fn ($type, $output) => str_contains($output, 'READY')));
            }
            foreach ($inputs as $input) {
                $input->write("GO\n");
                $input->close();
            }
            $results = [];
            foreach ($processes as $process) {
                $process->wait();
                $this->assertTrue($process->isSuccessful(), $process->getErrorOutput().$process->getOutput());
                $results[] = json_decode(substr($process->getOutput(), strpos($process->getOutput(), '{')), true, flags: JSON_THROW_ON_ERROR)['result'];
            }
            sort($results);
            $this->assertSame(['saved', 'stale'], $results);
            $settings = PaymentGatewaySetting::current();
            $this->assertSame($version + 1, $settings->version);
            $this->assertContains($settings->sandbox_secret_key, ['isolated-settings-key-first', 'isolated-settings-key-second']);
            $this->assertFalse($settings->enabled);
            $this->assertSame(1, PaymentGatewaySetting::count());
            $this->assertSame(1, PaymentCallback::where('event', 'settings_updated')->count());
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
