<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('membership_plans', function (Blueprint $table) {
            $table->foreignId('product_id')->nullable()->unique()->constrained()->restrictOnDelete();
        });
        Schema::table('memberships', function (Blueprint $table) {
            $table->unique(['user_id', 'membership_plan_id']);
        });
        Schema::table('order_items', function (Blueprint $table) {
            $table->foreignId('membership_plan_id')->nullable()->constrained()->restrictOnDelete();
            $table->unsignedSmallInteger('membership_months')->nullable();
        });
        Schema::create('membership_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->foreignId('membership_id')->constrained()->restrictOnDelete();
            $table->foreignId('payment_id')->constrained()->restrictOnDelete();
            $table->foreignId('order_item_id')->unique()->constrained()->restrictOnDelete();
            $table->string('type', 20);
            $table->unsignedBigInteger('amount_sen');
            $table->dateTime('previous_expires_at')->nullable();
            $table->dateTime('starts_at');
            $table->dateTime('expires_at');
            $table->dateTime('paid_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('membership_transactions');
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('membership_plan_id');
            $table->dropColumn('membership_months');
        });
        Schema::table('memberships', fn (Blueprint $table) => $table->dropUnique(['user_id', 'membership_plan_id']));
        Schema::table('membership_plans', fn (Blueprint $table) => $table->dropConstrainedForeignId('product_id'));
    }
};
