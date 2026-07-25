<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Seeds a virtual "REWORK" operation. The rework team is assigned to it via
 * user_operations exactly like any production operation, so the WIP
 * Scanning screen's existing "pick your operation, pick your team, scan"
 * flow can detect operation_code === 'REWORK' and switch into rework-queue
 * mode without a separate permission system.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $now = now();
        DB::table('operation_masters')->insert([
            'operation_code' => 'REWORK',
            'description' => 'Rework Station',
            'active' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('operation_masters')->where('operation_code', 'REWORK')->delete();
    }
};
