<?php

namespace Tests\Feature;

use App\Models\Cart;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Product;
use App\Models\StockReservation;
use App\Models\User;
use App\Services\CheckoutService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PDO;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class MySqlCheckoutConcurrencyTest extends TestCase
{
    public static function checkoutRaces(): array
    {
        return ['different customers' => [false, false], 'duplicate checkout key' => [true, false], 'duplicate payment callbacks' => [false, true]];
    }

    #[DataProvider('checkoutRaces')]
    public function test_two_independent_processes_keep_inventory_consistent(bool $sameUser, bool $callbacks): void
    {
        if (getenv('MYSQL_CONCURRENCY_TEST') !== '1') {
            $this->markTestSkipped('Set MYSQL_CONCURRENCY_TEST=1 to test real MySQL locking in a temporary database.');
        }
        $connection = config('database.connections.mysql');
        $database = 'lr_yazid_test_'.bin2hex(random_bytes(8));
        $server = new PDO(
            "mysql:host={$connection['host']};port={$connection['port']};charset=utf8mb4",
            $connection['username'], $connection['password'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION],
        );
        $server->exec("CREATE DATABASE `$database` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        $file = tempnam(storage_path('framework/testing'), 'checkout-');
        $processes = [];
        try {
            $connection['database'] = $database;
            file_put_contents($file, json_encode($connection, JSON_THROW_ON_ERROR));
            config(['database.default' => 'mysql', 'database.connections.mysql' => $connection]);
            DB::purge('mysql');
            $this->artisan('migrate', ['--force' => true])->assertSuccessful();
            $product = Product::create(['name' => 'Last unit', 'slug' => 'last-unit', 'sku' => 'LAST', 'price_sen' => 1000, 'stock' => 1, 'status' => 'active']);
            $buyers = [];
            for ($index = 0; $index < 2; $index++) {
                if ($sameUser && $index === 1) {
                    $buyers[] = $buyers[0];

                    continue;
                }
                $user = User::factory()->create();
                $address = $user->addresses()->create(['full_name' => 'Test', 'phone' => '+60123456789', 'line1' => 'Test Street', 'city' => 'Shah Alam', 'state' => 'Selangor', 'postcode' => '40100']);
                Cart::create(['user_id' => $user->id])->items()->create(['product_id' => $product->id, 'item_key' => "$product->id:0", 'quantity' => 1]);
                $buyers[] = [$user->id, $address->id];
            }
            $payment = null;
            if ($callbacks) {
                $order = app(CheckoutService::class)->create(User::findOrFail($buyers[0][0]), (string) Str::uuid(), $buyers[0][1], null);
                $payment = $order->payments()->create(['reference' => 'CONCURRENTTEST', 'environment' => 'sandbox', 'secret_key_snapshot' => 'not-a-real-key', 'amount_sen' => $order->total_sen, 'status' => 'pending', 'bill_code' => 'testconcurrentbill']);
            }
            DB::beginTransaction();
            Product::whereKey($product->id)->lockForUpdate()->firstOrFail();
            $sharedKey = (string) Str::uuid();
            foreach ($buyers as [$userId, $addressId]) {
                $key = $sameUser ? $sharedKey : (string) Str::uuid();
                $command = $payment
                    ? [PHP_BINARY, base_path('tests/Support/payment-worker.php'), $file, (string) $payment->id]
                    : [PHP_BINARY, base_path('tests/Support/checkout-worker.php'), $file, (string) $userId, (string) $addressId, $key];
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
                $this->assertTrue($process->isSuccessful(), $process->getErrorOutput());
                $output = substr($process->getOutput(), strpos($process->getOutput(), '{'));
                $results[] = json_decode($output, true, flags: JSON_THROW_ON_ERROR)['result'];
            }
            sort($results);
            $this->assertSame($callbacks ? ['paid', 'paid'] : ($sameUser ? ['created', 'created'] : ['created', 'rejected']), $results);
            $this->assertSame(1, Order::count());
            $this->assertSame($callbacks ? 0 : 1, (int) StockReservation::where('status', 'active')->sum('quantity'));
            $this->assertSame($callbacks ? 0 : 1, $product->fresh()->stock);
            if ($callbacks) {
                $this->assertSame(1, Payment::where('status', 'paid')->count());
                $this->assertSame(1, StockReservation::where('status', 'consumed')->count());
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
