<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Invoice;
use App\Models\InvoiceSetting;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Notifications\ShipmentNotice;
use App\Services\OrderFulfillmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class OrderDocumentsTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $customer;

    private Order $order;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create();
        $this->admin->role = UserRole::Admin;
        $this->admin->save();
        $this->customer = User::factory()->create();
        $this->order = Order::create(['user_id' => $this->customer->id, 'reference' => 'DOC-'.Str::random(20), 'checkout_key' => (string) Str::uuid(),
            'customer_name' => 'Original customer', 'customer_email' => $this->customer->email, 'customer_phone' => '+60123456789',
            'shipping_address' => ['full_name' => 'Original customer', 'line1' => 'Original address'],
            'subtotal_sen' => 1000, 'shipping_fee_sen' => 100, 'total_sen' => 1100, 'payment_status' => 'paid', 'fulfillment_status' => 'packed', 'expires_at' => now()->addHour()]);
        $product = Product::create(['name' => 'Current product', 'slug' => (string) Str::uuid(), 'sku' => (string) Str::uuid(), 'price_sen' => 9000, 'stock' => 2, 'status' => 'active']);
        $this->order->items()->create(['product_id' => $product->id, 'product_name' => 'Purchased product', 'sku' => $product->sku, 'type' => 'physical', 'quantity' => 1, 'unit_price_sen' => 1000, 'line_total_sen' => 1000]);
        $this->order->payments()->create(['reference' => 'DOC-PAY-'.Str::random(20), 'environment' => 'sandbox', 'amount_sen' => 1100, 'status' => 'paid', 'paid_at' => now(), 'secret_key_snapshot' => 'test-secret-not-real']);
        InvoiceSetting::create(['id' => 1, 'enabled' => true, 'seller_name' => 'Test issuer', 'seller_address' => 'Test address', 'seller_email' => 'issuer@example.com']);
        $this->actingAs($this->admin);
    }

    private function issue(): Invoice
    {
        $this->post("/admin/orders/{$this->order->id}/invoice")->assertSessionHasNoErrors()->assertRedirect();

        return Invoice::where('order_id', $this->order->id)->firstOrFail();
    }

    public function test_issuance_is_idempotent_and_retains_seller_customer_and_item_snapshots(): void
    {
        $invoice = $this->issue();
        $this->issue();
        InvoiceSetting::whereKey(1)->update(['seller_name' => 'Changed issuer']);
        $this->customer->update(['name' => 'Changed name']);
        $this->order->items()->update(['product_name' => 'Changed later']);
        $this->issue();
        $this->assertDatabaseCount('invoices', 1);
        $this->assertSame('Test issuer', $invoice->fresh()->snapshot['seller']['seller_name']);
        $this->assertSame('Original customer', $invoice->fresh()->snapshot['buyer']['customer_name']);
        $this->assertSame('Purchased product', $invoice->fresh()->snapshot['items'][0]['product_name']);
        $this->assertSame(1100, $invoice->snapshot['total_sen']);
        $this->get("/invoices/$invoice->id")->assertInertia(fn (Assert $page) => $page->component('invoice/Show')->missing('invoice.snapshot.secret_key_snapshot'));
    }

    public function test_unpaid_review_mismatched_or_undated_payments_cannot_issue_an_invoice(): void
    {
        $url = "/admin/orders/{$this->order->id}/invoice";
        $this->order->update(['payment_status' => 'pending']);
        $this->post($url)->assertSessionHasErrors('invoice');
        $this->order->update(['payment_status' => 'paid']);
        foreach ([['requires_review' => true], ['amount_sen' => 1099], ['paid_at' => null]] as $change) {
            $this->order->payments()->update(['requires_review' => false, 'amount_sen' => 1100, 'paid_at' => now(), ...$change]);
            $this->post($url)->assertSessionHasErrors('invoice');
        }
        $this->assertDatabaseCount('invoices', 0);
    }

    public function test_disabled_or_missing_issuer_blocks_issuance(): void
    {
        InvoiceSetting::whereKey(1)->update(['enabled' => false]);
        $this->post("/admin/orders/{$this->order->id}/invoice")->assertSessionHasErrors('invoice');
        InvoiceSetting::whereKey(1)->delete();
        $this->post("/admin/orders/{$this->order->id}/invoice")->assertSessionHasErrors('invoice');
        $this->assertDatabaseCount('invoices', 0);
    }

    public function test_first_settings_visit_returns_database_defaults_to_the_form(): void
    {
        InvoiceSetting::whereKey(1)->delete();
        $this->get('/admin/invoice-settings')->assertInertia(fn (Assert $page) => $page->where('settings.enabled', false));
    }

    public function test_inconsistent_line_snapshots_cannot_be_invoiced(): void
    {
        $this->order->items()->update(['line_total_sen' => 999]);
        $this->post("/admin/orders/{$this->order->id}/invoice")->assertSessionHasErrors('invoice');
        $this->assertDatabaseCount('invoices', 0);
    }

    public function test_invoice_settings_issue_export_and_invoice_ownership_are_server_protected(): void
    {
        $invoice = $this->issue();
        $this->actingAs($this->customer)->get("/invoices/$invoice->id")->assertOk();
        $this->post("/admin/orders/{$this->order->id}/invoice")->assertForbidden();
        $this->get('/admin/orders/export')->assertForbidden();
        $this->put('/admin/invoice-settings', [])->assertForbidden();
        $this->actingAs(User::factory()->create())->get("/invoices/$invoice->id")->assertForbidden();
    }

    public function test_issuer_form_requires_valid_details_and_does_not_change_existing_invoices(): void
    {
        $invoice = $this->issue();
        $this->put('/admin/invoice-settings', ['enabled' => true])->assertSessionHasErrors(['seller_name', 'seller_address', 'seller_email']);
        $this->put('/admin/invoice-settings', ['enabled' => false, 'seller_name' => 'New issuer', 'seller_address' => 'New address', 'seller_email' => 'new@example.com'])->assertSessionHasNoErrors();
        $this->assertSame('Test issuer', $invoice->fresh()->snapshot['seller']['seller_name']);
        $this->assertFalse(InvoiceSetting::firstOrFail()->enabled);
    }

    public function test_csv_export_uses_filters_exact_money_and_spreadsheet_safe_values(): void
    {
        $this->order->update(['customer_name' => ' =HYPERLINK("https://invalid.test")']);
        $csv = $this->get('/admin/orders/export?payment=paid&search='.$this->order->reference)->assertOk()->streamedContent();
        $this->assertStringContainsString("' =HYPERLINK", $csv);
        $this->assertStringContainsString('11.00', $csv);
        $this->assertStringNotContainsString('test-secret-not-real', $csv);
        $excluded = $this->get('/admin/orders/export?payment=failed')->assertOk()->streamedContent();
        $this->assertStringNotContainsString($this->order->reference, $excluded);
        $this->get('/admin/orders/export?from=2026-12-31&to=2026-01-01')->assertSessionHasErrors('to');
    }

    public function test_shipped_and_tracking_updates_notify_once_per_actual_change(): void
    {
        Notification::fake();
        $service = app(OrderFulfillmentService::class);
        $data = ['fulfillment_status' => 'shipped', 'courier_name' => 'Test courier', 'tracking_number' => 'TRACK-1', 'reason' => 'QA dispatch'];
        $service->update($this->order, $this->admin, $data);
        $service->update($this->order, $this->admin, $data);
        Notification::assertSentToTimes($this->customer, ShipmentNotice::class, 1);
        $service->update($this->order, $this->admin, [...$data, 'tracking_number' => 'TRACK-2']);
        Notification::assertSentToTimes($this->customer, ShipmentNotice::class, 2);
        $service->update($this->order, $this->admin, [...$data, 'tracking_number' => 'TRACK-2', 'fulfillment_status' => 'delivered']);
        Notification::assertSentToTimes($this->customer, ShipmentNotice::class, 3);
        $this->assertSame(3, $this->order->fulfillmentHistory()->count());
    }

    public function test_database_shipment_delivery_is_tracked_and_snapshot_contains_no_current_mutable_values(): void
    {
        $data = ['fulfillment_status' => 'shipped', 'courier_name' => 'Test courier', 'tracking_number' => 'TRACK-1', 'reason' => 'QA dispatch'];
        app(OrderFulfillmentService::class)->update($this->order, $this->admin, $data);
        $history = $this->order->fulfillmentHistory()->firstOrFail();
        $notice = new ShipmentNotice($history->id, $this->order->id, 'shipped', $this->order->reference, 'Test courier', 'TRACK-1');
        $this->customer->notifyNow($notice, ['database']);
        $this->assertNotNull($history->fresh()->database_sent_at);
        $this->assertStringContainsString('TRACK-1', $this->customer->notifications()->firstOrFail()->data['message']);
        $this->assertSame(2, DB::table('jobs')->count());
    }
}
