<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('mrns', 'warehouse_id')) {
            Schema::table('mrns', function (Blueprint $table) {
                $table->unsignedBigInteger('warehouse_id')->after('batch_id');
                $table->foreign('warehouse_id')->references('id')->on('warehouses')->onDelete('restrict');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('mrns', 'warehouse_id')) {
            Schema::table('mrns', function (Blueprint $table) {
                $table->dropForeign(['warehouse_id']);
                $table->dropColumn('warehouse_id');
            });
        }
    }
};
