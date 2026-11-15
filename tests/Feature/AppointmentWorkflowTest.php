<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Appointment;
use App\Models\AppointmentNoticeDelivery;
use App\Models\AppointmentService;
use App\Models\AppointmentSlot;
use App\Models\MembershipPlan;
use App\Models\User;
use App\Notifications\AppointmentNotice;
use App\Services\AppointmentBookingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AppointmentWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private AppointmentService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->freezeTime();
        Notification::fake();
        $this->admin = User::factory()->create();
        $this->admin->role = UserRole::Admin;
        $this->admin->save();
        $this->service = AppointmentService::create(['name' => 'Consultation', 'location' => 'Kuala Lumpur']);
    }

    private function vip(): User
    {
        $user = User::factory()->create();
        $plan = MembershipPlan::create(['name' => 'VIP', 'price_sen' => 19900, 'duration_months' => 12]);
        $user->memberships()->create(['membership_plan_id' => $plan->id, 'status' => 'active', 'starts_at' => now()->subDay(), 'expires_at' => now()->addYear()]);

        return $user;
    }

    private function slot(int $days = 3, ?AppointmentService $service = null): AppointmentSlot
    {
        return ($service ?? $this->service)->slots()->create(['created_by' => $this->admin->id, 'starts_at' => now()->addDays($days),
            'ends_at' => now()->addDays($days)->addHour(), 'capacity' => 1, 'location' => 'KL', 'notes' => 'Private staff note', 'status' => 'available']);
    }

    private function book(User $user, AppointmentSlot $slot): Appointment
    {
        $this->actingAs($user)->post('/vip/appointments', ['slot_id' => $slot->id, 'booking_key' => (string) Str::uuid(), 'purpose' => 'Discuss membership benefits'])
            ->assertSessionHasNoErrors()->assertRedirect();

        return Appointment::where('user_id', $user->id)->where('appointment_slot_id', $slot->id)->latest('id')->firstOrFail();
    }

    private function approve(Appointment $appointment): void
    {
        $this->actingAs($this->admin)->put("/admin/appointments/$appointment->id", ['decision' => 'approved'])->assertSessionHasNoErrors();
    }

    public function test_booking_is_pending_and_two_customers_can_request_the_final_place(): void
    {
        $slot = $this->slot();
        $first = $this->book($this->vip(), $slot);
        $second = $this->book($this->vip(), $slot);
        $this->assertSame('pending', $first->status);
        $this->assertSame('pending', $second->status);
        $this->assertSame(0, Appointment::where('status', 'approved')->count());
        $this->assertDatabaseHas('appointment_status_histories', ['appointment_id' => $first->id, 'event' => 'submitted', 'new_status' => 'pending']);
        Notification::assertSentTo($this->admin, AppointmentNotice::class);
    }

    public function test_idempotency_key_and_duplicate_active_request_do_not_duplicate_records_or_notices(): void
    {
        $user = $this->vip();
        $slot = $this->slot();
        $data = ['slot_id' => $slot->id, 'booking_key' => (string) Str::uuid(), 'purpose' => 'Consultation request'];
        $this->actingAs($user)->post('/vip/appointments', $data)->assertSessionHasNoErrors();
        $this->post('/vip/appointments', $data)->assertSessionHasNoErrors();
        $this->post('/vip/appointments', [...$data, 'booking_key' => (string) Str::uuid()])->assertSessionHasNoErrors();
        $this->assertDatabaseCount('appointments', 1);
        $this->assertDatabaseCount('appointment_status_histories', 1);
        Notification::assertSentToTimes($user, AppointmentNotice::class, 1);
    }

    public function test_normal_expired_and_admin_without_vip_cannot_book_or_access_calendar(): void
    {
        $slot = $this->slot();
        $expired = $this->vip();
        $expired->memberships()->update(['expires_at' => now()]);
        foreach ([User::factory()->create(), $expired, $this->admin] as $user) {
            $this->actingAs($user)->get('/vip/appointments/book')->assertForbidden();
            $this->post('/vip/appointments', ['slot_id' => $slot->id, 'booking_key' => (string) Str::uuid(), 'purpose' => 'Not eligible'])->assertForbidden();
            $this->get('/appointments')->assertOk();
        }
        $this->assertDatabaseCount('appointments', 0);
    }

    public function test_approval_rechecks_capacity_and_is_idempotent(): void
    {
        $slot = $this->slot();
        $first = $this->book($this->vip(), $slot);
        $second = $this->book($this->vip(), $slot);
        $this->approve($first);
        $this->approve($first);
        $this->put("/admin/appointments/$second->id", ['decision' => 'approved'])->assertSessionHasErrors('appointment');
        $this->assertSame('pending', $second->fresh()->status);
        $this->assertSame(1, Appointment::where('status', 'approved')->count());
        $this->assertSame(2, $first->histories()->count());
    }

    public function test_membership_must_cover_end_exclusively_and_is_rechecked_on_approval(): void
    {
        $user = $this->vip();
        $slot = $this->slot();
        $appointment = $this->book($user, $slot);
        $user->memberships()->update(['expires_at' => $slot->ends_at]);
        $this->actingAs($this->admin)->put("/admin/appointments/$appointment->id", ['decision' => 'approved'])->assertSessionHasErrors('appointment');
        $user->memberships()->update(['expires_at' => $slot->ends_at->addSecond()]);
        $this->approve($appointment);
    }

    public function test_conflicts_across_services_and_active_booking_limit_are_enforced(): void
    {
        $user = $this->vip();
        $this->book($user, $this->slot());
        $other = AppointmentService::create(['name' => 'Other service', 'location' => 'KL']);
        $slot = $this->slot(3, $other);
        $this->actingAs($user)->post('/vip/appointments', ['slot_id' => $slot->id, 'booking_key' => (string) Str::uuid(), 'purpose' => 'Overlapping request'])->assertSessionHasErrors('appointment');
        $this->service->update(['maximum_active_bookings' => 1]);
        $slot = $this->slot(4);
        $this->post('/vip/appointments', ['slot_id' => $slot->id, 'booking_key' => (string) Str::uuid(), 'purpose' => 'Another request'])->assertSessionHasErrors('appointment');
        $this->assertDatabaseCount('appointments', 1);
    }

    public function test_minimum_notice_advance_limit_and_slot_status_are_server_enforced(): void
    {
        $user = $this->vip();
        $slot = $this->slot();
        foreach ([
            ['starts_at' => now()->addHour(), 'ends_at' => now()->addHours(2)],
            ['starts_at' => now()->addDays(100), 'ends_at' => now()->addDays(100)->addHour()],
            ['starts_at' => now()->addDays(3), 'ends_at' => now()->addDays(3)->addHour(), 'status' => 'disabled'],
        ] as $change) {
            $slot->update($change);
            $this->actingAs($user)->post('/vip/appointments', ['slot_id' => $slot->id, 'booking_key' => (string) Str::uuid(), 'purpose' => 'Invalid slot request'])->assertSessionHasErrors('appointment');
        }
        $this->assertDatabaseCount('appointments', 0);
    }

    public function test_owner_cancellation_releases_capacity_and_closes_reschedules(): void
    {
        $user = $this->vip();
        $slot = $this->slot();
        $appointment = $this->book($user, $slot);
        $this->approve($appointment);
        $target = $this->slot(4);
        $this->actingAs($user)->post("/appointments/$appointment->id/reschedule", ['slot_id' => $target->id, 'reason' => 'Please change the date'])->assertSessionHasNoErrors();
        $this->post("/appointments/$appointment->id/cancel", ['reason' => 'Plans have changed'])->assertSessionHasNoErrors();
        $this->post("/appointments/$appointment->id/cancel", ['reason' => 'Plans have changed'])->assertSessionHasNoErrors();
        $this->assertNull($appointment->fresh()->active_slot_id);
        $this->assertSame('cancelled', $appointment->rescheduleRequests()->firstOrFail()->status);
        $next = $this->book($this->vip(), $slot);
        $this->approve($next);
    }

    public function test_customer_cutoff_is_enforced_but_admin_can_cancel_with_a_reason(): void
    {
        $user = $this->vip();
        $appointment = $this->book($user, $this->slot());
        $this->approve($appointment);
        $this->travel(60)->hours();
        $this->actingAs($user)->post("/appointments/$appointment->id/cancel", ['reason' => 'Too late to cancel'])->assertSessionHasErrors('appointment');
        $this->actingAs($this->admin)->put("/admin/appointments/$appointment->id", ['decision' => 'cancelled'])->assertSessionHasErrors('remarks');
        $this->put("/admin/appointments/$appointment->id", ['decision' => 'cancelled', 'remarks' => 'Customer contacted support'])->assertSessionHasNoErrors();
        $this->assertSame($this->admin->id, $appointment->fresh()->cancelled_by);
    }

    public function test_reschedule_keeps_original_until_atomic_approval_and_records_both_slots(): void
    {
        $user = $this->vip();
        $old = $this->slot();
        $target = $this->slot(4);
        $appointment = $this->book($user, $old);
        $this->approve($appointment);
        $this->actingAs($user)->post("/appointments/$appointment->id/reschedule", ['slot_id' => $target->id, 'reason' => 'Please change the date'])->assertSessionHasNoErrors();
        $request = $appointment->rescheduleRequests()->firstOrFail();
        $this->assertSame($old->id, $appointment->fresh()->appointment_slot_id);
        $this->assertSame('approved', $appointment->fresh()->status);
        $this->actingAs($this->admin)->put("/admin/appointment-reschedules/$request->id", ['approve' => true])->assertSessionHasNoErrors();
        $this->put("/admin/appointment-reschedules/$request->id", ['approve' => true])->assertSessionHasNoErrors();
        $this->assertSame($target->id, $appointment->fresh()->appointment_slot_id);
        $this->assertSame($target->id, $appointment->fresh()->active_slot_id);
        $this->assertDatabaseHas('appointment_status_histories', ['appointment_id' => $appointment->id, 'event' => 'reschedule_approved', 'previous_slot_id' => $old->id, 'new_slot_id' => $target->id]);
        $this->assertSame(1, $appointment->histories()->where('event', 'reschedule_approved')->count());
        $history = $appointment->histories()->where('event', 'reschedule_approved')->firstOrFail();
        $originalStart = $old->starts_at->toDateTimeString();
        $this->put("/admin/appointment-services/{$this->service->id}/slots/$old->id", [
            'starts_at' => $old->starts_at->addDays(10)->toDateTimeString(), 'ends_at' => $old->ends_at->addDays(10)->toDateTimeString(),
            'capacity' => 1, 'location' => 'Updated venue', 'status' => 'available',
        ])->assertSessionHasNoErrors();
        $this->assertSame($originalStart, $history->fresh()->previous_starts_at->toDateTimeString());
    }

    public function test_failed_move_to_full_target_rolls_back_without_releasing_original(): void
    {
        $user = $this->vip();
        $old = $this->slot();
        $target = $this->slot(4);
        $appointment = $this->book($user, $old);
        $this->approve($appointment);
        $this->actingAs($user)->post("/appointments/$appointment->id/reschedule", ['slot_id' => $target->id, 'reason' => 'Please change the date'])->assertSessionHasNoErrors();
        $request = $appointment->rescheduleRequests()->firstOrFail();
        $this->approve($this->book($this->vip(), $target));
        $this->actingAs($this->admin)->put("/admin/appointment-reschedules/$request->id", ['approve' => true])->assertSessionHasErrors('appointment');
        $this->assertSame($old->id, $appointment->fresh()->appointment_slot_id);
        $this->assertSame('pending', $request->fresh()->status);
    }

    public function test_rejected_reschedule_preserves_original_and_another_request_can_be_submitted(): void
    {
        $user = $this->vip();
        $appointment = $this->book($user, $this->slot());
        $this->approve($appointment);
        $target = $this->slot(4);
        $data = ['slot_id' => $target->id, 'reason' => 'Please change the date'];
        $this->actingAs($user)->post("/appointments/$appointment->id/reschedule", $data)->assertSessionHasNoErrors();
        $this->post("/appointments/$appointment->id/reschedule", $data)->assertSessionHasErrors('appointment');
        $request = $appointment->rescheduleRequests()->firstOrFail();
        $this->actingAs($this->admin)->put("/admin/appointment-reschedules/$request->id", ['approve' => false, 'remarks' => 'Please select another time'])->assertSessionHasNoErrors();
        $this->assertSame('approved', $appointment->fresh()->status);
        $this->actingAs($user)->post("/appointments/$appointment->id/reschedule", $data)->assertSessionHasNoErrors();
        $this->assertSame(2, $appointment->rescheduleRequests()->count());
    }

    public function test_admin_alternative_requires_customer_acceptance_and_then_admin_approval(): void
    {
        $user = $this->vip();
        $old = $this->slot();
        $appointment = $this->book($user, $old);
        $target = $this->slot(4);
        $this->actingAs($this->admin)->post("/admin/appointments/$appointment->id/propose", ['slot_id' => $target->id, 'reason' => 'A better time is available'])->assertSessionHasNoErrors();
        $request = $appointment->rescheduleRequests()->firstOrFail();
        $this->put("/admin/appointment-reschedules/$request->id", ['approve' => true])->assertSessionHasErrors('appointment');
        $this->actingAs($user)->post("/appointment-reschedules/$request->id/respond", ['accept' => true])->assertSessionHasNoErrors();
        $this->assertSame('pending', $appointment->fresh()->status);
        $this->assertSame($old->id, $appointment->fresh()->appointment_slot_id);
        $this->actingAs($this->admin)->put("/admin/appointment-reschedules/$request->id", ['approve' => true])->assertSessionHasNoErrors();
        $this->assertSame('approved', $appointment->fresh()->status);
        $this->assertSame($target->id, $appointment->fresh()->appointment_slot_id);
    }

    public function test_expired_members_keep_owner_history_but_cannot_reschedule_and_cannot_read_other_accounts(): void
    {
        $user = $this->vip();
        $appointment = $this->book($user, $this->slot());
        $this->approve($appointment);
        $target = $this->slot(4);
        $user->memberships()->update(['expires_at' => now()]);
        $this->actingAs($user)->get("/appointments/$appointment->id")->assertOk();
        $this->post("/appointments/$appointment->id/reschedule", ['slot_id' => $target->id, 'reason' => 'Not eligible now'])->assertForbidden();
        $this->actingAs($this->vip())->get("/appointments/$appointment->id")->assertForbidden();
        $this->post("/appointments/$appointment->id/cancel", ['reason' => 'Not my appointment'])->assertForbidden();
        $this->get("/admin/appointments/$appointment->id")->assertForbidden();
    }

    public function test_future_appointment_cannot_be_completed_and_terminal_statuses_clear_active_key(): void
    {
        $appointment = $this->book($this->vip(), $this->slot());
        $this->approve($appointment);
        $this->put("/admin/appointments/$appointment->id", ['decision' => 'completed'])->assertSessionHasErrors('appointment');
        $this->travel(4)->days();
        $this->put("/admin/appointments/$appointment->id", ['decision' => 'completed'])->assertSessionHasNoErrors();
        $this->assertNull($appointment->fresh()->active_slot_id);
        $this->put("/admin/appointments/$appointment->id", ['decision' => 'approved'])->assertSessionHasErrors('appointment');
    }

    public function test_calendar_does_not_leak_internal_notes_and_admin_views_work(): void
    {
        $user = $this->vip();
        $slot = $this->slot();
        $month = $slot->starts_at->format('Y-m');
        $this->actingAs($user)->get("/vip/appointments/book?service={$this->service->id}&month=$month")
            ->assertInertia(fn (Assert $page) => $page->has('slots', 1)->missing('slots.0.notes')->missing('slots.0.created_by'));
        $this->actingAs($this->admin)->get('/admin/appointments')->assertOk();
        foreach (['month', 'week', 'day', 'list'] as $view) {
            $this->get("/admin/appointments/calendar?view=$view")->assertOk();
        }
    }

    public function test_active_target_and_history_prevent_unsafe_slot_mutation_or_deletion(): void
    {
        $user = $this->vip();
        $appointment = $this->book($user, $this->slot());
        $this->approve($appointment);
        $target = $this->slot(4);
        $this->actingAs($user)->post("/appointments/$appointment->id/reschedule", ['slot_id' => $target->id, 'reason' => 'Please change the date'])->assertSessionHasNoErrors();
        $this->actingAs($this->admin)->delete("/admin/appointment-services/{$this->service->id}/slots/$target->id")->assertSessionHasErrors('slot');
        $this->put("/admin/appointment-services/{$this->service->id}/slots/$target->id", [
            'starts_at' => $target->starts_at->addDay()->toDateTimeString(), 'ends_at' => $target->ends_at->addDay()->toDateTimeString(),
            'capacity' => 1, 'location' => 'KL', 'status' => 'available',
        ])->assertSessionHasErrors('slot');
    }

    public function test_reminders_are_deduplicated_and_stale_reminders_are_suppressed(): void
    {
        $user = $this->vip();
        $appointment = $this->book($user, $this->slot());
        $this->approve($appointment);
        $this->travel(48)->hours();
        $this->artisan('appointments:send-reminders')->assertSuccessful();
        $this->artisan('appointments:send-reminders')->assertSuccessful();
        $this->assertSame(1, AppointmentNoticeDelivery::where('event_key', 'like', 'reminder_24:%')->count());
        $this->travel(23)->hours();
        $this->artisan('appointments:send-reminders')->assertSuccessful();
        $this->artisan('appointments:send-reminders')->assertSuccessful();
        $this->assertSame(1, AppointmentNoticeDelivery::where('event_key', 'like', 'reminder_1:%')->count());
        $notice = Notification::sent($user, AppointmentNotice::class)->first(fn ($notice) => $notice->kind === 'reminder_1');
        $this->assertTrue($notice->shouldSend($user, 'mail'));
        app(AppointmentBookingService::class)->cancel($appointment, $this->admin, 'Support cancellation', true);
        $this->assertFalse($notice->shouldSend($user, 'mail'));
    }

    public function test_rejection_releases_active_key_and_customer_cannot_review(): void
    {
        $user = $this->vip();
        $slot = $this->slot();
        $appointment = $this->book($user, $slot);
        $this->actingAs($user)->put("/admin/appointments/$appointment->id", ['decision' => 'approved'])->assertForbidden();
        $this->actingAs($this->admin)->put("/admin/appointments/$appointment->id", ['decision' => 'rejected', 'remarks' => 'Please choose another date'])->assertSessionHasNoErrors();
        $this->assertNull($appointment->fresh()->active_slot_id);
        $this->approve($this->book($this->vip(), $slot));
    }

    public function test_reschedule_cannot_change_service_or_move_another_customers_appointment(): void
    {
        $user = $this->vip();
        $appointment = $this->book($user, $this->slot());
        $this->approve($appointment);
        $other = AppointmentService::create(['name' => 'Other service', 'location' => 'KL']);
        $target = $this->slot(4, $other);
        $this->actingAs($user)->post("/appointments/$appointment->id/reschedule", ['slot_id' => $target->id, 'reason' => 'Change to another service'])->assertSessionHasErrors('appointment');
        $this->actingAs($this->vip())->post("/appointments/$appointment->id/reschedule", ['slot_id' => $target->id, 'reason' => 'Not my appointment'])->assertForbidden();
        $this->assertDatabaseCount('appointment_reschedule_requests', 0);
    }

    public function test_proposal_response_is_owner_protected_and_declining_preserves_original(): void
    {
        $user = $this->vip();
        $old = $this->slot();
        $appointment = $this->book($user, $old);
        $target = $this->slot(4);
        $this->actingAs($this->admin)->post("/admin/appointments/$appointment->id/propose", ['slot_id' => $target->id, 'reason' => 'Alternative consultation time'])->assertSessionHasNoErrors();
        $request = $appointment->rescheduleRequests()->firstOrFail();
        $this->actingAs($this->vip())->post("/appointment-reschedules/$request->id/respond", ['accept' => true])->assertForbidden();
        $user->memberships()->update(['expires_at' => now()]);
        $this->actingAs($user)->post("/appointment-reschedules/$request->id/respond", ['accept' => true])->assertSessionHasErrors('appointment');
        $this->post("/appointment-reschedules/$request->id/respond", ['accept' => false])->assertSessionHasNoErrors();
        $this->assertSame('rejected', $request->fresh()->status);
        $this->assertSame($old->id, $appointment->fresh()->appointment_slot_id);
    }

    public function test_replaying_cancelled_booking_key_does_not_create_a_new_request(): void
    {
        $user = $this->vip();
        $slot = $this->slot();
        $appointment = $this->book($user, $slot);
        $this->post("/appointments/$appointment->id/cancel", ['reason' => 'Cancel this request'])->assertSessionHasNoErrors();
        $this->post('/vip/appointments', ['slot_id' => $slot->id, 'booking_key' => $appointment->booking_key, 'purpose' => 'Replayed old submission'])->assertSessionHasNoErrors();
        $this->assertDatabaseCount('appointments', 1);
        $this->assertSame('cancelled', $appointment->fresh()->status);
    }
}
