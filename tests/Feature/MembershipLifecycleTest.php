<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Membership;
use App\Models\MembershipPlan;
use App\Models\MembershipReminder;
use App\Models\User;
use App\Notifications\MembershipNotice;
use App\Services\MembershipLifecycleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class MembershipLifecycleTest extends TestCase
{
    use RefreshDatabase;

    private function membership(User $user): Membership
    {
        $plan = MembershipPlan::create(['name' => 'Annual VIP', 'price_sen' => 19900, 'duration_months' => 12]);

        return Membership::create(['user_id' => $user->id, 'membership_plan_id' => $plan->id, 'status' => 'active', 'starts_at' => now()->subMonth(), 'expires_at' => now()->addDays(30)]);
    }

    public function test_reminders_and_expiry_are_queued_once_per_membership_period(): void
    {
        Notification::fake();
        $this->freezeTime();
        $user = User::factory()->create();
        $membership = $this->membership($user);
        $service = app(MembershipLifecycleService::class);
        $service->process();
        $service->process();
        $this->assertDatabaseCount('membership_reminders', 1);
        $this->travel(23)->days();
        $service->process();
        $this->travel(6)->days();
        $service->process();
        $this->travel(1)->days();
        $service->process();
        $service->process();
        $this->assertDatabaseCount('membership_reminders', 4);
        $this->assertSame('expired', $membership->fresh()->status);
        Notification::assertSentToTimes($user, MembershipNotice::class, 4);
        $this->actingAs($user)->get('/vip/dashboard')->assertForbidden();
    }

    public function test_administrator_adjustments_require_reason_and_create_an_audit(): void
    {
        Notification::fake();
        $this->freezeTime();
        $user = User::factory()->create();
        $membership = $this->membership($user);
        $admin = User::factory()->create();
        $admin->role = UserRole::Admin;
        $admin->save();
        $expiry = now()->addMonthsNoOverflow(3);
        $this->actingAs($admin)->put("/admin/memberships/$membership->id/expiry", ['expires_at' => $expiry->toDateTimeString(), 'reason' => ''])->assertSessionHasErrors('reason');
        $this->assertDatabaseCount('membership_adjustments', 0);
        $this->put("/admin/memberships/$membership->id/expiry", ['expires_at' => $expiry->toDateTimeString(), 'reason' => 'Customer support correction'])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('membership_adjustments', ['admin_id' => $admin->id, 'membership_id' => $membership->id, 'reason' => 'Customer support correction', 'new_expires_at' => $expiry->toDateTimeString()]);
        $this->assertSame($expiry->toDateTimeString(), $membership->fresh()->expires_at->toDateTimeString());
        Notification::assertSentTo($user, MembershipNotice::class);
    }

    public function test_customers_cannot_adjust_memberships(): void
    {
        $user = User::factory()->create();
        $membership = $this->membership($user);
        $this->actingAs($user)->put("/admin/memberships/$membership->id/expiry", ['expires_at' => now()->addYear()->toDateTimeString(), 'reason' => 'Attempted privilege change'])->assertForbidden();
    }

    public function test_database_notification_delivery_is_tracked_and_owner_protected(): void
    {
        $user = User::factory()->create();
        $membership = $this->membership($user);
        $reminder = MembershipReminder::create(['user_id' => $user->id, 'membership_id' => $membership->id, 'expires_at' => $membership->expires_at, 'kind' => '30_days', 'queued_at' => now()]);
        $notice = new MembershipNotice('30_days', 'Annual VIP', $membership->expires_at->format('d M Y H:i'), $reminder->id);
        $user->notifyNow($notice, ['database']);
        $this->assertNotNull($reminder->fresh()->database_sent_at);
        $notification = $user->notifications()->firstOrFail();
        $this->actingAs(User::factory()->create())->post("/notifications/$notification->id/read")->assertNotFound();
        $this->actingAs($user)->post("/notifications/$notification->id/read")->assertRedirect();
        $this->assertNotNull($notification->fresh()->read_at);
    }
}
