<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Jobs\AnnounceVipProgram;
use App\Models\Membership;
use App\Models\MembershipPlan;
use App\Models\ProgramNoticeDelivery;
use App\Models\User;
use App\Models\VipProgram;
use App\Notifications\ProgramNotice;
use App\Services\ProgramNotificationService;
use App\Services\ProgramRegistrationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class VipProgramTest extends TestCase
{
    use RefreshDatabase;

    private function vip(): User
    {
        $user = User::factory()->create();
        $plan = MembershipPlan::create(['name' => 'VIP', 'price_sen' => 19900, 'duration_months' => 12]);
        Membership::create(['user_id' => $user->id, 'membership_plan_id' => $plan->id, 'status' => 'active', 'starts_at' => now()->subDay(), 'expires_at' => now()->addYear()]);

        return $user;
    }

    private function admin(): User
    {
        $user = User::factory()->create();
        $user->role = UserRole::Admin;
        $user->save();

        return $user;
    }

    private function data(): array
    {
        return [
            'title' => 'Member Workshop', 'slug' => 'member-workshop', 'short_description' => 'A VIP workshop.',
            'description' => '<h2>Welcome</h2><script>alert(1)</script><p>Meet other members.</p>',
            'starts_at' => now()->addDays(10)->toDateTimeString(), 'ends_at' => now()->addDays(10)->addHours(2)->toDateTimeString(),
            'registration_opens_at' => now()->subDay()->toDateTimeString(), 'registration_closes_at' => now()->addDays(9)->toDateTimeString(),
            'capacity' => 1, 'status' => 'published', 'registration_enabled' => true, 'location' => 'Kuala Lumpur',
        ];
    }

    private function program(): VipProgram
    {
        return VipProgram::create($this->data());
    }

    public function test_only_admin_can_manage_programs_and_images_are_optimized(): void
    {
        Storage::fake('public');
        $this->actingAs($this->vip())->post('/admin/programs', $this->data())->assertForbidden();
        $this->actingAs($this->admin())->post('/admin/programs', [
            ...$this->data(), 'images' => [UploadedFile::fake()->image('event.png', 200, 100)],
        ])->assertSessionHasNoErrors()->assertRedirect();
        $program = VipProgram::firstOrFail();
        $this->assertStringNotContainsString('<script', $program->description);
        $this->assertSame(0, $program->price_sen);
        $this->assertStringEndsWith('.webp', $program->images()->firstOrFail()->path);
        Storage::disk('public')->assertExists($program->images()->firstOrFail()->path);
        $this->assertDatabaseCount('jobs', 1);
    }

    public function test_normal_expired_and_admin_without_membership_cannot_access_or_register(): void
    {
        $program = $this->program();
        foreach ([User::factory()->create(), $this->admin(), $this->vip()] as $user) {
            $user->memberships()->update(['expires_at' => now()]);
            $this->actingAs($user)->get('/vip/programs')->assertForbidden();
            $this->post("/vip/programs/$program->id/register")->assertForbidden();
            $this->get('/my-programs')->assertOk();
        }
        $this->assertDatabaseCount('vip_program_registrations', 0);
    }

    public function test_registration_is_idempotent_and_capacity_is_enforced(): void
    {
        Notification::fake();
        $program = $this->program();
        $user = $this->vip();
        $this->actingAs($user)->post("/vip/programs/$program->id/register")->assertSessionHasNoErrors()->assertRedirect('/my-programs');
        $this->post("/vip/programs/$program->id/register")->assertSessionHasNoErrors();
        $this->actingAs($this->vip())->post("/vip/programs/$program->id/register")->assertSessionHasErrors('program');
        $this->assertDatabaseCount('vip_program_registrations', 1);
        Notification::assertSentToTimes($user, ProgramNotice::class, 1);
        $this->assertDatabaseCount('program_notice_deliveries', 1);
    }

    public function test_membership_must_cover_entire_program_with_exclusive_expiry(): void
    {
        $program = $this->program();
        $user = $this->vip();
        $user->memberships()->update(['expires_at' => $program->ends_at]);
        $this->actingAs($user)->post("/vip/programs/$program->id/register")->assertSessionHasErrors('program');
        $this->assertDatabaseCount('vip_program_registrations', 0);
        $user->memberships()->update(['expires_at' => $program->ends_at->addSecond()]);
        $this->post("/vip/programs/$program->id/register")->assertSessionHasNoErrors();
    }

    public function test_window_status_enabled_and_paid_program_guards_are_server_enforced(): void
    {
        $this->freezeTime();
        $user = $this->vip();
        $program = $this->program();
        foreach ([
            ['status' => 'draft'], ['registration_enabled' => false],
            ['registration_opens_at' => now()->addSecond()],
            ['registration_closes_at' => now()], ['price_sen' => 100],
            ['starts_at' => now()],
        ] as $change) {
            $program->update([...$this->data(), 'price_sen' => 0, ...$change]);
            $this->actingAs($user)->post("/vip/programs/$program->id/register")->assertSessionHasErrors('program');
        }
        $this->assertDatabaseCount('vip_program_registrations', 0);
    }

    public function test_customer_cancellation_releases_capacity_and_reregistration_is_deduplicated(): void
    {
        Notification::fake();
        $user = $this->vip();
        $program = $this->program();
        $registration = app(ProgramRegistrationService::class)->register($user, $program);
        $this->actingAs($this->vip())->post("/my-programs/$registration->id/cancel")->assertForbidden();
        $this->actingAs($user)->post("/my-programs/$registration->id/cancel")->assertSessionHasNoErrors();
        $this->post("/my-programs/$registration->id/cancel")->assertSessionHasNoErrors();
        $this->post("/vip/programs/$program->id/register")->assertSessionHasNoErrors();
        $this->assertSame(2, $registration->fresh()->version);
        $this->assertDatabaseCount('vip_program_registrations', 1);
        Notification::assertSentToTimes($user, ProgramNotice::class, 3);
    }

    public function test_program_cancellation_notifies_once_and_preserves_history(): void
    {
        Notification::fake();
        $user = $this->vip();
        $program = $this->program();
        app(ProgramRegistrationService::class)->register($user, $program);
        $admin = $this->admin();
        $data = [...$this->data(), 'status' => 'cancelled'];
        $this->actingAs($admin)->put("/admin/programs/$program->id", $data)->assertSessionHasNoErrors();
        $this->put("/admin/programs/$program->id", $data)->assertSessionHasNoErrors();
        $this->assertDatabaseHas('vip_program_registrations', ['user_id' => $user->id, 'status' => 'cancelled', 'cancellation_reason' => 'program_cancelled']);
        Notification::assertSentToTimes($user, ProgramNotice::class, 2);
        $this->put("/admin/programs/$program->id", $this->data())->assertSessionHasErrors('status');
        $this->actingAs($user)->get('/my-programs')->assertInertia(fn (Assert $page) => $page->has('registrations.data', 1));
    }

    public function test_schedule_and_capacity_changes_cannot_disrupt_registrations(): void
    {
        $program = $this->program();
        $program->update(['capacity' => 2]);
        app(ProgramRegistrationService::class)->register($this->vip(), $program);
        app(ProgramRegistrationService::class)->register($this->vip(), $program);
        $this->actingAs($this->admin())->put("/admin/programs/$program->id", $this->data())->assertSessionHasErrors('capacity');
        $this->put("/admin/programs/$program->id", [...$this->data(), 'capacity' => 2, 'location' => 'New venue'])->assertSessionHasErrors('starts_at');
        $this->assertSame('Kuala Lumpur', $program->fresh()->location);
    }

    public function test_publication_job_deduplicates_on_retry_and_skips_ineligible_members(): void
    {
        Notification::fake();
        $program = $this->program();
        $vip = $this->vip();
        $expired = $this->vip();
        $expired->memberships()->update(['expires_at' => now()]);
        $job = new AnnounceVipProgram($program->id);
        $job->handle(app(ProgramNotificationService::class));
        $job->handle(app(ProgramNotificationService::class));
        Notification::assertSentToTimes($vip, ProgramNotice::class, 1);
        Notification::assertNotSentTo($expired, ProgramNotice::class);
    }

    public function test_attendance_requires_started_program_registered_participant_and_admin(): void
    {
        $this->freezeTime();
        $user = $this->vip();
        $program = $this->program();
        $registration = app(ProgramRegistrationService::class)->register($user, $program);
        $url = "/admin/programs/$program->id/participants/$registration->id/attendance";
        $this->actingAs($user)->put($url, ['attendance' => 'attended'])->assertForbidden();
        $admin = $this->admin();
        $this->actingAs($admin)->put($url, ['attendance' => 'attended'])->assertSessionHasErrors('attendance');
        $this->travel(10)->days();
        $this->put($url, ['attendance' => 'attended'])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('vip_program_registrations', ['id' => $registration->id, 'attendance' => 'attended', 'attendance_by' => $admin->id]);
    }

    public function test_participant_export_is_private_and_escapes_spreadsheet_formulas(): void
    {
        $program = $this->program();
        $user = $this->vip();
        $user->update(['name' => '=HYPERLINK("https://invalid.test")']);
        app(ProgramRegistrationService::class)->register($user, $program);
        $this->actingAs($user)->get("/admin/programs/$program->id/export")->assertForbidden();
        $csv = $this->actingAs($this->admin())->get("/admin/programs/$program->id/export")->assertOk()->streamedContent();
        $this->assertStringContainsString("'=HYPERLINK", $csv);
        $this->assertStringContainsString($user->email, $csv);
    }

    public function test_draft_program_details_are_private_and_history_is_owner_scoped_after_expiry(): void
    {
        $program = $this->program();
        $user = $this->vip();
        app(ProgramRegistrationService::class)->register($user, $program);
        $this->actingAs($this->vip())->get('/my-programs')->assertInertia(fn (Assert $page) => $page->has('registrations.data', 0));
        $program->update(['status' => 'draft']);
        $this->get("/vip/programs/$program->slug")->assertNotFound();
        $user->memberships()->update(['expires_at' => now()]);
        $this->actingAs($user)->get('/my-programs')->assertInertia(fn (Assert $page) => $page->has('registrations.data', 1));
    }

    public function test_database_delivery_is_recorded_and_notifications_are_owner_protected(): void
    {
        $program = $this->program();
        $user = $this->vip();
        $delivery = ProgramNoticeDelivery::create(['vip_program_id' => $program->id, 'user_id' => $user->id, 'event_key' => 'test', 'queued_at' => now()]);
        $user->notifyNow(new ProgramNotice('published', $program->title, '20 Nov 2026', $delivery->id), ['database']);
        $this->assertNotNull($delivery->fresh()->database_sent_at);
        $notification = $user->notifications()->firstOrFail();
        $this->actingAs($this->vip())->post("/notifications/$notification->id/read")->assertNotFound();
    }

    public function test_invalid_schedule_and_unsafe_map_links_are_rejected(): void
    {
        $this->actingAs($this->admin())->post('/admin/programs', [
            ...$this->data(), 'ends_at' => now()->toDateTimeString(), 'maps_url' => 'javascript:alert(1)',
        ])->assertSessionHasErrors(['ends_at', 'maps_url']);
        $this->assertDatabaseCount('vip_programs', 0);
    }

    public function test_completed_program_can_be_archived_without_cancelling_attendance_history(): void
    {
        $this->freezeTime();
        $program = $this->program();
        $registration = app(ProgramRegistrationService::class)->register($this->vip(), $program);
        $data = $this->data();
        $this->travel(11)->days();
        $this->actingAs($this->admin())->put("/admin/programs/$program->id", [...$data, 'status' => 'completed'])->assertSessionHasNoErrors();
        $this->put("/admin/programs/$program->id", [...$data, 'status' => 'archived'])->assertSessionHasNoErrors();
        $this->assertSame('registered', $registration->fresh()->status);
        $this->assertSame('archived', $program->fresh()->status);
    }
}
