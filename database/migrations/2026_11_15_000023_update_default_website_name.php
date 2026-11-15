<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('website_settings', function (Blueprint $table) {
            $table->string('website_name', 80)->default('Csiti.my')->change();
        });

        DB::table('website_settings')->where('website_name', 'Yazid Collective')->update([
            'website_name' => 'Csiti.my',
            'version' => DB::raw('version + 1'),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::table('website_settings', function (Blueprint $table) {
            $table->string('website_name', 80)->default('Yazid Collective')->change();
        });

        // Keep published names intact when rolling back the schema default.
    }
};
