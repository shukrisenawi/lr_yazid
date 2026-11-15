<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('courier_name', 100)->nullable();
            $table->string('tracking_number', 150)->nullable();
        });
        Schema::create('order_fulfillment_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->restrictOnDelete();
            $table->foreignId('admin_id')->constrained('users')->restrictOnDelete();
            $table->string('previous_status', 20);
            $table->string('new_status', 20);
            $table->json('previous_tracking');
            $table->json('new_tracking');
            $table->text('reason');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_fulfillment_histories');
        Schema::table('orders', fn (Blueprint $table) => $table->dropColumn(['courier_name', 'tracking_number']));
    }
};
