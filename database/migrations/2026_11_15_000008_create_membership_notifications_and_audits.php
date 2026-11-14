<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('type');
            $table->morphs('notifiable');
            $table->text('data');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
        });
        Schema::create('membership_reminders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->foreignId('membership_id')->constrained()->restrictOnDelete();
            $table->dateTime('expires_at');
            $table->string('kind', 20);
            $table->dateTime('queued_at');
            $table->dateTime('mail_sent_at')->nullable();
            $table->dateTime('database_sent_at')->nullable();
            $table->timestamps();
            $table->unique(['membership_id', 'expires_at', 'kind'], 'unique_membership_reminder');
        });
        Schema::create('membership_adjustments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('membership_id')->constrained()->restrictOnDelete();
            $table->foreignId('admin_id')->constrained('users')->restrictOnDelete();
            $table->dateTime('old_expires_at');
            $table->dateTime('new_expires_at');
            $table->string('old_status', 20);
            $table->string('new_status', 20);
            $table->text('reason');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('membership_adjustments');
        Schema::dropIfExists('membership_reminders');
        Schema::dropIfExists('notifications');
    }
};
