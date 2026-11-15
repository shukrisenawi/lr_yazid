<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('content_pages', function (Blueprint $table) {
            $table->id();
            $table->string('title', 200);
            $table->string('slug', 200)->unique();
            $table->longText('content');
            $table->string('seo_title', 200)->nullable();
            $table->string('meta_description', 300)->nullable();
            $table->string('status', 20)->default('draft')->index();
            $table->boolean('show_in_footer')->default(false);
            $table->unsignedInteger('version')->default(1);
            $table->timestamps();
        });
        Schema::create('content_page_revisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('content_page_id')->constrained()->restrictOnDelete();
            $table->foreignId('admin_id')->constrained('users')->restrictOnDelete();
            $table->unsignedInteger('version');
            $table->json('snapshot');
            $table->timestamps();
            $table->unique(['content_page_id', 'version']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('content_page_revisions');
        Schema::dropIfExists('content_pages');
    }
};
