<?php

namespace Tests\Feature\Settings;

use App\Enums\UserRole;
use App\Models\User;
use App\Models\WebsiteSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProfileUpdateTest extends TestCase
{
    use RefreshDatabase;

    public function test_avatar_can_be_uploaded_and_replaced(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();
        $data = ['_method' => 'patch', 'profile_version' => $user->profile_version, 'name' => $user->name, 'email' => $user->email];
        $this->actingAs($user)->post(route('profile.update'), [...$data, 'avatar' => UploadedFile::fake()->image('avatar.png')])->assertSessionHasNoErrors();
        $user->refresh();
        $oldPath = $user->avatar_path;
        Storage::disk('public')->assertExists($oldPath);
        $this->get(route('profile.edit'))->assertInertia(fn ($page) => $page->where('auth.user.avatar', Storage::disk('public')->url($oldPath)));
        $this->post(route('profile.update'), [...$data, 'profile_version' => $user->profile_version, 'avatar' => UploadedFile::fake()->image('new.png')])->assertSessionHasNoErrors();
        Storage::disk('public')->assertMissing($oldPath);
        Storage::disk('public')->assertExists($user->fresh()->avatar_path);
    }

    public function test_only_admin_can_upload_system_logo(): void
    {
        Storage::fake('public');
        $settings = WebsiteSetting::current();
        $user = User::factory()->create();
        $data = ['profile_version' => $user->profile_version, 'name' => $user->name, 'email' => $user->email, 'website_version' => $settings->version];
        $this->actingAs($user)->patch(route('profile.update'), [...$data, 'logo' => UploadedFile::fake()->image('logo.png')])->assertSessionHasErrors('logo');
        $user->role = UserRole::Admin;
        $user->save();
        $this->patch(route('profile.update'), [...$data, 'logo' => UploadedFile::fake()->image('logo.png')])->assertSessionHasNoErrors();
        Storage::disk('public')->assertExists($settings->fresh()->logo_path);
        $this->assertDatabaseHas('website_changes', ['admin_id' => $user->id, 'entity_type' => 'settings']);
    }

    public function test_invalid_images_are_rejected(): void
    {
        $user = User::factory()->create(['role' => UserRole::Admin]);
        $this->actingAs($user)->patch(route('profile.update'), [
            'profile_version' => $user->profile_version,
            'name' => $user->name,
            'email' => $user->email,
            'website_version' => WebsiteSetting::current()->version,
            'avatar' => UploadedFile::fake()->create('avatar.pdf'),
            'logo' => UploadedFile::fake()->image('large.png')->size(2049),
        ])->assertSessionHasErrors(['avatar', 'logo']);
    }

    public function test_stale_logo_update_rolls_back_avatar_and_profile(): void
    {
        Storage::fake('public');
        $user = User::factory()->create(['role' => UserRole::Admin]);
        $settings = WebsiteSetting::current();
        $this->actingAs($user)->patch(route('profile.update'), [
            'profile_version' => $user->profile_version,
            'name' => 'Changed name',
            'email' => $user->email,
            'website_version' => $settings->version + 1,
            'avatar' => UploadedFile::fake()->image('avatar.png'),
            'logo' => UploadedFile::fake()->image('logo.png'),
        ])->assertSessionHasErrors('version');
        $this->assertSame($user->name, $user->fresh()->name);
        $this->assertNull($user->fresh()->avatar_path);
        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    public function test_profile_page_is_displayed()
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->get(route('profile.edit'));

        $response->assertOk();
    }

    public function test_profile_information_can_be_updated()
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->patch(route('profile.update'), [
                'profile_version' => $user->profile_version,
                'name' => 'Test User',
                'email' => 'test@example.com',
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('profile.edit'));

        $user->refresh();

        $this->assertSame('Test User', $user->name);
        $this->assertSame('test@example.com', $user->email);
        $this->assertNull($user->email_verified_at);
    }

    public function test_email_is_stored_in_lowercase_so_the_user_can_still_log_in()
    {
        $user = User::factory()->create();

        $this
            ->actingAs($user)
            ->patch(route('profile.update'), [
                'profile_version' => $user->profile_version,
                'name' => 'Test User',
                'email' => 'Test@Example.com',
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame('test@example.com', $user->refresh()->email);

        $this->post(route('logout'));

        $this->post(route('login.store'), [
            'email' => 'Test@Example.com',
            'password' => 'password',
        ]);

        $this->assertAuthenticatedAs($user);
    }

    public function test_email_must_be_unique_regardless_of_case()
    {
        User::factory()->create(['email' => 'taken@example.com']);

        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->from(route('profile.edit'))
            ->patch(route('profile.update'), [
                'profile_version' => $user->profile_version,
                'name' => 'Test User',
                'email' => 'Taken@Example.com',
            ]);

        $response
            ->assertSessionHasErrors('email')
            ->assertRedirect(route('profile.edit'));
    }

    public function test_email_verification_status_is_unchanged_when_the_email_address_is_unchanged()
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->patch(route('profile.update'), [
                'profile_version' => $user->profile_version,
                'name' => 'Test User',
                'email' => $user->email,
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('profile.edit'));

        $this->assertNotNull($user->refresh()->email_verified_at);
    }

    public function test_user_can_delete_their_account()
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->delete(route('profile.destroy'), [
                'password' => 'password',
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('home'));

        $this->assertGuest();
        $this->assertNull($user->fresh());
    }

    public function test_correct_password_must_be_provided_to_delete_account()
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->from(route('profile.edit'))
            ->delete(route('profile.destroy'), [
                'password' => 'wrong-password',
            ]);

        $response
            ->assertSessionHasErrors('password')
            ->assertRedirect(route('profile.edit'));

        $this->assertNotNull($user->fresh());
    }
}
