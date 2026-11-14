<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Cart;
use App\Models\Order;
use App\Models\Payment;
use App\Models\PaymentGatewaySetting;
use App\Models\Product;
use App\Models\User;
use App\Services\CheckoutService;
use App\Services\PaymentVerificationService;
use App\Services\ToyyibPayService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PaymentTest extends TestCase
{
    use RefreshDatabase;

    private const SECRET = 'sandbox-test-secret-not-a-real-key';

    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
        PaymentGatewaySetting::create(['id' => 1, 'environment' => 'sandbox', 'enabled' => true, 'sandbox_secret_key' => self::SECRET, 'sandbox_category_code' => 'testcategory']);
    }

    private function order(): Order
    {
        $user = User::factory()->create();
        $address = $user->addresses()->create(['full_name' => 'Test Customer', 'phone' => '+60123456789', 'line1' => 'Test Street', 'city' => 'Shah Alam', 'state' => 'Selangor', 'postcode' => '40100']);
        $slug = (string) Str::uuid();
        $product = Product::create(['name' => 'Test Bottle', 'slug' => $slug, 'sku' => $slug, 'stock' => 10, 'price_sen' => 8900, 'status' => 'active']);
        Cart::create(['user_id' => $user->id])->items()->create(['product_id' => $product->id, 'item_key' => "$product->id:0", 'quantity' => 1]);
        $this->actingAs($user);

        return app(CheckoutService::class)->create($user, (string) Str::uuid(), $address->id, null);
    }

    private function payment(Order $order): Payment
    {
        return $order->payments()->create([
            'reference' => 'PAY'.Str::upper(Str::random(24)), 'environment' => 'sandbox',
            'secret_key_snapshot' => self::SECRET, 'amount_sen' => $order->total_sen,
            'status' => 'pending', 'bill_code' => 'test'.Str::random(12),
        ]);
    }

    private function amount(Payment $payment): string
    {
        return intdiv($payment->amount_sen, 100).'.'.sprintf('%02d', $payment->amount_sen % 100);
    }

    private function callbackData(Payment $payment, string $status = '1', string $invoice = 'TPTEST1'): array
    {
        return [
            'status' => $status, 'order_id' => $payment->reference, 'refno' => $invoice,
            'billcode' => $payment->bill_code, 'amount' => $this->amount($payment),
            'hash' => md5(self::SECRET.$status.$payment->reference.$invoice.'ok'),
        ];
    }

    private function transaction(Payment $payment, string $status = '1', string $invoice = 'TPTEST1'): array
    {
        return [
            'billExternalReferenceNo' => $payment->reference, 'billpaymentInvoiceNo' => $invoice,
            'billpaymentStatus' => $status, 'billpaymentAmount' => $this->amount($payment),
        ];
    }

    public function test_bill_creation_uses_server_prices_and_reuses_a_pending_bill(): void
    {
        $order = $this->order();
        Http::fake(['*/createBill' => Http::response([['BillCode' => 'testbill1']])]);
        $this->post("/orders/$order->id/pay")->assertRedirect('https://dev.toyyibpay.com/testbill1');
        $this->post("/orders/$order->id/pay")->assertRedirect('https://dev.toyyibpay.com/testbill1');
        Http::assertSentCount(1);
        Http::assertSent(fn ($request) => $request['billAmount'] === 9900 && $request['billPriceSetting'] === 1 && $request['userSecretKey'] === self::SECRET);
        $this->assertDatabaseCount('payments', 1);
        $this->assertSame('pending', $order->fresh()->payment_status);
    }

    public function test_disabled_payments_never_call_the_provider(): void
    {
        PaymentGatewaySetting::whereKey(1)->update(['enabled' => false]);
        $order = $this->order();
        $this->post("/orders/$order->id/pay")->assertSessionHasErrors('payment');
        Http::assertNothingSent();
        $this->assertDatabaseCount('payments', 0);
    }

    public function test_http_200_provider_error_is_not_treated_as_a_bill(): void
    {
        $order = $this->order();
        Http::fake(['*/createBill' => Http::response(['status' => 'error', 'msg' => 'invalid category'])]);
        $this->post("/orders/$order->id/pay")->assertSessionHasErrors('payment');
        $this->assertDatabaseHas('payments', ['status' => 'failed', 'bill_code' => null]);
        $this->assertSame('pending', $order->fresh()->payment_status);
    }

    public function test_ambiguous_creation_timeout_blocks_duplicate_bills(): void
    {
        $order = $this->order();
        Http::fake(fn () => throw new ConnectionException('Test timeout'));
        $this->post("/orders/$order->id/pay")->assertSessionHasErrors('payment');
        $this->post("/orders/$order->id/pay")->assertSessionHasErrors('payment');
        $this->assertDatabaseCount('payments', 1);
        $this->assertDatabaseHas('payments', ['status' => 'creation_unknown', 'requires_review' => true]);
    }

    public function test_verified_callback_marks_paid_and_deducts_stock_once(): void
    {
        $order = $this->order();
        $payment = $this->payment($order);
        Http::fake(['*/getBillTransactions' => Http::response([$this->transaction($payment)])]);
        $this->postJson('/payments/toyyibpay/callback', $this->callbackData($payment))->assertOk();
        $this->postJson('/payments/toyyibpay/callback', $this->callbackData($payment))->assertOk();
        $this->assertSame('paid', $order->fresh()->payment_status);
        $this->assertSame('paid', $payment->fresh()->status);
        $this->assertSame(9, Product::firstOrFail()->stock);
        $this->assertSame('consumed', $order->reservations()->firstOrFail()->status);
        Http::assertSentCount(1);
    }

    public function test_invalid_signature_and_bill_code_are_rejected_before_http(): void
    {
        $payment = $this->payment($this->order());
        $data = $this->callbackData($payment);
        $data['hash'] = str_repeat('0', 32);
        $this->postJson('/payments/toyyibpay/callback', $data)->assertForbidden();
        $data = $this->callbackData($payment);
        $data['billcode'] = 'wrongbill';
        $this->postJson('/payments/toyyibpay/callback', $data)->assertForbidden();
        Http::assertNothingSent();
        $this->assertSame('pending', $payment->fresh()->status);
    }

    public function test_callback_amount_tampering_is_rejected_even_with_a_valid_hash(): void
    {
        $payment = $this->payment($this->order());
        $data = $this->callbackData($payment);
        $data['amount'] = '0.01';
        $this->postJson('/payments/toyyibpay/callback', $data)->assertUnprocessable();
        Http::assertNothingSent();
    }

    public function test_server_amount_or_external_reference_mismatch_never_marks_paid(): void
    {
        $order = $this->order();
        $payment = $this->payment($order);
        $transaction = $this->transaction($payment);
        $transaction['billpaymentAmount'] = '1.00';
        $foreign = $this->transaction($payment);
        $foreign['billExternalReferenceNo'] = 'OTHER';
        Http::fake(['*/getBillTransactions' => Http::sequence()->push([$transaction])->push([$foreign])]);
        $this->postJson('/payments/toyyibpay/callback', $this->callbackData($payment))->assertUnprocessable();
        $this->postJson('/payments/toyyibpay/callback', $this->callbackData($payment))->assertStatus(202);
        $this->assertSame('pending', $order->fresh()->payment_status);
        $this->assertSame(10, Product::firstOrFail()->stock);
    }

    public function test_pending_api_status_requires_later_server_reconciliation(): void
    {
        $payment = $this->payment($this->order());
        Http::fake(['*/getBillTransactions' => Http::sequence()->push([$this->transaction($payment, '2')])->push([$this->transaction($payment)])]);
        $this->postJson('/payments/toyyibpay/callback', $this->callbackData($payment))->assertStatus(202);
        $this->assertSame('paid', app(PaymentVerificationService::class)->reconcile($payment));
    }

    public function test_failed_payment_can_retry_without_releasing_stock_early(): void
    {
        $order = $this->order();
        $payment = $this->payment($order);
        Http::fake(['*/getBillTransactions' => Http::response([$this->transaction($payment, '3')])]);
        $this->postJson('/payments/toyyibpay/callback', $this->callbackData($payment, '3'))->assertOk();
        $this->assertSame('failed', $order->fresh()->payment_status);
        $this->assertSame('active', $order->reservations()->firstOrFail()->status);
        Http::fake(['*/createBill' => Http::response([['BillCode' => 'retrybill1']])]);
        $this->post("/orders/$order->id/pay")->assertRedirect('https://dev.toyyibpay.com/retrybill1');
        $this->assertDatabaseCount('payments', 2);
    }

    public function test_out_of_order_failure_never_downgrades_paid_order(): void
    {
        $order = $this->order();
        $payment = $this->payment($order);
        Http::fake(['*/getBillTransactions' => Http::response([$this->transaction($payment)])]);
        $this->postJson('/payments/toyyibpay/callback', $this->callbackData($payment))->assertOk();
        $this->postJson('/payments/toyyibpay/callback', $this->callbackData($payment, '3'))->assertOk();
        $this->assertSame('paid', $order->fresh()->payment_status);
        $this->assertSame(9, Product::firstOrFail()->stock);
    }

    public function test_late_verified_payment_requires_review_without_overselling(): void
    {
        $this->freezeTime();
        $order = $this->order();
        $payment = $this->payment($order);
        $this->travel(31)->minutes();
        Http::fake(['*/getBillTransactions' => Http::response([$this->transaction($payment)])]);
        $this->postJson('/payments/toyyibpay/callback', $this->callbackData($payment))->assertOk()->assertJsonPath('status', 'requires_review');
        $this->assertSame('paid', $order->fresh()->payment_status);
        $this->assertSame('cancelled', $order->fresh()->fulfillment_status);
        $this->assertTrue($payment->fresh()->requires_review);
        $this->assertSame(10, Product::firstOrFail()->stock);
    }

    public function test_additional_payment_on_same_bill_is_flagged_without_double_deduction(): void
    {
        $payment = $this->payment($this->order());
        Http::fake(['*/getBillTransactions' => Http::sequence()->push([$this->transaction($payment)])->push([$this->transaction($payment, '1', 'TPTEST2')])]);
        $this->postJson('/payments/toyyibpay/callback', $this->callbackData($payment))->assertOk();
        $this->postJson('/payments/toyyibpay/callback', $this->callbackData($payment, '1', 'TPTEST2'))->assertOk();
        $this->assertTrue($payment->fresh()->requires_review);
        $this->assertSame('TPTEST1', $payment->fresh()->transaction_reference);
        $this->assertSame(9, Product::firstOrFail()->stock);
    }

    public function test_browser_return_status_is_never_trusted(): void
    {
        $order = $this->order();
        $payment = $this->payment($order);
        $this->get('/payments/return?'.http_build_query(['order_id' => $payment->reference, 'billcode' => $payment->bill_code, 'status_id' => '1']))
            ->assertInertia(fn (Assert $page) => $page->component('customer/PaymentResult')->where('order.payment_status', 'pending')->missing('payment.secret_key_snapshot'));
        $this->assertSame('pending', $order->fresh()->payment_status);
        Http::assertNothingSent();
    }

    public function test_secrets_are_encrypted_and_never_sent_to_vue(): void
    {
        $order = $this->order();
        $payment = $this->payment($order);
        $raw = DB::table('payments')->where('id', $payment->id)->value('secret_key_snapshot');
        $this->assertNotSame(self::SECRET, $raw);
        $this->get("/orders/$order->id")->assertDontSee(self::SECRET)->assertInertia(fn (Assert $page) => $page->missing('order.payments.0.secret_key_snapshot'));
        $user = $order->user;
        $user->role = UserRole::Admin;
        $user->save();
        $this->actingAs($user)->get('/admin/payment-settings')->assertDontSee(self::SECRET)
            ->assertInertia(fn (Assert $page) => $page->missing('settings.sandbox_secret_key')->where('sandboxConfigured', true));
    }

    public function test_customers_cannot_manage_or_reconcile_payments(): void
    {
        $payment = $this->payment($this->order());
        $this->get('/admin/payments')->assertForbidden();
        $this->put('/admin/payment-settings', [])->assertForbidden();
        $this->post("/admin/payments/$payment->id/reconcile")->assertForbidden();
    }

    public function test_decimal_money_parsing_uses_exact_integer_sen(): void
    {
        $gateway = app(ToyyibPayService::class);
        $this->assertSame(1, $gateway->toSen('0.01'));
        $this->assertSame(8910, $gateway->toSen('89.1'));
        $this->assertSame(8900, $gateway->toSen('89'));
    }

    public function test_callback_validation_returns_json_without_an_accept_header(): void
    {
        $this->post('/payments/toyyibpay/callback', [])->assertUnprocessable()->assertJsonValidationErrors('hash');
    }

    public function test_server_payment_date_is_interpreted_in_malaysia_timezone(): void
    {
        $this->freezeTime();
        $time = now()->subMinutes(3);
        $this->assertSame($time->toDateTimeString(), app(ToyyibPayService::class)->paymentTime($time->format('d-m-Y H:i:s'))->toDateTimeString());
    }
}
