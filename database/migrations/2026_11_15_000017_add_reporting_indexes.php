<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->index(['environment', 'status', 'paid_at'], 'payments_reporting_period');
            $table->index(['order_id', 'status', 'requires_review'], 'payments_reporting_order');
        });
        Schema::table('orders', fn (Blueprint $table) => $table->index('created_at', 'orders_reporting_created'));
        Schema::table('appointments', fn (Blueprint $table) => $table->index('created_at', 'appointments_reporting_created'));
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropIndex('payments_reporting_period');
            $table->dropIndex('payments_reporting_order');
        });
        Schema::table('orders', fn (Blueprint $table) => $table->dropIndex('orders_reporting_created'));
        Schema::table('appointments', fn (Blueprint $table) => $table->dropIndex('appointments_reporting_created'));
    }
};
