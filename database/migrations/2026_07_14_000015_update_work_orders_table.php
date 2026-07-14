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
        Schema::table('work_orders', function (Blueprint $table) {
            $table->dropForeign(['batch_id']);
            $table->dropColumn('batch_id');

            $table->unsignedBigInteger('batch_detail_id')->after('id');
            $table->foreign('batch_detail_id')->references('id')->on('batch_details');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('work_orders', function (Blueprint $table) {
            $table->dropForeign(['batch_detail_id']);
            $table->dropColumn('batch_detail_id');

            $table->unsignedBigInteger('batch_id')->after('id');
            $table->foreign('batch_id')->references('id')->on('batches');
        });
    }
};
