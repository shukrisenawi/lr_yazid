<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('commerce_notice_deliveries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('payment_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('kind', 30)->index();
            $table->string('event_key', 100);
            $table->uuid('notification_id')->unique();
            $table->json('snapshot');
            $table->dateTime('queued_at');
            foreach (['database', 'mail'] as $channel) {
                $table->dateTime($channel.'_sent_at')->nullable();
                $table->dateTime($channel.'_suppressed_at')->nullable();
                $table->string($channel.'_suppression_reason', 60)->nullable();
            }
            $table->timestamps();
            $table->unique(['user_id', 'event_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('commerce_notice_deliveries');
    }
};
