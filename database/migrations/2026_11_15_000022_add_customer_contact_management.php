<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->unsignedBigInteger('profile_version')->default(1);
        });
        Schema::create('customer_contact_changes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('admin_id')->constrained('users')->restrictOnDelete();
            $table->unsignedBigInteger('profile_version');
            $table->json('previous_values');
            $table->json('new_values');
            $table->text('reason');
            $table->timestamps();
            $table->unique(['customer_id', 'profile_version']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_contact_changes');
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn('profile_version'));
    }
};
