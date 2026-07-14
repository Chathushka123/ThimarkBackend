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
        Schema::table('bundle_details', function (Blueprint $table) {
            $table->unsignedBigInteger('whl_id')->nullable()->after('stock_material_id');
            $table->foreign('whl_id')->references('id')->on('warehouse_locations');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('bundle_details', function (Blueprint $table) {
            $table->dropForeign(['whl_id']);
            $table->dropColumn('whl_id');
        });
    }
};
