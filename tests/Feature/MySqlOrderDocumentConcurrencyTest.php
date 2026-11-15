<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Invoice;
use App\Models\InvoiceSetting;
use App\Models\Order;
use App\Models\OrderFulfillmentHistory;
use App\Models\Product;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PDO;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class MySqlOrderDocumentConcurrencyTest extends TestCase
{
    public static function modes(): array
    {
        return ['invoice issue' => ['invoice'], 'duplicate shipment' => ['shipment']];
    }

    #[DataProvider('modes')]
    public function test_parallel_requests_do_not_duplicate_documents_or_notices(string $mode): void
    {
        if (getenv('MYSQL_CONCURRENCY_TEST') !== '1') {
            $this->markTestSkipped('Set MYSQL_CONCURRENCY_TEST=1 for isolated MySQL concurrency tests.');
        }
        $connection = config('database.connections.mysql');
        $database = 'lr_yazid_test_'.bin2hex(random_bytes(8));
        $server = new PDO("mysql:host={$connection['host']};port={$connection['port']};charset=utf8mb4",
            $connection['username'], $connection['password'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $server->exec("CREATE DATABASE `$database` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        $file = tempnam(storage_path('framework/testing'), 'documents-');
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
            $user = User::factory()->create();
            $order = Order::create(['user_id' => $user->id, 'reference' => 'DOC-RACE', 'checkout_key' => (string) Str::uuid(),
                'customer_name' => 'QA customer', 'customer_email' => $user->email, 'customer_phone' => '+60123456789',
                'subtotal_sen' => 1000, 'shipping_fee_sen' => 100, 'total_sen' => 1100, 'payment_status' => 'paid',
                'fulfillment_status' => 'packed', 'expires_at' => now()->addHour()]);
            $product = Product::create(['name' => 'QA product', 'slug' => 'qa-product', 'sku' => 'QA', 'price_sen' => 1000, 'stock' => 1, 'status' => 'active']);
            $order->items()->create(['product_id' => $product->id, 'product_name' => 'QA product', 'sku' => 'QA', 'type' => 'physical', 'quantity' => 1, 'unit_price_sen' => 1000, 'line_total_sen' => 1000]);
            $order->payments()->create(['reference' => 'PAY-QA-RACE', 'environment' => 'sandbox', 'amount_sen' => 1100, 'status' => 'paid', 'paid_at' => now(), 'secret_key_snapshot' => 'test-only']);
            InvoiceSetting::create(['id' => 1, 'enabled' => true, 'seller_name' => 'QA issuer', 'seller_address' => 'QA address', 'seller_email' => 'qa@example.com']);
            DB::beginTransaction();
            User::whereKey($user->id)->lockForUpdate()->firstOrFail();
            for ($index = 0; $index < 2; $index++) {
                $process = new Process([PHP_BINARY, base_path('tests/Support/document-worker.php'), $file, (string) $order->id, (string) $admin->id, $mode], base_path(), ['APP_ENV' => 'testing']);
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
                $results[] = json_decode(substr($process->getOutput(), strpos($process->getOutput(), '{')), true, flags: JSON_THROW_ON_ERROR)['result'];
            }
            $this->assertSame($results[0], $results[1]);
            $this->assertSame($mode === 'invoice' ? 1 : 0, Invoice::count());
            $this->assertSame($mode === 'shipment' ? 1 : 0, OrderFulfillmentHistory::count());
            $this->assertSame($mode === 'shipment' ? 2 : 0, DB::table('jobs')->count());
            $this->assertSame('paid', $order->fresh()->payment_status);
            $this->assertSame(1, $product->fresh()->stock);
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
