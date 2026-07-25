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
        Schema::table('bundle_ticket_rejects', function (Blueprint $table) {
            $table->unsignedBigInteger('reason_id')->nullable()->after('bundle_ticket_id');
            $table->enum('source', ['DIRECT', 'REWORK_REJECT'])->default('DIRECT')->after('reject_reason');

            $table->foreign('reason_id')->references('id')->on('reasons');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('bundle_ticket_rejects', function (Blueprint $table) {
            $table->dropForeign(['reason_id']);
            $table->dropColumn(['reason_id', 'source']);
        });
    }
};
