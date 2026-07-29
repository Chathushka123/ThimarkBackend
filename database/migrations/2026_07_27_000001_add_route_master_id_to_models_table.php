<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('models', function (Blueprint $table) {
            $table->unsignedBigInteger('route_master_id')->nullable()->after('main_model_id');

            $table->foreign('route_master_id')->references('id')->on('route_masters')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('models', function (Blueprint $table) {
            $table->dropForeign(['route_master_id']);
            $table->dropColumn('route_master_id');
        });
    }
};
