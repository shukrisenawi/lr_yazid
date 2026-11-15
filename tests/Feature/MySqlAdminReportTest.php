<?php

namespace Tests\Feature;

use App\Models\MembershipPlan;
use App\Models\MembershipTransaction;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Services\AdminReportService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PDO;
use Tests\TestCase;

class MySqlAdminReportTest extends TestCase
{
    public function test_financial_aggregates_execute_against_an_isolated_mysql_database(): void
    {
        if (getenv('MYSQL_CONCURRENCY_TEST') !== '1') {
            $this->markTestSkipped('Set MYSQL_CONCURRENCY_TEST=1 for isolated MySQL checks.');
        }
        $connection = config('database.connections.mysql');
        $database = 'lr_yazid_test_'.bin2hex(random_bytes(8));
        $server = new PDO("mysql:host={$connection['host']};port={$connection['port']};charset=utf8mb4",
            $connection['username'], $connection['password'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $server->exec("CREATE DATABASE `$database` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        try {
            $connection['database'] = $database;
            config(['database.default' => 'mysql', 'database.connections.mysql' => $connection]);
            DB::purge('mysql');
            $this->artisan('migrate', ['--force' => true])->assertSuccessful();
            $user = User::factory()->create();
            $product = Product::create(['name' => 'Reporting QA', 'slug' => 'reporting-qa', 'sku' => 'REPORT-QA', 'price_sen' => 12000, 'type' => 'membership', 'stock' => 0, 'status' => 'active']);
            $plan = MembershipPlan::create(['name' => 'Reporting QA plan', 'product_id' => $product->id, 'price_sen' => 12000, 'duration_months' => 12]);
            $membership = $user->memberships()->create(['membership_plan_id' => $plan->id, 'starts_at' => now()->subDay(), 'expires_at' => now()->addYear()]);
            $order = Order::create(['user_id' => $user->id, 'reference' => 'MYSQL-REPORT-QA', 'checkout_key' => Str::uuid(),
                'customer_name' => 'Isolated test customer', 'customer_email' => $user->email, 'customer_phone' => '+60123456789',
                'subtotal_sen' => 12000, 'shipping_fee_sen' => 345, 'total_sen' => 12345, 'payment_status' => 'paid', 'expires_at' => now()->addHour()]);
            $item = $order->items()->create(['product_id' => $product->id, 'membership_plan_id' => $plan->id, 'membership_months' => 12,
                'product_name' => 'Reporting QA', 'sku' => 'REPORT-QA', 'type' => 'membership', 'quantity' => 1, 'unit_price_sen' => 12000, 'line_total_sen' => 12000]);
            $payment = $order->payments()->create(['reference' => 'MYSQL-REPORT-PAY-QA', 'environment' => 'production', 'amount_sen' => 12345,
                'status' => 'paid', 'paid_at' => now(), 'checked_at' => now(), 'transaction_reference' => 'MYSQL-REPORT-TX-QA', 'secret_key_snapshot' => 'isolated-test-not-real']);
            MembershipTransaction::create(['user_id' => $user->id, 'membership_id' => $membership->id, 'payment_id' => $payment->id,
                'order_item_id' => $item->id, 'type' => 'activation', 'amount_sen' => 12000,
                'starts_at' => now(), 'expires_at' => now()->addYear(), 'paid_at' => now()]);
            $report = app(AdminReportService::class)->generate([
                'from' => now()->startOfMonth()->subMonth()->toDateString(), 'to' => now()->toDateString(), 'environment' => 'production']);
            $this->assertSame(12345, $report['summary']['collected_sen']);
            $this->assertSame(12000, $report['summary']['membership_sen']);
            $this->assertSame(1, $report['summary']['vip_activations']);
            $this->assertSame(1, $report['current']['active_vip']);
            $this->assertCount(2, $report['monthly']);
            $this->assertSame(0, $report['monthly'][0]['collected_sen']);
            $this->assertSame(12345, $report['monthly'][1]['collected_sen']);
            $this->assertSame(1, $report['monthly'][1]['vip_activations']);
        } finally {
            while (DB::transactionLevel() > 0) {
                DB::rollBack();
            }
            DB::purge('mysql');
            $server->exec("DROP DATABASE `$database`");
        }
    }
}
