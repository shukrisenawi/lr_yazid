<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\CommerceSetting;
use App\Models\Membership;
use App\Models\MembershipPlan;
use App\Models\MembershipTransaction;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Tests\TestCase;

class MembershipPurchaseTest extends TestCase
{
    use RefreshDatabase;

    private function plan(): MembershipPlan
    {
        $slug = (string) Str::uuid();
        $product = Product::create(['name' => 'Annual VIP', 'slug' => $slug, 'sku' => $slug, 'type' => 'membership', 'price_sen' => 19900, 'stock' => 0, 'status' => 'active']);

        return MembershipPlan::create(['name' => 'Annual VIP', 'price_sen' => 19900, 'duration_months' => 12, 'benefits' => ['VIP dashboard'], 'product_id' => $product->id]);
    }

    private function checkout(User $user, MembershipPlan $plan, ?int $addressId = null): Order
    {
        $this->actingAs($user)->post('/cart/items', ['product_id' => $plan->product_id, 'quantity' => 1])->assertSessionHasNoErrors();
        $this->post('/checkout', ['checkout_key' => (string) Str::uuid(), 'address_id' => $addressId])->assertSessionHasNoErrors();

        return $user->orders()->latest('id')->firstOrFail();
    }

    private function verify(Order $order, string $status = '1'): array
    {
        $payment = $order->payments()->create(['reference' => 'PAY'.Str::random(24), 'environment' => 'sandbox', 'secret_key_snapshot' => 'membership-test-key', 'amount_sen' => $order->total_sen, 'bill_code' => 'test'.Str::random(12), 'status' => 'pending']);
        $amount = intdiv($order->total_sen, 100).'.'.sprintf('%02d', $order->total_sen % 100);
        Http::preventStrayRequests();
        Http::fake(['*/getBillTransactions' => Http::response([['billExternalReferenceNo' => $payment->reference, 'billpaymentInvoiceNo' => 'TPVIPTEST', 'billpaymentStatus' => $status, 'billpaymentAmount' => $amount]])]);
        $data = ['order_id' => $payment->reference, 'billcode' => $payment->bill_code, 'refno' => 'TPVIPTEST', 'status' => $status, 'amount' => $amount, 'hash' => md5('membership-test-key'.$status.$payment->reference.'TPVIPTESTok')];
        $this->postJson('/payments/toyyibpay/callback', $data)->assertOk();

        return $data;
    }

    public function test_membership_only_checkout_needs_no_address_shipping_or_stock(): void
    {
        CommerceSetting::create(['id' => 1, 'shipping_enabled' => false, 'shipping_fee_sen' => 2000, 'reservation_minutes' => 30]);
        $user = User::factory()->create(['phone' => '+60123456789']);
        $order = $this->checkout($user, $this->plan());
        $this->assertSame(0, $order->shipping_fee_sen);
        $this->assertNull($order->shipping_address);
        $this->assertNull($order->fulfillment_status);
        $this->assertSame(19900, $order->total_sen);
        $this->assertDatabaseCount('stock_reservations', 0);
        $this->assertFalse($user->hasActiveMembership());
        $this->get('/vip/dashboard')->assertForbidden();
    }

    public function test_verified_payment_activates_membership_exactly_once(): void
    {
        $this->freezeTime();
        $user = User::factory()->create(['phone' => '+60123456789']);
        $plan = $this->plan();
        $order = $this->checkout($user, $plan);
        $data = $this->verify($order);
        $this->postJson('/payments/toyyibpay/callback', $data)->assertOk();
        $this->assertTrue($user->hasActiveMembership());
        $membership = $user->memberships()->firstOrFail();
        $this->assertSame(now()->addMonthsNoOverflow(12)->toDateTimeString(), $membership->expires_at->toDateTimeString());
        $this->assertDatabaseCount('membership_transactions', 1);
        $this->get('/vip/dashboard')->assertOk();
    }

    public function test_early_renewal_extends_existing_expiry_not_payment_date(): void
    {
        $this->freezeTime();
        $user = User::factory()->create(['phone' => '+60123456789']);
        $plan = $this->plan();
        $oldExpiry = now()->addMonthsNoOverflow(2);
        Membership::create(['user_id' => $user->id, 'membership_plan_id' => $plan->id, 'status' => 'active', 'starts_at' => now()->subMonthsNoOverflow(10), 'expires_at' => $oldExpiry]);
        $this->verify($this->checkout($user, $plan));
        $this->assertSame($oldExpiry->addMonthsNoOverflow(12)->toDateTimeString(), $user->memberships()->firstOrFail()->expires_at->toDateTimeString());
        $this->assertDatabaseHas('membership_transactions', ['type' => 'renewal']);
    }

    public function test_expired_membership_restarts_from_verified_payment(): void
    {
        $this->freezeTime();
        $user = User::factory()->create(['phone' => '+60123456789']);
        $plan = $this->plan();
        Membership::create(['user_id' => $user->id, 'membership_plan_id' => $plan->id, 'status' => 'expired', 'starts_at' => now()->subYears(2), 'expires_at' => now()->subDay()]);
        $this->verify($this->checkout($user, $plan));
        $membership = $user->memberships()->firstOrFail();
        $this->assertSame(now()->toDateTimeString(), $membership->starts_at->toDateTimeString());
        $this->assertSame(now()->addMonthsNoOverflow(12)->toDateTimeString(), $membership->expires_at->toDateTimeString());
    }

    public function test_calendar_month_renewal_does_not_overflow_leap_day(): void
    {
        $this->travelTo(now()->setDate(2028, 2, 29)->setTime(10, 0));
        $user = User::factory()->create(['phone' => '+60123456789']);
        $this->verify($this->checkout($user, $this->plan()));
        $this->assertSame('2029-02-28', $user->memberships()->firstOrFail()->expires_at->toDateString());
    }

    public function test_failed_payments_do_not_activate_or_extend_membership(): void
    {
        $user = User::factory()->create(['phone' => '+60123456789']);
        $this->verify($this->checkout($user, $this->plan()), '3');
        $this->assertFalse($user->hasActiveMembership());
        $this->assertDatabaseCount('membership_transactions', 0);
    }

    public function test_pending_membership_purchase_blocks_another_order(): void
    {
        $user = User::factory()->create(['phone' => '+60123456789']);
        $plan = $this->plan();
        $this->checkout($user, $plan);
        $this->post('/cart/items', ['product_id' => $plan->product_id, 'quantity' => 1])->assertSessionHasNoErrors();
        $this->post('/checkout', ['checkout_key' => (string) Str::uuid()])->assertSessionHasErrors('cart');
        $this->assertDatabaseCount('orders', 1);
    }

    public function test_membership_quantity_is_one_even_when_added_again(): void
    {
        $plan = $this->plan();
        $this->post('/cart/items', ['product_id' => $plan->product_id, 'quantity' => 2])->assertSessionHasErrors('quantity');
        $this->post('/cart/items', ['product_id' => $plan->product_id, 'quantity' => 1])->assertSessionHasNoErrors();
        $this->post('/cart/items', ['product_id' => $plan->product_id, 'quantity' => 1])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('cart_items', ['product_id' => $plan->product_id, 'quantity' => 1]);
    }

    public function test_order_snapshots_preserve_purchased_membership_price_and_duration(): void
    {
        $this->freezeTime();
        $user = User::factory()->create(['phone' => '+60123456789']);
        $plan = $this->plan();
        $order = $this->checkout($user, $plan);
        $plan->update(['price_sen' => 29900, 'duration_months' => 6]);
        $this->verify($order);
        $this->assertSame(19900, MembershipTransaction::firstOrFail()->amount_sen);
        $this->assertSame(now()->addMonthsNoOverflow(12)->toDateTimeString(), $user->memberships()->firstOrFail()->expires_at->toDateTimeString());
    }

    public function test_expiry_command_preserves_history_and_timestamp_gate(): void
    {
        $this->freezeTime();
        $user = User::factory()->create(['phone' => '+60123456789']);
        $plan = $this->plan();
        Membership::create(['user_id' => $user->id, 'membership_plan_id' => $plan->id, 'status' => 'active', 'starts_at' => now()->subYear(), 'expires_at' => now()]);
        $this->actingAs($user)->get('/vip/dashboard')->assertForbidden();
        $this->artisan('vip:expire-memberships')->assertSuccessful();
        $this->assertDatabaseHas('memberships', ['user_id' => $user->id, 'status' => 'expired']);
        $this->get('/membership')->assertOk();
    }

    public function test_administrator_can_create_a_plan_but_customer_cannot(): void
    {
        $user = User::factory()->create();
        $data = ['name' => 'Annual VIP', 'price_sen' => 19900, 'duration_months' => 12, 'is_active' => true, 'benefits' => ['VIP dashboard']];
        $this->actingAs($user)->post('/admin/membership-plans', $data)->assertForbidden();
        $user->role = UserRole::Admin;
        $user->save();
        $this->post('/admin/membership-plans', $data)->assertSessionHasNoErrors();
        $this->assertDatabaseHas('products', ['type' => 'membership', 'stock' => 0, 'price_sen' => 19900]);
    }

    public function test_admin_can_create_trial_account_for_selected_number_of_days(): void
    {
        $this->freezeTime();
        Notification::fake();
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $plan = $this->plan();
        $this->actingAs($admin)->post('/admin/membership-plans/trial', [
            'name' => 'Trial Customer', 'email' => 'trial@example.com',
            'phone' => '0123456789', 'password' => 'TrialPassword123!',
            'password_confirmation' => 'TrialPassword123!',
            'membership_plan_id' => $plan->id, 'trial_days' => 14,
        ])->assertSessionHasNoErrors()->assertRedirect();
        $user = User::where('email', 'trial@example.com')->firstOrFail();
        $this->assertSame(UserRole::Customer, $user->role);
        $this->assertTrue($user->hasActiveMembership());
        $this->assertTrue(Hash::check('TrialPassword123!', $user->password));
        $this->assertSame(now()->addDays(14)->toDateTimeString(), $user->memberships()->firstOrFail()->expires_at->toDateTimeString());
        Notification::assertSentTo($user, VerifyEmail::class);
        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('membership_transactions', 0);
        $this->travel(14)->days();
        $this->assertFalse($user->hasActiveMembership());
    }

    public function test_trial_creation_is_admin_only_and_requires_valid_days(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->post('/admin/membership-plans/trial')->assertForbidden();
        $user->role = UserRole::Admin;
        $user->save();
        foreach ([0, 366, 1.5] as $days) {
            $this->post('/admin/membership-plans/trial', [
                'membership_plan_id' => $this->plan()->id, 'trial_days' => $days,
            ])->assertSessionHasErrors('trial_days');
        }
        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseCount('memberships', 0);
    }

    public function test_combined_order_consumes_physical_stock_and_activates_vip(): void
    {
        $user = User::factory()->create(['phone' => '+60123456789']);
        $address = $user->addresses()->create(['full_name' => 'Test', 'phone' => '+60123456789', 'line1' => 'Test Street', 'city' => 'Shah Alam', 'state' => 'Selangor', 'postcode' => '40100']);
        $product = Product::create(['name' => 'Bottle', 'slug' => 'bottle', 'sku' => 'BOTTLE', 'stock' => 2, 'price_sen' => 8900, 'status' => 'active']);
        $this->actingAs($user)->post('/cart/items', ['product_id' => $product->id, 'quantity' => 1])->assertSessionHasNoErrors();
        $order = $this->checkout($user, $this->plan(), $address->id);
        $this->assertSame(29800, $order->total_sen);
        $this->assertSame(1000, $order->shipping_fee_sen);
        $this->verify($order);
        $this->assertSame(1, $product->fresh()->stock);
        $this->assertTrue($user->hasActiveMembership());
        $this->assertSame(19900, MembershipTransaction::firstOrFail()->amount_sen);
    }

    public function test_revocation_before_payment_requires_review_without_restoring_privileges(): void
    {
        $user = User::factory()->create(['phone' => '+60123456789']);
        $plan = $this->plan();
        $order = $this->checkout($user, $plan);
        Membership::create(['user_id' => $user->id, 'membership_plan_id' => $plan->id, 'status' => 'revoked', 'starts_at' => now()->subDay(), 'expires_at' => now()->addYear()]);
        $this->verify($order);
        $this->assertFalse($user->hasActiveMembership());
        $this->assertDatabaseCount('membership_transactions', 0);
        $this->assertTrue($order->payments()->firstOrFail()->requires_review);
    }
}
