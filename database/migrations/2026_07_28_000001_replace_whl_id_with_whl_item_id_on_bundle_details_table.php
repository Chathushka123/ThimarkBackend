<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bundle_details', function (Blueprint $table) {
            $table->dropForeign(['whl_id']);
            $table->dropColumn('whl_id');

            $table->unsignedBigInteger('whl_item_id')->nullable()->after('stock_material_id');
            $table->foreign('whl_item_id')->references('id')->on('whl_items');
        });
    }

    public function down(): void
    {
        Schema::table('bundle_details', function (Blueprint $table) {
            $table->dropForeign(['whl_item_id']);
            $table->dropColumn('whl_item_id');

            $table->unsignedBigInteger('whl_id')->nullable()->after('stock_material_id');
            $table->foreign('whl_id')->references('id')->on('warehouse_locations');
        });
    }
};
