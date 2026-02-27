<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_materials', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->string('code', 100);
            $table->string('supplier', 250)->nullable();
            $table->integer('lead_time')->nullable();
            $table->integer('min_qty')->nullable();
            $table->json('size');
            $table->double('unit_price', 15, 2)->nullable();
            $table->unsignedBigInteger('uom_id');
            $table->string('category', 20)->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->foreign('uom_id')->references('id')->on('uoms')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_materials');
    }
};
