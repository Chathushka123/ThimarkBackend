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
        Schema::table('bundle_tickets', function (Blueprint $table) {
            $table->unique(['bundle_id', 'work_order_operation_id', 'direction'], 'bundle_tickets_bundle_woo_direction_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('bundle_tickets', function (Blueprint $table) {
            $table->dropUnique('bundle_tickets_bundle_woo_direction_unique');
        });
    }
};
