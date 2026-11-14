<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('addresses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('full_name');
            $table->string('phone', 20);
            $table->string('line1');
            $table->string('line2')->nullable();
            $table->string('city', 100);
            $table->string('state', 100);
            $table->string('postcode', 5);
            $table->string('country_code', 2)->default('MY');
            $table->boolean('is_default')->default(false);
            $table->timestamps();
            $table->index(['user_id', 'is_default']);
        });
        Schema::create('carts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->unique()->constrained()->cascadeOnDelete();
            $table->string('guest_token_hash', 64)->nullable()->unique();
            $table->timestamps();
        });
        Schema::create('cart_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cart_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->foreignId('product_variant_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('item_key', 80);
            $table->unsignedSmallInteger('quantity');
            $table->timestamps();
            $table->unique(['cart_id', 'item_key']);
        });
        Schema::create('commerce_settings', function (Blueprint $table) {
            $table->unsignedTinyInteger('id')->primary();
            $table->unsignedInteger('shipping_fee_sen')->default(1000);
            $table->boolean('shipping_enabled')->default(true);
            $table->unsignedSmallInteger('reservation_minutes')->default(30);
            $table->timestamps();
        });
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->string('reference', 40)->unique();
            $table->uuid('checkout_key');
            $table->string('customer_name');
            $table->string('customer_email');
            $table->string('customer_phone', 20);
            $table->json('shipping_address')->nullable();
            $table->string('currency', 3)->default('MYR');
            $table->unsignedBigInteger('subtotal_sen');
            $table->unsignedBigInteger('shipping_fee_sen');
            $table->unsignedBigInteger('total_sen');
            $table->string('payment_status', 20)->default('pending')->index();
            $table->string('fulfillment_status', 20)->nullable()->default('new');
            $table->text('notes')->nullable();
            $table->string('cancellation_reason')->nullable();
            $table->dateTime('expires_at')->index();
            $table->timestamps();
            $table->unique(['user_id', 'checkout_key']);
        });
        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->restrictOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->foreignId('product_variant_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('product_name');
            $table->string('variant_name')->nullable();
            $table->string('sku');
            $table->string('type', 20)->default('physical');
            $table->unsignedSmallInteger('quantity');
            $table->unsignedBigInteger('unit_price_sen');
            $table->unsignedBigInteger('line_total_sen');
            $table->timestamps();
        });
        Schema::create('stock_reservations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->restrictOnDelete();
            $table->foreignId('order_item_id')->unique()->constrained()->restrictOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->foreignId('product_variant_id')->nullable()->constrained()->restrictOnDelete();
            $table->unsignedSmallInteger('quantity');
            $table->string('status', 20)->default('active');
            $table->dateTime('expires_at');
            $table->timestamps();
            $table->index(['product_id', 'product_variant_id', 'status', 'expires_at'], 'reservation_availability_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_reservations');
        Schema::dropIfExists('order_items');
        Schema::dropIfExists('orders');
        Schema::dropIfExists('commerce_settings');
        Schema::dropIfExists('cart_items');
        Schema::dropIfExists('carts');
        Schema::dropIfExists('addresses');
    }
};
