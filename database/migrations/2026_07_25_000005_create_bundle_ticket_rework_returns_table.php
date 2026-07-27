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
        Schema::create('bundle_ticket_rework_returns', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('bundle_ticket_id');
            $table->integer('return_qty')->default(0);
            $table->integer('reject_qty')->default(0);
            $table->unsignedBigInteger('reason_id')->nullable();
            $table->string('remarks')->nullable();
            $table->unsignedBigInteger('daily_shift_team_id');
            $table->unsignedBigInteger('bundle_ticket_secondary_id')->nullable();
            $table->unsignedBigInteger('bundle_ticket_reject_id')->nullable();
            $table->boolean('active')->default(true);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();

            $table->foreign('bundle_ticket_id')->references('id')->on('bundle_tickets');
            $table->foreign('reason_id')->references('id')->on('reasons');
            $table->foreign('daily_shift_team_id')->references('id')->on('daily_shift_teams');
            $table->foreign('bundle_ticket_secondary_id')->references('id')->on('bundle_ticket_secondaries');
            $table->foreign('bundle_ticket_reject_id')->references('id')->on('bundle_ticket_rejects');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bundle_ticket_rework_returns');
    }
};
