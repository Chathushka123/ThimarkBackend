<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mrn_details', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('mrn_id');
            $table->unsignedBigInteger('stock_item_id');
            $table->unsignedBigInteger('whl_id');
            $table->double('qty');
            $table->boolean('active')->default(true);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();

            $table->foreign('mrn_id')->references('id')->on('mrns')->onDelete('cascade');
            $table->foreign('stock_item_id')->references('id')->on('stock_materials')->onDelete('restrict');
            $table->foreign('whl_id')->references('id')->on('warehouse_locations')->onDelete('restrict');
            $table->foreign('created_by')->references('id')->on('users')->onDelete('set null');
            $table->foreign('updated_by')->references('id')->on('users')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mrn_details');
    }
};
