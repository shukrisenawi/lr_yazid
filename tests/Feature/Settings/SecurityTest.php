<?php

namespace Tests\Feature\Settings;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Inertia\Inertia;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Fortify\Features;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

class SecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_security_page_is_displayed()
    {
        $this->skipUnlessFortifyHas(Features::twoFactorAuthentication());

        Features::twoFactorAuthentication([
            'confirm' => true,
            'confirmPassword' => true,
        ]);

        $user = User::factory()->create();

        $this->actingAs($user)
            ->withSession(['auth.password_confirmed_at' => time()])
            ->get(route('security.edit'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('settings/Security')
                ->missing('canManagePasskeys')
                ->missing('passkeys')
                ->where('canManageTwoFactor', true)
                ->where('twoFactorEnabled', false),
            );
    }

    public function test_security_page_requires_password_confirmation_when_enabled()
    {
        $this->skipUnlessFortifyHas(Features::twoFactorAuthentication());

        $user = User::factory()->create();

        Features::twoFactorAuthentication([
            'confirm' => true,
            'confirmPassword' => true,
        ]);

        $response = $this->actingAs($user)
            ->get(route('security.edit'));

        $response->assertRedirect(route('password.confirm'));
    }

    public function test_security_page_renders_without_two_factor_when_feature_is_disabled()
    {
        $this->skipUnlessFortifyHas(Features::twoFactorAuthentication());

        config(['fortify.features' => []]);

        $user = User::factory()->create();

        $this->actingAs($user)
            ->withSession(['auth.password_confirmed_at' => time()])
            ->get(route('security.edit'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('settings/Security')
                ->missing('canManagePasskeys')
                ->missing('passkeys')
                ->where('canManageTwoFactor', false)
                ->missing('twoFactorEnabled')
                ->missing('requiresConfirmation'),
            );
    }

    public function test_password_can_be_updated()
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->from(route('security.edit'))
            ->put(route('user-password.update'), [
                'current_password' => 'password',
                'password' => 'new-password',
                'password_confirmation' => 'new-password',
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('security.edit'));

        $this->assertTrue(Hash::check('new-password', $user->refresh()->password));
    }

    public function test_correct_password_must_be_provided_to_update_password()
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->from(route('security.edit'))
            ->put(route('user-password.update'), [
                'current_password' => 'wrong-password',
                'password' => 'new-password',
                'password_confirmation' => 'new-password',
            ]);

        $response
            ->assertSessionHasErrors('current_password')
            ->assertRedirect(route('security.edit'));
    }

    public function test_invalid_two_factor_confirmation_can_be_retried_without_losing_setup(): void
    {
        $this->skipUnlessFortifyHas(Features::twoFactorAuthentication());
        $user = User::factory()->create([
            'two_factor_secret' => encrypt('JBSWY3DPEHPK3PXP'),
            'two_factor_recovery_codes' => encrypt(json_encode(['qa-recovery-code'])),
            'two_factor_confirmed_at' => null,
        ]);
        $this->actingAs($user)->withSession([
            'auth.password_confirmed_at' => time(),
            'two_factor_empty_at' => time() - 10,
            'two_factor_confirming_at' => time() - 5,
        ])->from(route('security.edit'))
            ->post(route('two-factor.confirm'), ['code' => ''])
            ->assertSessionHasErrors('code', null, 'confirmTwoFactorAuthentication');

        $this->get(route('security.edit'))->assertOk();
        $this->assertNotNull($user->fresh()->two_factor_secret);
        $this->assertNull($user->fresh()->two_factor_confirmed_at);

        $code = (new Google2FA)->getCurrentOtp('JBSWY3DPEHPK3PXP');
        $this->from(route('security.edit'))
            ->post(route('two-factor.confirm'), ['code' => $code])
            ->assertSessionHasNoErrors();
        $this->assertNotNull($user->fresh()->two_factor_confirmed_at);
    }

    public function test_notification_poll_does_not_cancel_pending_two_factor_setup(): void
    {
        $this->skipUnlessFortifyHas(Features::twoFactorAuthentication());
        $this->get('/')->assertOk();
        $user = User::factory()->create([
            'two_factor_secret' => encrypt('JBSWY3DPEHPK3PXP'),
            'two_factor_confirmed_at' => null,
        ]);
        $this->actingAs($user)->withSession([
            'auth.password_confirmed_at' => time(),
            'two_factor_empty_at' => time() - 40,
            'two_factor_confirming_at' => time() - 30,
        ])->get(route('security.edit'), [
            'X-Inertia' => 'true',
            'X-Inertia-Version' => Inertia::getVersion(),
            'X-Inertia-Partial-Component' => 'settings/Security',
            'X-Inertia-Partial-Data' => 'unreadNotificationCount',
        ])->assertOk();

        $this->assertNotNull($user->fresh()->two_factor_secret);
        $this->assertNull($user->fresh()->two_factor_confirmed_at);

        // A real full-page revisit still cleans up abandoned confirmation.
        $this->get(route('security.edit'))->assertOk();
        $this->assertNull($user->fresh()->two_factor_secret);
    }
}
