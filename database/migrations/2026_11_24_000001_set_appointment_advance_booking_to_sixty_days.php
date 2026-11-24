<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('appointment_services', function (Blueprint $table) {
            $table->unsignedSmallInteger('maximum_advance_days')->default(60)->change();
        });

        DB::table('appointment_services')->update(['maximum_advance_days' => 60]);
    }

    public function down(): void
    {
        Schema::table('appointment_services', function (Blueprint $table) {
            $table->unsignedSmallInteger('maximum_advance_days')->default(90)->change();
        });
    }
};
