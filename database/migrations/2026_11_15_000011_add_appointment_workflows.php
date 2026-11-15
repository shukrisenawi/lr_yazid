<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            $table->uuid('booking_key')->nullable();
            $table->foreignId('active_slot_id')->nullable()->constrained('appointment_slots')->restrictOnDelete();
            $table->dateTime('scheduled_starts_at')->nullable()->index();
            $table->dateTime('scheduled_ends_at')->nullable();
            $table->string('service_name', 200)->nullable();
            $table->string('location', 500)->nullable();
            $table->unsignedInteger('version')->default(1);
            $table->unique(['user_id', 'booking_key']);
            $table->unique(['user_id', 'active_slot_id']);
        });
        DB::table('appointments')->orderBy('id')->each(function ($appointment) {
            $slot = DB::table('appointment_slots')->where('id', $appointment->appointment_slot_id)->first();
            $service = DB::table('appointment_services')->where('id', $appointment->appointment_service_id)->first();
            if (! $slot || ! $service) {
                throw new RuntimeException('An existing appointment has a missing slot or service.');
            }
            DB::table('appointments')->where('id', $appointment->id)->update([
                'active_slot_id' => in_array($appointment->status, ['pending', 'approved'], true) ? $slot->id : null,
                'scheduled_starts_at' => $slot->starts_at, 'scheduled_ends_at' => $slot->ends_at,
                'service_name' => $service->name, 'location' => $slot->location,
            ]);
        });
        Schema::create('appointment_reschedule_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('appointment_id')->constrained()->restrictOnDelete();
            $table->foreignId('original_slot_id')->constrained('appointment_slots')->restrictOnDelete();
            $table->foreignId('target_slot_id')->constrained('appointment_slots')->restrictOnDelete();
            $table->foreignId('requested_by')->constrained('users')->restrictOnDelete();
            $table->string('origin', 20)->default('customer');
            $table->string('status', 20)->default('pending');
            $table->text('reason');
            $table->dateTime('target_starts_at');
            $table->dateTime('target_ends_at');
            $table->string('target_location', 500);
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->dateTime('reviewed_at')->nullable();
            $table->text('review_remarks')->nullable();
            $table->timestamps();
        });
        Schema::create('appointment_status_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('appointment_id')->constrained()->restrictOnDelete();
            $table->string('event', 40);
            $table->string('previous_status', 20)->nullable();
            $table->string('new_status', 20);
            $table->foreignId('changed_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('previous_slot_id')->nullable()->constrained('appointment_slots')->restrictOnDelete();
            $table->foreignId('new_slot_id')->constrained('appointment_slots')->restrictOnDelete();
            $table->text('remarks')->nullable();
            $table->timestamps();
        });
        Schema::create('appointment_notice_deliveries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('appointment_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->string('event_key', 100);
            $table->dateTime('queued_at');
            $table->dateTime('database_sent_at')->nullable();
            $table->dateTime('mail_sent_at')->nullable();
            $table->timestamps();
            $table->unique(['appointment_id', 'user_id', 'event_key'], 'unique_appointment_notice');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('appointment_notice_deliveries');
        Schema::dropIfExists('appointment_status_histories');
        Schema::dropIfExists('appointment_reschedule_requests');
        Schema::table('appointments', function (Blueprint $table) {
            $table->dropUnique(['user_id', 'booking_key']);
            $table->dropUnique(['user_id', 'active_slot_id']);
            $table->dropConstrainedForeignId('active_slot_id');
            $table->dropColumn(['booking_key', 'scheduled_starts_at', 'scheduled_ends_at', 'service_name', 'location', 'version']);
        });
    }
};
