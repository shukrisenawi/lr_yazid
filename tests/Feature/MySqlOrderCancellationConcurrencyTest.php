<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Cart;
use App\Models\OrderCancellation;
use App\Models\Product;
use App\Models\User;
use App\Services\CheckoutService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PDO;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class MySqlOrderCancellationConcurrencyTest extends TestCase
{
    public static function races(): array
    {
        return ['duplicate cancellation' => [false], 'cancellation versus verified payment' => [true]];
    }

    #[DataProvider('races')]
    public function test_order_and_stock_remain_consistent_under_independent_processes(bool $settlement): void
    {
        if (getenv('MYSQL_CONCURRENCY_TEST') !== '1') {
            $this->markTestSkipped('Set MYSQL_CONCURRENCY_TEST=1 for disposable MySQL checks.');
        }
        $connection = config('database.connections.mysql');
        $database = 'lr_yazid_test_'.bin2hex(random_bytes(8));
        $server = new PDO("mysql:host={$connection['host']};port={$connection['port']};charset=utf8mb4",
            $connection['username'], $connection['password'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $server->exec("CREATE DATABASE `$database` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        $file = tempnam(storage_path('framework/testing'), 'cancel-');
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
            $product = Product::create(['name' => 'Cancel race', 'slug' => 'cancel-race', 'sku' => 'CANCEL', 'price_sen' => 1000, 'stock' => 1, 'status' => 'active']);
            $address = $user->addresses()->create(['full_name' => 'Test', 'phone' => '+60123456789',
                'line1' => 'QA', 'city' => 'Shah Alam', 'state' => 'Selangor', 'postcode' => '40100']);
            Cart::create(['user_id' => $user->id])->items()->create(['product_id' => $product->id, 'item_key' => "$product->id:0", 'quantity' => 1]);
            $order = app(CheckoutService::class)->create($user, (string) Str::uuid(), $address->id, null);
            $payment = $order->payments()->create(['reference' => 'CONCURRENTTEST', 'environment' => 'sandbox',
                'secret_key_snapshot' => 'not-real', 'amount_sen' => $order->total_sen, 'status' => 'pending', 'bill_code' => 'testconcurrentbill']);
            DB::beginTransaction();
            User::whereKey($user->id)->lockForUpdate()->firstOrFail();
            $cancel = [PHP_BINARY, base_path('tests/Support/order-cancellation-worker.php'), $file, (string) $order->id, (string) $admin->id];
            $commands = [$cancel, $settlement ? [PHP_BINARY, base_path('tests/Support/payment-worker.php'), $file, (string) $payment->id] : $cancel];
            foreach ($commands as $command) {
                $process = new Process($command, base_path(), ['APP_ENV' => 'testing']);
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
            $this->assertSame(0, $order->reservations()->where('status', 'active')->count());
            $this->assertSame(1, $order->items()->count());
            if (! $settlement) {
                sort($results);
                $this->assertSame(['cancelled', 'rejected'], $results);
                $this->assertSame('cancelled', $order->fresh()->payment_status);
                $this->assertSame(1, OrderCancellation::count());
                $this->assertSame(1, $product->fresh()->stock);
            } else {
                $this->assertSame('paid', $order->fresh()->payment_status);
                if ($payment->fresh()->requires_review) {
                    $this->assertContains('requires_review', $results);
                    $this->assertSame(1, OrderCancellation::count());
                    $this->assertSame(1, $product->fresh()->stock);
                    $this->assertSame('cancelled', $order->fresh()->fulfillment_status);
                    $this->assertSame(0, $order->reservations()->where('status', 'consumed')->count());
                } else {
                    $this->assertContains('rejected', $results);
                    $this->assertSame(0, OrderCancellation::count());
                    $this->assertSame(0, $product->fresh()->stock);
                    $this->assertSame(1, $order->reservations()->where('status', 'consumed')->count());
                }
            }
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
