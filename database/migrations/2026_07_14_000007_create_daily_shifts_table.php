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
        Schema::create('daily_shifts', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('shift_id');
            $table->date('current_date');
            $table->dateTime('start_date_time');
            $table->dateTime('end_date_time');
            $table->boolean('active')->default(true);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();

            $table->foreign('shift_id')->references('id')->on('shifts');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('daily_shifts');
    }
};
