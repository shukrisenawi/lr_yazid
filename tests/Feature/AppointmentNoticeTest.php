<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\AppointmentNoticeDelivery;
use App\Models\AppointmentService;
use App\Models\User;
use App\Notifications\AppointmentNotice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AppointmentNoticeTest extends TestCase
{
    use RefreshDatabase;

    public function test_database_delivery_is_tracked_and_notification_reading_is_owner_scoped(): void
    {
        $user = User::factory()->create();
        $service = AppointmentService::create(['name' => 'Consultation', 'location' => 'KL']);
        $slot = $service->slots()->create(['created_by' => $user->id, 'starts_at' => now()->addDays(3), 'ends_at' => now()->addDays(3)->addHour(), 'capacity' => 1, 'location' => 'KL']);
        $appointment = Appointment::create(['reference' => 'APT-NOTICE-TEST', 'user_id' => $user->id, 'appointment_service_id' => $service->id,
            'appointment_slot_id' => $slot->id, 'scheduled_starts_at' => $slot->starts_at, 'scheduled_ends_at' => $slot->ends_at, 'purpose' => 'QA notification', 'status' => 'approved']);
        $delivery = AppointmentNoticeDelivery::create(['appointment_id' => $appointment->id, 'user_id' => $user->id, 'event_key' => 'approved', 'queued_at' => now()]);
        $notice = new AppointmentNotice('approved', $appointment->reference, $service->name, $slot->starts_at->format('d M Y H:i'),
            'approved', $delivery->id, $appointment->id, '/appointments/'.$appointment->id, $slot->starts_at->toIso8601String());
        $user->notifyNow($notice, ['database']);
        $this->assertNotNull($delivery->fresh()->database_sent_at);
        $notification = $user->notifications()->firstOrFail();
        $this->actingAs(User::factory()->create())->post("/notifications/$notification->id/read")->assertNotFound();
        $this->actingAs($user)->post("/notifications/$notification->id/read")->assertRedirect();
        $this->assertNotNull($notification->fresh()->read_at);
        $this->delete('/settings/profile', ['password' => 'password'])->assertSessionHasErrors('password');
        $this->assertNotNull($user->fresh());
    }
}
