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
        Schema::create('daily_shift_teams', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('daily_shift_id');
            $table->unsignedBigInteger('team_id');
            $table->dateTime('start_date_time');
            $table->dateTime('end_date_time');
            $table->boolean('active')->default(true);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();

            $table->foreign('daily_shift_id')->references('id')->on('daily_shifts');
            $table->foreign('team_id')->references('id')->on('teams');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('daily_shift_teams');
    }
};
