<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('manual_refunds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->restrictOnDelete();
            $table->foreignId('payment_id')->constrained()->restrictOnDelete();
            $table->foreignId('recorded_by')->constrained('users')->restrictOnDelete();
            $table->uuid('idempotency_key')->unique();
            $table->string('reference', 40)->unique();
            $table->string('environment', 20);
            $table->string('external_reference', 100);
            $table->string('method', 30);
            $table->unsignedInteger('amount_sen');
            $table->char('currency', 3)->default('MYR');
            $table->dateTime('refunded_at')->index();
            $table->text('reason');
            $table->timestamps();
            $table->unique(['environment', 'external_reference']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('manual_refunds');
    }
};
