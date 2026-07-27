<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The original `reworks` table conflated "sent to rework" and "returned
 * from rework" into a single row created at once, with no link into the
 * scan ledger — it was never wired into any controller/route. Superseded
 * by `bundle_ticket_reworks` (the send) and `bundle_ticket_rework_returns`
 * (the return), which integrate with the scan/reject ledger.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::dropIfExists('reworks');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::create('reworks', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('bundle_ticket_id');
            $table->integer('rework_qty');
            $table->integer('return_qty');
            $table->unsignedBigInteger('daily_shift_team_id');
            $table->boolean('active')->default(true);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();

            $table->foreign('bundle_ticket_id')->references('id')->on('bundle_tickets');
            $table->foreign('daily_shift_team_id')->references('id')->on('daily_shift_teams');
        });
    }
};
