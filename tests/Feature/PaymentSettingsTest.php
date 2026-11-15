<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Order;
use App\Models\PaymentCallback;
use App\Models\PaymentGatewaySetting;
use App\Models\User;
use App\Services\PaymentWorkflowService;
use App\Support\PublicHttpsUrl;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PaymentSettingsTest extends TestCase
{
    use RefreshDatabase;

    private const SANDBOX_KEY = 'qa-sandbox-key-not-real';

    private const PRODUCTION_KEY = 'qa-production-key-not-real';

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
        config(['app.url' => 'http://localhost:8000', 'toyyibpay' => [
            'enabled' => false, 'environment' => 'sandbox', 'sandbox_secret_key' => '', 'sandbox_category_code' => '',
            'production_secret_key' => '', 'production_category_code' => '',
        ]]);
        $this->admin = User::factory()->create();
        $this->admin->role = UserRole::Admin;
        $this->admin->save();
        $this->actingAs($this->admin);
    }

    private function data(array $changes = []): array
    {
        return [...['enabled' => false, 'environment' => 'sandbox',
            'sandbox_category_code' => 'QASANDBOX', 'production_category_code' => 'QAPRODUCTION',
            'sandbox_secret_key' => '', 'production_secret_key' => '',
            'clear_sandbox_secret_key' => false, 'clear_production_secret_key' => false,
            'version' => PaymentGatewaySetting::current()->version], ...$changes];
    }

    public function test_settings_are_discoverable_and_defaults_do_not_expose_credentials(): void
    {
        $this->get('/admin/payment-settings')->assertInertia(fn (Assert $page) => $page
            ->component('admin/PaymentSettings')->where('settings.enabled', false)->where('settings.environment', 'sandbox')
            ->where('settings.version', 0)->where('storedInDatabase', false)->where('callbackReady', false)
            ->where('sandboxKeyAvailable', false)->where('productionKeyAvailable', false)
            ->missing('settings.sandbox_secret_key')->missing('settings.production_secret_key'));
        $this->assertDatabaseCount('payment_gateway_settings', 0);
    }

    public function test_verified_administrators_alone_can_configure_toyyibpay(): void
    {
        $this->actingAs(User::factory()->create())->get('/admin/payment-settings')->assertForbidden();
        $this->put('/admin/payment-settings', $this->data())->assertForbidden();
        $this->admin->forceFill(['email_verified_at' => null])->save();
        $this->actingAs($this->admin)->put('/admin/payment-settings', $this->data())->assertRedirect('/email/verify');
        auth()->logout();
        $this->get('/admin/payment-settings')->assertRedirect('/login');
    }

    public function test_both_environments_can_be_saved_from_settings_with_encrypted_secrets_and_safe_audit(): void
    {
        $this->put('/admin/payment-settings', $this->data(['sandbox_secret_key' => self::SANDBOX_KEY, 'production_secret_key' => self::PRODUCTION_KEY]))
            ->assertSessionHasNoErrors()->assertRedirect();
        $settings = PaymentGatewaySetting::current();
        $this->assertTrue($settings->configured('sandbox'));
        $this->assertTrue($settings->configured('production'));
        $this->assertSame(self::SANDBOX_KEY, $settings->sandbox_secret_key);
        $this->assertNotSame(self::SANDBOX_KEY, DB::table('payment_gateway_settings')->value('sandbox_secret_key'));
        $this->assertNotSame(self::PRODUCTION_KEY, DB::table('payment_gateway_settings')->value('production_secret_key'));
        $payload = PaymentCallback::where('event', 'settings_updated')->firstOrFail()->payload;
        $this->assertSame($this->admin->id, $payload['admin_id']);
        $this->assertSame(['sandbox', 'production'], $payload['secret_keys_changed']);
        $this->assertStringNotContainsString(self::SANDBOX_KEY, json_encode($payload));
        $this->assertStringNotContainsString(self::PRODUCTION_KEY, json_encode($payload));
        $this->get('/admin/payment-settings')->assertDontSee(self::SANDBOX_KEY)->assertDontSee(self::PRODUCTION_KEY)
            ->assertInertia(fn (Assert $page) => $page->where('storedInDatabase', true)->where('settings.version', 1)->where('sandboxConfigured', true));
        Http::assertNothingSent();
    }

    public function test_blank_keys_keep_existing_values_and_identical_saves_do_not_repeat_audit(): void
    {
        $this->put('/admin/payment-settings', $this->data(['sandbox_secret_key' => self::SANDBOX_KEY]))->assertSessionHasNoErrors();
        $raw = DB::table('payment_gateway_settings')->value('sandbox_secret_key');
        $this->put('/admin/payment-settings', $this->data())->assertSessionHasNoErrors();
        $this->put('/admin/payment-settings', $this->data(['sandbox_secret_key' => self::SANDBOX_KEY]))->assertSessionHasNoErrors();
        $this->assertSame($raw, DB::table('payment_gateway_settings')->value('sandbox_secret_key'));
        $this->assertSame(1, PaymentGatewaySetting::current()->version);
        $this->assertDatabaseCount('payment_callbacks', 1);
    }

    public function test_collection_cannot_be_enabled_without_selected_credentials_or_a_public_https_address(): void
    {
        $this->put('/admin/payment-settings', $this->data(['enabled' => true]))->assertSessionHasErrors('enabled');
        $this->assertDatabaseCount('payment_gateway_settings', 0);
        $this->put('/admin/payment-settings', $this->data(['enabled' => true, 'sandbox_secret_key' => self::SANDBOX_KEY]))
            ->assertSessionHasErrors('enabled');
        config(['app.url' => 'https://shop.example.com']);
        $this->put('/admin/payment-settings', $this->data(['enabled' => true, 'environment' => 'production', 'sandbox_secret_key' => self::SANDBOX_KEY]))
            ->assertSessionHasErrors('enabled');
        $this->assertDatabaseCount('payment_gateway_settings', 0);
        $this->assertDatabaseCount('payment_callbacks', 0);
        Http::assertNothingSent();
    }

    public function test_selected_environment_can_be_enabled_and_disabled_without_provider_calls(): void
    {
        config(['app.url' => 'https://shop.example.com']);
        $this->put('/admin/payment-settings', $this->data(['enabled' => true, 'sandbox_secret_key' => self::SANDBOX_KEY,
            'production_secret_key' => self::PRODUCTION_KEY]))->assertSessionHasNoErrors();
        $this->put('/admin/payment-settings', $this->data(['enabled' => true, 'environment' => 'production']))->assertSessionHasNoErrors();
        $this->assertTrue(PaymentGatewaySetting::current()->enabled);
        $this->assertSame('production', PaymentGatewaySetting::current()->environment);
        $this->put('/admin/payment-settings', $this->data(['environment' => 'production']))->assertSessionHasNoErrors();
        $this->assertFalse(PaymentGatewaySetting::current()->enabled);
        Http::assertNothingSent();
    }

    public function test_removal_is_explicit_and_cannot_remove_the_active_key_while_collection_is_enabled(): void
    {
        config(['app.url' => 'https://shop.example.com']);
        $this->put('/admin/payment-settings', $this->data(['enabled' => true, 'sandbox_secret_key' => self::SANDBOX_KEY]))->assertSessionHasNoErrors();
        $this->put('/admin/payment-settings', $this->data(['enabled' => true, 'clear_sandbox_secret_key' => true]))->assertSessionHasErrors('enabled');
        $this->assertSame(self::SANDBOX_KEY, PaymentGatewaySetting::current()->sandbox_secret_key);
        $this->put('/admin/payment-settings', $this->data(['clear_sandbox_secret_key' => true, 'sandbox_secret_key' => 'replacement-key']))
            ->assertSessionHasErrors('sandbox_secret_key');
        $this->put('/admin/payment-settings', $this->data(['clear_sandbox_secret_key' => true]))->assertSessionHasNoErrors();
        $this->assertNull(PaymentGatewaySetting::current()->sandbox_secret_key);
        $this->assertFalse(PaymentGatewaySetting::current()->configured('sandbox'));
    }

    public function test_stale_form_versions_cannot_overwrite_credentials_and_secrets_are_not_flashed_on_errors(): void
    {
        $this->put('/admin/payment-settings', $this->data(['sandbox_secret_key' => self::SANDBOX_KEY]))->assertSessionHasNoErrors();
        $this->put('/admin/payment-settings', $this->data(['version' => 0, 'sandbox_secret_key' => 'stale-key-not-real']))
            ->assertSessionHasErrors('version')->assertSessionMissing('_old_input.sandbox_secret_key');
        $this->put('/admin/payment-settings', $this->data(['environment' => 'invalid', 'production_secret_key' => self::PRODUCTION_KEY]))
            ->assertSessionHasErrors('environment')->assertSessionMissing('_old_input.production_secret_key');
        $this->assertSame(self::SANDBOX_KEY, PaymentGatewaySetting::current()->sandbox_secret_key);
        $this->assertDatabaseCount('payment_callbacks', 1);
    }

    public function test_environment_defaults_are_migrated_to_database_on_save_and_database_then_takes_precedence(): void
    {
        config(['toyyibpay.sandbox_secret_key' => self::SANDBOX_KEY, 'toyyibpay.sandbox_category_code' => 'FROMENV']);
        $this->get('/admin/payment-settings')->assertInertia(fn (Assert $page) => $page->where('storedInDatabase', false)->where('sandboxConfigured', true));
        $this->put('/admin/payment-settings', $this->data(['sandbox_category_code' => 'FROMENV']))->assertSessionHasNoErrors();
        config(['toyyibpay.sandbox_secret_key' => 'changed-env-key-not-real']);
        $this->assertSame(self::SANDBOX_KEY, PaymentGatewaySetting::current()->sandbox_secret_key);
        $this->assertSame(1, PaymentGatewaySetting::current()->id);
        $this->assertDatabaseCount('payment_gateway_settings', 1);
    }

    public function test_new_attempts_use_saved_credentials_while_existing_attempts_keep_their_snapshot(): void
    {
        config(['app.url' => 'https://shop.example.com']);
        $this->put('/admin/payment-settings', $this->data(['enabled' => true, 'sandbox_secret_key' => self::SANDBOX_KEY]))->assertSessionHasNoErrors();
        Http::fake(['dev.toyyibpay.com/*' => Http::sequence()->push([['BillCode' => 'billalpha123']])->push([['BillCode' => 'billbeta123']])]);
        $first = app(PaymentWorkflowService::class)->start($this->order());
        $this->put('/admin/payment-settings', $this->data(['enabled' => true, 'sandbox_category_code' => 'NEWCATEGORY', 'sandbox_secret_key' => 'replacement-sandbox-key-not-real']))
            ->assertSessionHasNoErrors();
        $second = app(PaymentWorkflowService::class)->start($this->order());
        $this->assertSame(self::SANDBOX_KEY, $first->fresh()->secret_key_snapshot);
        $this->assertSame('replacement-sandbox-key-not-real', $second->fresh()->secret_key_snapshot);
        Http::assertSent(fn ($request) => $request['userSecretKey'] === self::SANDBOX_KEY && $request['categoryCode'] === 'QASANDBOX');
        Http::assertSent(fn ($request) => $request['userSecretKey'] === 'replacement-sandbox-key-not-real' && $request['categoryCode'] === 'NEWCATEGORY');
    }

    private function order(): Order
    {
        return Order::create(['user_id' => $this->admin->id, 'reference' => 'SETTINGS-'.Str::random(20), 'checkout_key' => Str::uuid(),
            'customer_name' => 'QA account', 'customer_email' => $this->admin->email, 'customer_phone' => '+60123456789',
            'subtotal_sen' => 1000, 'shipping_fee_sen' => 100, 'total_sen' => 1100, 'expires_at' => now()->addHour()]);
    }

    public static function callbackUrls(): array
    {
        return [
            'public https' => ['https://shop.example.com', true],
            'https subpath' => ['https://shop.example.com/collective', true],
            'http' => ['http://shop.example.com', false],
            'localhost' => ['https://localhost', false],
            'localhost trailing dot' => ['https://localhost.', false],
            'local subdomain' => ['https://shop.localhost', false],
            'private address' => ['https://192.168.1.10', false],
            'loopback v6' => ['https://[::1]', false],
            'short loopback' => ['https://127.1', false],
            'hex loopback' => ['https://0x7f.1', false],
            'test domain' => ['https://shop.test', false],
            'url credentials' => ['https://user:pass@shop.example.com', false],
            'url query' => ['https://shop.example.com?redirect=1', false],
            'url fragment' => ['https://shop.example.com#fragment', false],
        ];
    }

    #[DataProvider('callbackUrls')]
    public function test_callback_configuration_screening_does_not_accept_local_or_unsafe_addresses(string $url, bool $ready): void
    {
        $this->assertSame($ready, PublicHttpsUrl::configured($url));
    }
}
