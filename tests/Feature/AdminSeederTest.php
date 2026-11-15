<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use Database\Seeders\AdminSeeder;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use RuntimeException;
use Tests\TestCase;

class AdminSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_default_seeder_creates_a_verified_admin_with_a_hashed_password(): void
    {
        $this->seed(DatabaseSeeder::class);

        $admin = User::where('email', 'admin@csiti.my')->firstOrFail();

        $this->assertSame(UserRole::Admin, $admin->role);
        $this->assertNotNull($admin->email_verified_at);
        $this->assertNotSame('123..', $admin->password);
        $this->assertTrue(Hash::check('123..', $admin->password));

        $this->post(route('login.store'), [
            'email' => 'admin@csiti.my',
            'password' => '123..',
        ])->assertRedirect('/dashboard');
        $this->assertAuthenticatedAs($admin);
        $this->get('/admin/dashboard')->assertOk();
    }

    public function test_reseeding_does_not_reset_existing_credentials_or_security_settings(): void
    {
        $admin = User::factory()->withTwoFactor()->create([
            'email' => 'admin@csiti.my',
            'role' => UserRole::Admin,
            'password' => 'A-new-private-password!',
        ]);
        $original = $admin->refresh()->getAttributes();

        $this->seed(AdminSeeder::class);
        $this->seed(AdminSeeder::class);

        $this->assertSame(1, User::count());
        $this->assertSame($original, $admin->fresh()->getAttributes());
    }

    public function test_other_admin_accounts_are_not_modified(): void
    {
        $existing = User::factory()->create(['role' => UserRole::Admin]);
        $original = $existing->refresh()->getAttributes();

        $this->seed(AdminSeeder::class);

        $this->assertSame(2, User::where('role', UserRole::Admin)->count());
        $this->assertSame($original, $existing->fresh()->getAttributes());
    }

    public function test_default_admin_is_not_created_outside_local_or_testing(): void
    {
        foreach (['production', 'staging'] as $environment) {
            $this->app->instance('env', $environment);
            $this->artisan('db:seed', ['--class' => AdminSeeder::class, '--force' => true])
                ->assertSuccessful();
        }

        $this->assertSame(0, User::count());
    }

    public function test_an_existing_customer_is_not_promoted_or_overwritten(): void
    {
        $customer = User::factory()->create(['email' => 'admin@csiti.my']);
        $original = $customer->refresh()->getAttributes();

        try {
            $this->seed(AdminSeeder::class);
            $this->fail('A conflicting customer email must stop admin seeding.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('non-admin account', $exception->getMessage());
        }

        $this->assertSame($original, $customer->fresh()->getAttributes());
        $this->assertSame(0, User::where('role', UserRole::Admin)->count());
    }
}
