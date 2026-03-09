<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('batches', 'model_id')) {
            Schema::table('batches', function (Blueprint $table) {
                $table->unsignedBigInteger('model_id')->after('id');
                $table->foreign('model_id')->references('id')->on('models')->onDelete('restrict');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('batches', 'model_id')) {
            Schema::table('batches', function (Blueprint $table) {
                $table->dropForeign(['model_id']);
                $table->dropColumn('model_id');
            });
        }
    }
};
