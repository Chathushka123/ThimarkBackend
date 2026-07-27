<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('bundle_ticket_secondaries', function (Blueprint $table) {
            $table->enum('source', ['SCAN', 'REWORK_RETURN'])->default('SCAN')->after('scan_qty');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('bundle_ticket_secondaries', function (Blueprint $table) {
            $table->dropColumn('source');
        });
    }
};
