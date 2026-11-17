<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Laravel\Fortify\Features;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_screen_can_be_rendered()
    {
        $response = $this->get(route('login'));

        $response->assertOk();
    }

    public function test_users_can_authenticate_using_the_login_screen()
    {
        $user = User::factory()->create();

        $response = $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('dashboard', absolute: false));
    }

    public function test_passkey_routes_are_not_available(): void
    {
        $this->assertFalse(Features::enabled(Features::passkeys()));

        foreach (['passkey.login-options', 'passkey.login', 'passkey.confirm-options', 'passkey.confirm', 'passkey.registration-options', 'passkey.store', 'passkey.destroy'] as $name) {
            $this->assertFalse(Route::has($name));
        }

        $this->get('/passkeys/login/options')->assertNotFound();
        $this->post('/passkeys/login')->assertNotFound();

        $this->actingAs(User::factory()->create());
        $this->get('/passkeys/confirm/options')->assertNotFound();
        $this->post('/passkeys/confirm')->assertNotFound();
        $this->get('/user/passkeys/options')->assertNotFound();
        $this->post('/user/passkeys')->assertNotFound();
        $this->delete('/user/passkeys/1')->assertNotFound();
    }

    public function test_users_with_two_factor_enabled_are_redirected_to_two_factor_challenge()
    {
        $this->skipUnlessFortifyHas(Features::twoFactorAuthentication());

        Features::twoFactorAuthentication([
            'confirm' => true,
            'confirmPassword' => true,
        ]);

        $user = User::factory()->withTwoFactor()->create();

        $response = $this->post(route('login'), [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $response->assertRedirect(route('two-factor.login'));
        $response->assertSessionHas('login.id', $user->id);
        $this->assertGuest();
    }

    public function test_users_can_not_authenticate_with_invalid_password()
    {
        $user = User::factory()->create();

        $response = $this->withSession(['_previous.url' => route('home')])->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'wrong-password',
        ]);

        $response->assertRedirect(route('login'));
        $response->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_invalid_login_input_returns_to_login_instead_of_the_previous_page(): void
    {
        $response = $this->withSession(['_previous.url' => route('home')])->post(route('login.store'), [
            'email' => '',
            'password' => '',
        ]);

        $response->assertRedirect(route('login'));
        $response->assertSessionHasErrors(['email', 'password']);
        $this->assertGuest();
    }

    public function test_invalid_json_login_returns_validation_errors(): void
    {
        $response = $this->postJson(route('login.store'), [
            'email' => 'missing-user@example.test',
            'password' => 'wrong-password',
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors('email');
        $this->assertGuest();
    }

    public function test_users_can_logout()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('logout'));

        $response->assertRedirect(route('home'));

        $this->assertGuest();
    }

    public function test_users_are_rate_limited()
    {
        $user = User::factory()->create();

        RateLimiter::increment(md5('login'.implode('|', [$user->email, '127.0.0.1'])), amount: 5);

        $response = $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'wrong-password',
        ]);

        $response->assertTooManyRequests();
    }
}
