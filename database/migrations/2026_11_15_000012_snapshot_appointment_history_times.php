<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('appointment_status_histories', function (Blueprint $table) {
            $table->dateTime('previous_starts_at')->nullable();
            $table->dateTime('previous_ends_at')->nullable();
            $table->dateTime('new_starts_at')->nullable();
            $table->dateTime('new_ends_at')->nullable();
        });
        DB::table('appointment_status_histories')->orderBy('id')->each(function ($history) {
            $old = $history->previous_slot_id ? DB::table('appointment_slots')->where('id', $history->previous_slot_id)->first() : null;
            $new = DB::table('appointment_slots')->where('id', $history->new_slot_id)->first();
            DB::table('appointment_status_histories')->where('id', $history->id)->update([
                'previous_starts_at' => $old?->starts_at, 'previous_ends_at' => $old?->ends_at,
                'new_starts_at' => $new?->starts_at, 'new_ends_at' => $new?->ends_at,
            ]);
        });
    }

    public function down(): void
    {
        Schema::table('appointment_status_histories', fn (Blueprint $table) => $table->dropColumn(['previous_starts_at', 'previous_ends_at', 'new_starts_at', 'new_ends_at']));
    }
};
