<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('website_settings', function (Blueprint $table) {
            $table->id();
            $table->string('website_name', 80)->default('Yazid Collective');
            $table->string('logo_path')->nullable();
            $table->string('favicon_path')->nullable();
            $table->string('contact_email')->nullable();
            $table->string('contact_phone', 40)->nullable();
            $table->text('address')->nullable();
            $table->string('footer_information', 300)->default('Thoughtfully selected. Personally connected.');
            $table->json('social_links')->nullable();
            $table->unsignedInteger('version')->default(1);
            $table->timestamps();
        });
        Schema::create('homepage_banners', function (Blueprint $table) {
            $table->id();
            $table->string('title', 100);
            $table->string('subtitle', 200)->nullable();
            $table->string('image_path')->nullable();
            $table->string('image_alt', 200)->nullable();
            $table->string('link_label', 40)->nullable();
            $table->string('link_path', 500)->nullable();
            $table->string('status', 20)->default('draft')->index();
            $table->unsignedInteger('position')->default(0);
            $table->dateTime('starts_at')->nullable();
            $table->dateTime('ends_at')->nullable();
            $table->unsignedInteger('version')->default(1);
            $table->timestamps();
        });
        Schema::create('website_changes', function (Blueprint $table) {
            $table->id();
            $table->string('entity_type', 20);
            $table->unsignedBigInteger('entity_id');
            $table->unsignedInteger('version');
            $table->foreignId('admin_id')->constrained('users')->restrictOnDelete();
            $table->json('snapshot');
            $table->timestamps();
            $table->unique(['entity_type', 'entity_id', 'version']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('website_changes');
        Schema::dropIfExists('homepage_banners');
        Schema::dropIfExists('website_settings');
    }
};
