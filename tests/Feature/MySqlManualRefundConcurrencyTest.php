<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\ManualRefund;
use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PDO;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class MySqlManualRefundConcurrencyTest extends TestCase
{
    public static function races(): array
    {
        return ['duplicate submission' => [true], 'cumulative payment cap' => [false]];
    }

    #[DataProvider('races')]
    public function test_independent_processes_do_not_duplicate_or_over_record_refunds(bool $duplicate): void
    {
        if (getenv('MYSQL_CONCURRENCY_TEST') !== '1') {
            $this->markTestSkipped('Set MYSQL_CONCURRENCY_TEST=1 for disposable MySQL checks.');
        }
        $connection = config('database.connections.mysql');
        $database = 'lr_yazid_test_'.bin2hex(random_bytes(8));
        $server = new PDO("mysql:host={$connection['host']};port={$connection['port']};charset=utf8mb4",
            $connection['username'], $connection['password'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $server->exec("CREATE DATABASE `$database` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        $file = tempnam(storage_path('framework/testing'), 'refund-');
        $processes = [];
        try {
            $connection['database'] = $database;
            file_put_contents($file, json_encode($connection, JSON_THROW_ON_ERROR));
            config(['database.default' => 'mysql', 'database.connections.mysql' => $connection]);
            DB::purge('mysql');
            $this->artisan('migrate', ['--force' => true])->assertSuccessful();
            $user = User::factory()->create();
            $admin = User::factory()->create();
            $admin->role = UserRole::Admin;
            $admin->save();
            $order = Order::create(['user_id' => $user->id, 'reference' => 'REFUND-RACE', 'checkout_key' => Str::uuid(),
                'customer_name' => $user->name, 'customer_email' => $user->email, 'customer_phone' => '+60123456789',
                'subtotal_sen' => 10000, 'shipping_fee_sen' => 0, 'total_sen' => 10000,
                'payment_status' => 'paid', 'expires_at' => now()->subDay()]);
            $payment = $order->payments()->create(['reference' => 'SYNTHETIC-REFUND-QA', 'environment' => 'sandbox', 'status' => 'paid',
                'amount_sen' => 10000, 'secret_key_snapshot' => 'not-real', 'paid_at' => now()->subDay(),
                'checked_at' => now(), 'transaction_reference' => 'SYNTHETIC-TX']);
            $data = ['idempotency_key' => (string) Str::uuid(), 'payment_id' => $payment->id, 'amount' => '60.00',
                'external_reference' => 'SYNTHETIC-REFUND-ONE', 'method' => 'bank_transfer',
                'refunded_at' => now()->format('Y-m-d\TH:i'), 'reason' => 'Disposable database synthetic refund test', 'confirmed' => true];
            DB::beginTransaction();
            User::whereKey($user->id)->lockForUpdate()->firstOrFail();
            for ($index = 0; $index < 2; $index++) {
                $input = $index === 1 && ! $duplicate ? [...$data, 'idempotency_key' => (string) Str::uuid(), 'external_reference' => 'SYNTHETIC-REFUND-TWO'] : $data;
                $process = new Process([PHP_BINARY, base_path('tests/Support/manual-refund-worker.php'), $file,
                    (string) $order->id, (string) $admin->id, json_encode($input, JSON_THROW_ON_ERROR)], base_path(), ['APP_ENV' => 'testing']);
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
            $this->assertSame($duplicate ? ['recorded', 'recorded'] : ['recorded', 'rejected'], $results);
            $this->assertSame(1, ManualRefund::count());
            $this->assertSame(6000, (int) ManualRefund::sum('amount_sen'));
            $this->assertSame('paid', $order->fresh()->payment_status);
            $this->assertSame('paid', $payment->fresh()->status);
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
