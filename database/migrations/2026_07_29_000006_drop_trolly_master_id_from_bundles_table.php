<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bundles', function (Blueprint $table) {
            $table->dropForeign(['trolly_master_id']);
            $table->dropColumn('trolly_master_id');
        });
    }

    public function down(): void
    {
        Schema::table('bundles', function (Blueprint $table) {
            $table->unsignedBigInteger('trolly_master_id')->nullable()->after('work_order_id');
            $table->foreign('trolly_master_id')->references('id')->on('trolly_masters')->nullOnDelete();
        });
    }
};
