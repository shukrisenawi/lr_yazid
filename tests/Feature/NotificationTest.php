<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class NotificationTest extends TestCase
{
    use RefreshDatabase;

    private function notice(User $user, bool $read = false): DatabaseNotification
    {
        return $user->notifications()->create([
            'id' => (string) Str::uuid(),
            'type' => 'test',
            'data' => ['title' => 'Test notification', 'message' => 'A new update.', 'url' => '/dashboard'],
            'read_at' => $read ? now() : null,
        ]);
    }

    public function test_bell_count_and_list_only_include_the_signed_in_users_notifications(): void
    {
        $user = User::factory()->create();
        $unread = $this->notice($user);
        $this->notice($user, true);
        $this->notice(User::factory()->create());

        $this->actingAs($user)->get('/notifications')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('customer/Notifications')
            ->where('unreadNotificationCount', 1)
            ->has('notifications.data', 2));

        $this->from('/dashboard')->post("/notifications/{$unread->id}/read")->assertRedirect('/notifications');
        $this->assertNotNull($unread->fresh()->read_at);
        $this->get('/dashboard')->assertInertia(fn (Assert $page) => $page->where('unreadNotificationCount', 0));
    }

    public function test_mark_all_reads_every_page_without_changing_another_users_notifications(): void
    {
        $user = User::factory()->create();
        for ($index = 0; $index < 21; $index++) {
            $this->notice($user);
        }
        $other = $this->notice(User::factory()->create());

        $this->actingAs($user)->from('/dashboard')->post('/notifications/read-all')->assertRedirect('/notifications');

        $this->assertSame(0, $user->unreadNotifications()->count());
        $this->assertNull($other->fresh()->read_at);
    }

    public function test_users_cannot_mark_someone_elses_notification_as_read(): void
    {
        $notice = $this->notice(User::factory()->create());
        $this->actingAs(User::factory()->create())->post("/notifications/{$notice->id}/read")->assertNotFound();
        $this->assertNull($notice->fresh()->read_at);
    }

    public function test_admins_can_access_the_bell_and_their_notifications(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $this->notice($admin);
        $this->actingAs($admin)->get('/admin/dashboard')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('unreadNotificationCount', 1));
        $this->get('/notifications')->assertOk();
    }

    public function test_guests_cannot_read_or_change_notifications(): void
    {
        $notice = $this->notice(User::factory()->create());
        $this->get('/notifications')->assertRedirect('/login');
        $this->post('/notifications/read-all')->assertRedirect('/login');
        $this->post("/notifications/{$notice->id}/read")->assertRedirect('/login');
    }
}
