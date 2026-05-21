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
        Schema::table('batches', function (Blueprint $table) {
            $table->unsignedBigInteger('model_id')->nullable()->change();
            $table->json('qty_json')->nullable()->change();
            $table->unsignedBigInteger('main_model_id')->nullable()->after('model_id');

            $table->unique('batch_no');

            $table->foreign('main_model_id')->references('id')->on('main_models')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('batches', function (Blueprint $table) {
            $table->dropForeign(['main_model_id']);
            $table->dropUnique(['batch_no']);
        });
    }
};
