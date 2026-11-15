<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\CustomerContactChange;
use App\Models\Invoice;
use App\Models\MembershipPlan;
use App\Models\Order;
use App\Models\User;
use App\Services\CustomerContactService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class CustomerContactManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $customer;

    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
        $this->admin = User::factory()->create();
        $this->admin->role = UserRole::Admin;
        $this->admin->save();
        $this->customer = User::factory()->withTwoFactor()->create(['name' => 'Original Customer', 'phone' => '+60123456789']);
        $this->actingAs($this->admin);
    }

    private function data(array $override = []): array
    {
        return [...['name' => 'Corrected Customer', 'phone' => '019-876 5432', 'profile_version' => 1,
            'reason' => 'Customer requested contact correction'], ...$override];
    }

    private function url(): string
    {
        return "/admin/customers/{$this->customer->id}";
    }

    public function test_customer_cannot_use_admin_endpoint_or_service_and_admin_targets_are_rejected(): void
    {
        $this->actingAs($this->customer)->put($this->url(), $this->data())->assertForbidden();
        try {
            app(CustomerContactService::class)->update($this->customer, $this->customer, $this->data());
            $this->fail('Service role checks must not be bypassed.');
        } catch (HttpException $error) {
            $this->assertSame(403, $error->getStatusCode());
        }
        $this->actingAs($this->admin)->put("/admin/customers/{$this->admin->id}", $this->data())->assertNotFound();
        $this->assertDatabaseCount('customer_contact_changes', 0);
    }

    public function test_normalized_contact_correction_is_audited_without_mutating_security_or_commercial_snapshots(): void
    {
        $order = Order::create(['user_id' => $this->customer->id, 'reference' => 'CONTACT-SNAPSHOT-QA', 'checkout_key' => Str::uuid(),
            'customer_name' => $this->customer->name, 'customer_email' => $this->customer->email,
            'customer_phone' => $this->customer->phone, 'subtotal_sen' => 1000, 'shipping_fee_sen' => 0, 'total_sen' => 1000,
            'payment_status' => 'paid', 'expires_at' => now()->subDay()]);
        $invoice = Invoice::create(['order_id' => $order->id, 'issued_by' => $this->admin->id, 'reference' => 'INV-CONTACT-QA',
            'issued_at' => now(), 'snapshot' => ['buyer' => ['customer_name' => $this->customer->name, 'customer_phone' => $this->customer->phone]]]);
        $plan = MembershipPlan::create(['name' => 'QA VIP', 'price_sen' => 19900, 'duration_months' => 12]);
        $membership = $this->customer->memberships()->create(['membership_plan_id' => $plan->id,
            'status' => 'active', 'starts_at' => now()->subDay(), 'expires_at' => now()->addYear()]);
        $beforeOrder = $order->fresh()->getRawOriginal();
        $beforeInvoice = $invoice->fresh()->getRawOriginal();
        $beforeMembership = $membership->fresh()->getRawOriginal();
        $security = $this->customer->fresh()->only(['email', 'email_verified_at', 'password', 'remember_token', 'role',
            'two_factor_secret', 'two_factor_recovery_codes', 'two_factor_confirmed_at']);
        $this->from($this->url())->put($this->url(), $this->data(['name' => '  Corrected Customer  ']))->assertSessionHasNoErrors();
        $fresh = $this->customer->fresh();
        $this->assertSame('Corrected Customer', $fresh->name);
        $this->assertSame('+60198765432', $fresh->phone);
        $this->assertSame(2, $fresh->profile_version);
        $this->assertEquals($security, $fresh->only(array_keys($security)));
        $this->assertSame($beforeOrder, $order->fresh()->getRawOriginal());
        $this->assertSame($beforeInvoice, $invoice->fresh()->getRawOriginal());
        $this->assertSame($beforeMembership, $membership->fresh()->getRawOriginal());
        $audit = CustomerContactChange::firstOrFail();
        $this->assertSame(['name' => 'Original Customer', 'phone' => '+60123456789'], $audit->previous_values);
        $this->assertSame(['name' => 'Corrected Customer', 'phone' => '+60198765432'], $audit->new_values);
        $this->assertSame($this->admin->id, $audit->admin_id);
        $this->assertSame(2, $audit->profile_version);
        $this->assertSame(0, DB::table('jobs')->count());
        Http::assertNothingSent();
    }

    public function test_protected_fields_are_rejected_and_credentials_are_not_flashed(): void
    {
        foreach (['email', 'password', 'role', 'email_verified_at', 'remember_token', 'two_factor_secret', 'two_factor_recovery_codes'] as $field) {
            $this->put($this->url(), $this->data([$field => 'must-not-be-stored']))->assertSessionHasErrors($field);
        }
        $this->assertNull(session()->getOldInput('two_factor_recovery_codes'));
        $this->assertSame('Original Customer', $this->customer->fresh()->name);
        $this->assertDatabaseCount('customer_contact_changes', 0);
    }

    public function test_validation_requires_reason_valid_phone_and_profile_version(): void
    {
        foreach (['name' => '', 'phone' => '123', 'reason' => '   x  ', 'profile_version' => 0] as $field => $value) {
            $this->put($this->url(), $this->data([$field => $value]))->assertSessionHasErrors($field);
        }
        $this->assertDatabaseCount('customer_contact_changes', 0);
    }

    public function test_stale_or_repeated_admin_submissions_never_overwrite_a_newer_profile(): void
    {
        $this->put($this->url(), $this->data())->assertSessionHasNoErrors();
        $this->put($this->url(), $this->data(['name' => 'Stale change']))->assertSessionHasErrors('profile_version');
        $this->put($this->url(), $this->data())->assertSessionHasErrors('profile_version');
        $this->assertSame('Corrected Customer', $this->customer->fresh()->name);
        $this->assertDatabaseCount('customer_contact_changes', 1);
    }

    public function test_no_op_corrections_do_not_create_audit_noise_or_increment_revision(): void
    {
        $this->put($this->url(), $this->data(['name' => $this->customer->name, 'phone' => '012-345 6789']))->assertSessionHasNoErrors();
        $this->assertSame(1, $this->customer->fresh()->profile_version);
        $this->assertDatabaseCount('customer_contact_changes', 0);
    }

    public function test_self_service_and_admin_changes_invalidate_each_others_stale_forms(): void
    {
        $this->actingAs($this->customer)->patch('/settings/profile', ['name' => 'Owner Corrected',
            'email' => $this->customer->email, 'phone' => '+60123456789', 'profile_version' => 1])->assertSessionHasNoErrors();
        $this->assertSame(2, $this->customer->fresh()->profile_version);
        $this->actingAs($this->admin)->put($this->url(), $this->data())->assertSessionHasErrors('profile_version');
        $this->put($this->url(), $this->data(['profile_version' => 2]))->assertSessionHasNoErrors();
        $this->actingAs($this->customer)->patch('/settings/profile', ['name' => 'Stale owner edit',
            'email' => $this->customer->email, 'phone' => '+60123456789', 'profile_version' => 2])->assertSessionHasErrors('profile_version');
        $this->assertSame('Corrected Customer', $this->customer->fresh()->name);
        $this->assertSame(3, $this->customer->fresh()->profile_version);
        $this->assertDatabaseCount('customer_contact_changes', 1);
    }

    public function test_self_service_requires_a_version_and_no_op_saves_do_not_advance_it(): void
    {
        $this->actingAs($this->customer);
        $data = ['name' => $this->customer->name, 'email' => $this->customer->email, 'phone' => '012-345 6789'];
        $this->patch('/settings/profile', $data)->assertSessionHasErrors('profile_version');
        $this->patch('/settings/profile', [...$data, 'profile_version' => 1])->assertSessionHasNoErrors();
        $this->assertSame(1, $this->customer->fresh()->profile_version);
        $this->assertDatabaseCount('customer_contact_changes', 0);
    }

    public function test_contact_and_audit_are_rolled_back_together(): void
    {
        DB::beginTransaction();
        app(CustomerContactService::class)->update($this->customer, $this->admin, $this->data());
        $this->assertDatabaseCount('customer_contact_changes', 1);
        DB::rollBack();
        $this->assertSame('Original Customer', $this->customer->fresh()->name);
        $this->assertSame(1, $this->customer->fresh()->profile_version);
        $this->assertDatabaseCount('customer_contact_changes', 0);
    }

    public function test_audit_history_is_paginated_admin_only_and_customer_scoped(): void
    {
        $other = User::factory()->create();
        for ($version = 1; $version <= 11; $version++) {
            app(CustomerContactService::class)->update($this->customer, $this->admin,
                $this->data(['profile_version' => $version, 'name' => 'Contact version '.$version]));
        }
        app(CustomerContactService::class)->update($other, $this->admin, $this->data());
        $this->get($this->url())->assertInertia(fn (Assert $page) => $page
            ->has('contactHistory.data', 10)->where('contactHistory.total', 11)
            ->where('contactHistory.data.0.customer_id', $this->customer->id)->where('contactHistory.data.0.profile_version', 12)
            ->missing('customer.password')->missing('customer.two_factor_secret')->where('customer.profile_version', 12));
        $this->get($this->url().'?contactPage=2')->assertInertia(fn (Assert $page) => $page->has('contactHistory.data', 1));
        $this->actingAs($this->customer)->get($this->url())->assertForbidden();
        $this->get('/settings/profile')->assertInertia(fn (Assert $page) => $page->missing('contactHistory'));
    }

    public function test_existing_nonfinancial_self_deletion_can_remove_contact_only_audit(): void
    {
        $this->put($this->url(), $this->data())->assertSessionHasNoErrors();
        $this->actingAs($this->customer)->delete('/settings/profile', ['password' => 'password'])->assertRedirect('/');
        $this->assertNull($this->customer->fresh());
        $this->assertDatabaseCount('customer_contact_changes', 0);
    }
}
