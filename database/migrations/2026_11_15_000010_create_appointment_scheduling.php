<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('appointment_services', function (Blueprint $table) {
            $table->id();
            $table->string('name', 200);
            $table->text('description')->nullable();
            $table->unsignedSmallInteger('duration_minutes')->default(30);
            $table->unsignedSmallInteger('default_capacity')->default(1);
            $table->string('location', 500);
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('minimum_notice_hours')->default(24);
            $table->unsignedSmallInteger('maximum_advance_days')->default(90);
            $table->unsignedSmallInteger('cancellation_notice_hours')->default(24);
            $table->unsignedSmallInteger('maximum_active_bookings')->default(3);
            $table->timestamps();
        });
        Schema::create('appointment_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('appointment_service_id')->constrained()->restrictOnDelete();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->json('configuration');
            $table->unsignedInteger('created_count');
            $table->unsignedInteger('skipped_count');
            $table->timestamps();
        });
        Schema::create('appointment_blocked_dates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('appointment_service_id')->constrained()->restrictOnDelete();
            $table->date('date');
            $table->string('reason', 500);
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->unique(['appointment_service_id', 'date'], 'unique_service_blocked_date');
        });
        Schema::create('appointment_slots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('appointment_service_id')->constrained()->restrictOnDelete();
            $table->foreignId('appointment_schedule_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->dateTime('starts_at')->index();
            $table->dateTime('ends_at');
            $table->unsignedSmallInteger('capacity');
            $table->string('location', 500);
            $table->text('notes')->nullable();
            $table->string('status', 20)->default('available');
            $table->timestamps();
            $table->unique(['appointment_service_id', 'starts_at', 'ends_at'], 'unique_service_slot');
            $table->index(['appointment_service_id', 'starts_at', 'ends_at'], 'service_slot_overlap');
        });
        Schema::create('appointments', function (Blueprint $table) {
            $table->id();
            $table->string('reference', 40)->unique();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->foreignId('appointment_service_id')->constrained()->restrictOnDelete();
            $table->foreignId('appointment_slot_id')->constrained()->restrictOnDelete();
            $table->string('purpose', 1000);
            $table->text('notes')->nullable();
            $table->string('status', 20)->default('pending')->index();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->dateTime('reviewed_at')->nullable();
            $table->text('admin_remarks')->nullable();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->dateTime('cancelled_at')->nullable();
            $table->text('cancellation_reason')->nullable();
            $table->timestamps();
            $table->index(['appointment_slot_id', 'status']);
            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('appointments');
        Schema::dropIfExists('appointment_slots');
        Schema::dropIfExists('appointment_blocked_dates');
        Schema::dropIfExists('appointment_schedules');
        Schema::dropIfExists('appointment_services');
    }
};
