<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('wh_id');
            $table->unsignedBigInteger('bin_id');
            $table->unsignedBigInteger('stock_material_id');
            $table->unsignedBigInteger('whl_item_id');
            $table->enum('log_type', ['Quantity adjustment', 'Transfer to a new bin', 'Add a material'])->nullable();
            $table->integer('previous_qty')->nullable();
            $table->integer('new_qty')->nullable();
            $table->unsignedBigInteger('old_bin')->nullable();
            $table->unsignedBigInteger('new_bin')->nullable();
            $table->unsignedBigInteger('old_material')->nullable();
            $table->unsignedBigInteger('new_material')->nullable();
            $table->unsignedBigInteger('updated_by');
            $table->dateTime('updated_at');

            $table->foreign('wh_id')->references('id')->on('warehouses')->onDelete('cascade');
            $table->foreign('bin_id')->references('id')->on('warehouse_locations')->onDelete('cascade');
            $table->foreign('stock_material_id')->references('id')->on('stock_materials')->onDelete('cascade');
            $table->foreign('whl_item_id')->references('id')->on('whl_items')->onDelete('cascade');
            $table->foreign('old_bin')->references('id')->on('warehouse_locations')->onDelete('set null');
            $table->foreign('new_bin')->references('id')->on('warehouse_locations')->onDelete('set null');
            $table->foreign('old_material')->references('id')->on('stock_materials')->onDelete('set null');
            $table->foreign('new_material')->references('id')->on('stock_materials')->onDelete('set null');
            $table->foreign('updated_by')->references('id')->on('users')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_logs');
    }
};
