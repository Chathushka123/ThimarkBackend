<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('warehouse_locations', function (Blueprint $table) {
            $table->unsignedBigInteger('stock_item_id')->nullable()->after('warehouse_id');
            $table->foreign('stock_item_id')->references('id')->on('stock_materials')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::table('warehouse_locations', function (Blueprint $table) {
            $table->dropForeign(['stock_item_id']);
            $table->dropColumn('stock_item_id');
        });
    }
};
