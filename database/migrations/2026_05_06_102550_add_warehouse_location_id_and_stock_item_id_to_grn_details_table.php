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
        Schema::table('grn_details', function (Blueprint $table) {
            $table->unsignedBigInteger('warehouse_location_id')->nullable()->after('grn_id');
            $table->unsignedBigInteger('stock_item_id')->nullable()->after('warehouse_location_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('grn_details', function (Blueprint $table) {
            $table->dropColumn(['warehouse_location_id', 'stock_item_id']);
        });
    }
};
