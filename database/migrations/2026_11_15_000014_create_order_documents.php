<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoice_settings', function (Blueprint $table) {
            $table->id();
            $table->boolean('enabled')->default(false);
            $table->string('seller_name', 200)->nullable();
            $table->text('seller_address')->nullable();
            $table->string('seller_email')->nullable();
            $table->string('registration_number', 100)->nullable();
            $table->text('footer_note')->nullable();
            $table->timestamps();
        });
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->unique()->constrained()->restrictOnDelete();
            $table->foreignId('issued_by')->constrained('users')->restrictOnDelete();
            $table->string('reference', 60)->unique();
            $table->dateTime('issued_at');
            $table->json('snapshot');
            $table->timestamps();
        });
        Schema::table('order_fulfillment_histories', function (Blueprint $table) {
            $table->dateTime('database_sent_at')->nullable();
            $table->dateTime('mail_sent_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('order_fulfillment_histories', fn (Blueprint $table) => $table->dropColumn(['database_sent_at', 'mail_sent_at']));
        Schema::dropIfExists('invoices');
        Schema::dropIfExists('invoice_settings');
    }
};
