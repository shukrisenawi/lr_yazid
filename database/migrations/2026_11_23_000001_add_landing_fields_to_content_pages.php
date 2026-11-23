<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('content_pages', function (Blueprint $table) {
            $table->boolean('is_landing')->default(false);
            $table->string('custom_domain', 253)->nullable()->unique();
        });
    }

    public function down(): void
    {
        Schema::table('content_pages', function (Blueprint $table) {
            $table->dropUnique(['custom_domain']);
            $table->dropColumn(['is_landing', 'custom_domain']);
        });
    }
};
