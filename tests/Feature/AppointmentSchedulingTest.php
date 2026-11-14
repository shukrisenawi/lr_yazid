<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Appointment;
use App\Models\AppointmentService;
use App\Models\AppointmentSlot;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AppointmentSchedulingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->setDate(2026, 11, 15)->setTime(8, 0));
    }

    private function admin(): User
    {
        $user = User::factory()->create();
        $user->role = UserRole::Admin;
        $user->save();
        $this->actingAs($user);

        return $user;
    }

    private function service(): AppointmentService
    {
        return AppointmentService::create(['name' => 'Consultation', 'location' => 'Kuala Lumpur']);
    }

    private function configuration(): array
    {
        return [
            'from' => '2026-11-16', 'to' => '2026-11-20', 'weekdays' => [1, 2, 3, 4, 5],
            'day_start' => '09:00', 'day_end' => '12:00', 'break_start' => '10:00', 'break_end' => '10:30',
            'duration_minutes' => 30, 'capacity' => 1, 'location' => 'QA venue', 'preview' => false,
        ];
    }

    private function slotData(): array
    {
        return ['starts_at' => '2026-11-16 09:00:00', 'ends_at' => '2026-11-16 09:30:00', 'capacity' => 1, 'location' => 'Kuala Lumpur', 'status' => 'available'];
    }

    private function manual(AppointmentService $service): AppointmentSlot
    {
        $this->post("/admin/appointment-services/$service->id/slots", $this->slotData())->assertSessionHasNoErrors();

        return $service->slots()->firstOrFail();
    }

    public function test_customer_cannot_access_or_change_scheduling(): void
    {
        $service = $this->service();
        $this->actingAs(User::factory()->create())->get('/admin/appointment-services')->assertForbidden();
        $this->post("/admin/appointment-services/$service->id/slots", $this->slotData())->assertForbidden();
        $this->post("/admin/appointment-services/$service->id/generate-slots", $this->configuration())->assertForbidden();
        $this->post("/admin/appointment-services/$service->id/blocked-dates", ['date' => '2026-11-17', 'reason' => 'Closed'])->assertForbidden();
    }

    public function test_service_configuration_is_validated_and_does_not_rewrite_existing_slots(): void
    {
        $this->admin();
        $data = ['name' => 'Consultation', 'description' => 'VIP consultation', 'duration_minutes' => 30,
            'default_capacity' => 1, 'location' => 'Kuala Lumpur', 'is_active' => true,
            'minimum_notice_hours' => 24, 'maximum_advance_days' => 90, 'cancellation_notice_hours' => 24, 'maximum_active_bookings' => 3];
        $this->post('/admin/appointment-services', $data)->assertSessionHasNoErrors();
        $service = AppointmentService::firstOrFail();
        $slot = $this->manual($service);
        $this->put("/admin/appointment-services/$service->id", [...$data, 'default_capacity' => 2, 'location' => 'New location'])->assertSessionHasNoErrors();
        $this->assertSame(1, $slot->fresh()->capacity);
        $this->assertSame('Kuala Lumpur', $slot->fresh()->location);
        $this->put("/admin/appointment-services/$service->id", [...$data, 'minimum_notice_hours' => 24, 'maximum_advance_days' => 1])->assertSessionHasErrors('minimum_notice_hours');
    }

    public function test_preview_is_read_only_and_generation_is_idempotent(): void
    {
        $this->admin();
        $service = $this->service();
        $url = "/admin/appointment-services/$service->id/generate-slots";
        $this->post($url, [...$this->configuration(), 'preview' => true])->assertSessionHasNoErrors()->assertSessionHas('slotGeneration.candidates', 25);
        $this->assertDatabaseCount('appointment_slots', 0);
        $this->assertDatabaseCount('appointment_schedules', 0);
        $this->post($url, $this->configuration())->assertSessionHasNoErrors()->assertSessionHas('slotGeneration.created', 25);
        $this->post($url, $this->configuration())->assertSessionHasNoErrors()->assertSessionHas('slotGeneration.created', 0)->assertSessionHas('slotGeneration.overlaps', 25);
        $this->assertDatabaseCount('appointment_slots', 25);
        $this->assertDatabaseCount('appointment_schedules', 2);
    }

    public function test_generator_skips_breaks_blocked_dates_and_nonworking_days(): void
    {
        $this->admin();
        $service = $this->service();
        $this->post("/admin/appointment-services/$service->id/blocked-dates", ['date' => '2026-11-17', 'reason' => 'Office closed'])->assertSessionHasNoErrors();
        $this->post("/admin/appointment-services/$service->id/generate-slots", [...$this->configuration(), 'to' => '2026-11-22'])
            ->assertSessionHasNoErrors()->assertSessionHas('slotGeneration.created', 20)->assertSessionHas('slotGeneration.blocked_days', 1)->assertSessionHas('slotGeneration.breaks', 4);
        $this->assertSame(0, $service->slots()->whereDate('starts_at', '2026-11-17')->count());
        $this->assertSame(0, $service->slots()->whereDate('starts_at', '2026-11-21')->count());
        $this->assertSame(0, $service->slots()->whereTime('starts_at', '10:00:00')->count());
    }

    public function test_overlapping_slots_are_skipped_but_adjacent_slots_are_allowed(): void
    {
        $this->admin();
        $service = $this->service();
        $this->post("/admin/appointment-services/$service->id/slots", [...$this->slotData(), 'starts_at' => '2026-11-16 09:15:00', 'ends_at' => '2026-11-16 09:45:00'])->assertSessionHasNoErrors();
        $this->post("/admin/appointment-services/$service->id/generate-slots", $this->configuration())->assertSessionHasNoErrors()->assertSessionHas('slotGeneration.created', 23)->assertSessionHas('slotGeneration.overlaps', 2);
        $this->post("/admin/appointment-services/$service->id/slots", $this->slotData())->assertSessionHasErrors('starts_at');
        $this->post("/admin/appointment-services/$service->id/slots", [...$this->slotData(), 'starts_at' => '2026-11-16 09:45:00', 'ends_at' => '2026-11-16 10:00:00'])->assertSessionHasNoErrors();
    }

    public function test_approved_appointments_prevent_slot_changes_deletion_and_date_blocking(): void
    {
        $admin = $this->admin();
        $service = $this->service();
        $slot = $this->manual($service);
        Appointment::create(['reference' => 'QA-APPROVED', 'user_id' => $admin->id, 'appointment_service_id' => $service->id,
            'appointment_slot_id' => $slot->id, 'purpose' => 'Test', 'status' => 'approved']);
        $this->put("/admin/appointment-services/$service->id/slots/$slot->id", [...$this->slotData(), 'status' => 'disabled'])->assertSessionHasErrors('slot');
        $this->delete("/admin/appointment-services/$service->id/slots/$slot->id")->assertSessionHasErrors('slot');
        $this->post("/admin/appointment-services/$service->id/blocked-dates", ['date' => '2026-11-16', 'reason' => 'Closed'])->assertSessionHasErrors('date');
        $this->assertSame('available', $slot->fresh()->status);
        $this->assertDatabaseCount('appointment_blocked_dates', 0);
        $this->post("/admin/appointment-services/$service->id/generate-slots", $this->configuration())
            ->assertSessionHasNoErrors()->assertSessionHas('slotGeneration.created', 24)->assertSessionHas('slotGeneration.overlaps', 1);
        $this->assertSame('2026-11-16 09:00:00', $slot->fresh()->starts_at->toDateTimeString());
    }

    public function test_blocking_disables_slots_and_unblocking_does_not_resurrect_them(): void
    {
        $this->admin();
        $service = $this->service();
        $slot = $this->manual($service);
        $this->post("/admin/appointment-services/$service->id/blocked-dates", ['date' => '2026-11-16', 'reason' => 'Closed'])->assertSessionHasNoErrors();
        $this->assertSame('disabled', $slot->fresh()->status);
        $this->put("/admin/appointment-services/$service->id/slots/$slot->id", $this->slotData())->assertSessionHasErrors('starts_at');
        $blocked = $service->blockedDates()->firstOrFail();
        $this->delete("/admin/appointment-services/$service->id/blocked-dates/$blocked->id")->assertSessionHasNoErrors();
        $this->assertSame('disabled', $slot->fresh()->status);
        $this->put("/admin/appointment-services/$service->id/slots/$slot->id", $this->slotData())->assertSessionHasNoErrors();
        $this->assertSame('available', $slot->fresh()->status);
    }

    public function test_slot_and_blocked_date_routes_are_scoped_to_their_service(): void
    {
        $this->admin();
        $service = $this->service();
        $other = $this->service();
        $slot = $this->manual($service);
        $this->put("/admin/appointment-services/$other->id/slots/$slot->id", $this->slotData())->assertNotFound();
        $this->delete("/admin/appointment-services/$other->id/slots/$slot->id")->assertNotFound();
        $this->assertDatabaseCount('appointment_slots', 1);
    }

    public function test_inactive_service_and_invalid_generator_configuration_are_rejected(): void
    {
        $this->admin();
        $service = $this->service();
        $url = "/admin/appointment-services/$service->id/generate-slots";
        $this->post($url, [...$this->configuration(), 'break_end' => '09:00'])->assertSessionHasErrors('break_end');
        $this->post($url, [...$this->configuration(), 'to' => '2027-11-16'])->assertSessionHasErrors('to');
        $service->update(['is_active' => false]);
        $this->post($url, $this->configuration())->assertSessionHasErrors('generator');
        $this->post("/admin/appointment-services/$service->id/slots", $this->slotData())->assertSessionHasErrors('slot');
        $this->assertDatabaseCount('appointment_slots', 0);
    }

    public function test_slots_with_cancelled_history_cannot_be_deleted_but_unused_slots_can(): void
    {
        $admin = $this->admin();
        $service = $this->service();
        $slot = $this->manual($service);
        $this->delete("/admin/appointment-services/$service->id/slots/$slot->id")->assertSessionHasNoErrors();
        $slot = $this->manual($service);
        Appointment::create(['reference' => 'QA-CANCELLED', 'user_id' => $admin->id, 'appointment_service_id' => $service->id,
            'appointment_slot_id' => $slot->id, 'purpose' => 'Test', 'status' => 'cancelled']);
        $this->delete("/admin/appointment-services/$service->id/slots/$slot->id")->assertSessionHasErrors('slot');
        $this->assertDatabaseCount('appointment_slots', 1);
    }

    public function test_large_generation_request_rolls_back_without_partial_slots(): void
    {
        $this->admin();
        $service = $this->service();
        $this->post("/admin/appointment-services/$service->id/generate-slots", [
            ...$this->configuration(), 'to' => '2026-12-15', 'weekdays' => [1, 2, 3, 4, 5, 6, 7],
            'day_start' => '00:00', 'day_end' => '23:59', 'duration_minutes' => 5, 'break_start' => null, 'break_end' => null,
        ])->assertSessionHasErrors('to');
        $this->assertDatabaseCount('appointment_slots', 0);
        $this->assertDatabaseCount('appointment_schedules', 0);
    }

    public function test_manual_slot_times_are_normalized_to_malaysia_and_excessive_duration_is_rejected(): void
    {
        $this->admin();
        $service = $this->service();
        $this->post("/admin/appointment-services/$service->id/slots", [
            ...$this->slotData(), 'starts_at' => '2026-11-16T01:00:00Z', 'ends_at' => '2026-11-16T01:30:00Z',
        ])->assertSessionHasNoErrors();
        $this->assertSame('2026-11-16 09:00:00', $service->slots()->firstOrFail()->starts_at->toDateTimeString());
        $this->post("/admin/appointment-services/$service->id/slots", [
            ...$this->slotData(), 'starts_at' => '2026-11-17 00:00:00', 'ends_at' => '2026-11-18 00:00:00',
        ])->assertSessionHasErrors('ends_at');
        $this->assertDatabaseCount('appointment_slots', 1);
    }
}
