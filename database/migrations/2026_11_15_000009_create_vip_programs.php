<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vip_programs', function (Blueprint $table) {
            $table->id();
            $table->string('title', 200);
            $table->string('slug', 200)->unique();
            $table->text('short_description')->nullable();
            $table->longText('description')->nullable();
            $table->dateTime('starts_at')->index();
            $table->dateTime('ends_at');
            $table->string('location', 500);
            $table->string('maps_url', 2000)->nullable();
            $table->dateTime('registration_opens_at');
            $table->dateTime('registration_closes_at');
            $table->unsignedInteger('capacity');
            $table->boolean('registration_enabled')->default(true);
            $table->string('status', 20)->default('draft')->index();
            $table->unsignedBigInteger('price_sen')->default(0);
            $table->dateTime('publication_queued_at')->nullable();
            $table->timestamps();
        });
        Schema::create('vip_program_images', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vip_program_id')->constrained()->restrictOnDelete();
            $table->string('path');
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamps();
        });
        Schema::create('vip_program_registrations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vip_program_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->string('reference', 40)->unique();
            $table->string('status', 20)->default('registered');
            $table->string('attendance', 20)->default('unmarked');
            $table->dateTime('registered_at');
            $table->unsignedInteger('version')->default(1);
            $table->dateTime('cancelled_at')->nullable();
            $table->string('cancellation_reason')->nullable();
            $table->foreignId('attendance_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->dateTime('attendance_at')->nullable();
            $table->timestamps();
            $table->unique(['vip_program_id', 'user_id'], 'unique_program_participant');
            $table->index(['vip_program_id', 'status']);
        });
        Schema::create('program_notice_deliveries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vip_program_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->string('event_key', 100);
            $table->dateTime('queued_at');
            $table->dateTime('database_sent_at')->nullable();
            $table->dateTime('mail_sent_at')->nullable();
            $table->timestamps();
            $table->unique(['vip_program_id', 'user_id', 'event_key'], 'unique_program_notice');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('program_notice_deliveries');
        Schema::dropIfExists('vip_program_registrations');
        Schema::dropIfExists('vip_program_images');
        Schema::dropIfExists('vip_programs');
    }
};
