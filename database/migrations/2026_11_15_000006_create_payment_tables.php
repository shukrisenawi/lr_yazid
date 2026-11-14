<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_gateway_settings', function (Blueprint $table) {
            $table->unsignedTinyInteger('id')->primary();
            $table->string('environment', 20)->default('sandbox');
            $table->boolean('enabled')->default(false);
            $table->text('sandbox_secret_key')->nullable();
            $table->text('production_secret_key')->nullable();
            $table->string('sandbox_category_code')->nullable();
            $table->string('production_category_code')->nullable();
            $table->timestamps();
        });
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->restrictOnDelete();
            $table->string('reference', 40)->unique();
            $table->string('environment', 20);
            $table->text('secret_key_snapshot');
            $table->unsignedBigInteger('amount_sen');
            $table->string('status', 30)->default('creating')->index();
            $table->string('bill_code', 64)->nullable();
            $table->string('transaction_reference', 100)->nullable();
            $table->boolean('requires_review')->default(false);
            $table->string('review_reason')->nullable();
            $table->dateTime('paid_at')->nullable();
            $table->dateTime('checked_at')->nullable();
            $table->timestamps();
            $table->unique(['environment', 'bill_code']);
            $table->unique(['environment', 'transaction_reference']);
        });
        Schema::create('payment_callbacks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payment_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('event', 40);
            $table->string('outcome', 100);
            $table->json('payload')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_callbacks');
        Schema::dropIfExists('payments');
        Schema::dropIfExists('payment_gateway_settings');
    }
};
