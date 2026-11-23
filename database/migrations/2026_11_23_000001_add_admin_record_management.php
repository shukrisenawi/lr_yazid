<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['homepage_banners', 'content_pages', 'vip_programs', 'appointment_services', 'appointments', 'membership_plans', 'memberships'] as $name) {
            Schema::table($name, fn (Blueprint $table) => $table->softDeletes());
        }
        foreach (['orders', 'payments'] as $name) {
            Schema::table($name, fn (Blueprint $table) => $table->timestamp('archived_at')->nullable()->index());
        }
        Schema::create('admin_record_actions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('admin_id')->constrained('users')->restrictOnDelete();
            $table->string('module');
            $table->unsignedBigInteger('record_id');
            $table->string('action');
            $table->text('reason');
            $table->timestamp('created_at');
            $table->index(['module', 'record_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_record_actions');
        foreach (['orders', 'payments'] as $name) {
            Schema::table($name, fn (Blueprint $table) => $table->dropColumn('archived_at'));
        }
        foreach (['homepage_banners', 'content_pages', 'vip_programs', 'appointment_services', 'appointments', 'membership_plans', 'memberships'] as $name) {
            Schema::table($name, fn (Blueprint $table) => $table->dropSoftDeletes());
        }
    }
};
