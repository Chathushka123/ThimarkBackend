<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mrn_details', function (Blueprint $table) {
            // Drop the existing foreign key constraint on whl_id
            $table->dropForeign(['whl_id']);

            // Make whl_id nullable
            $table->unsignedBigInteger('whl_id')->nullable()->change();

            // Add issued_qty column
            $table->double('issued_qty')->nullable()->after('qty');

            // Recreate the foreign key constraint with nullable
            $table->foreign('whl_id')->references('id')->on('warehouse_locations')->onDelete('restrict');
        });
    }

    public function down(): void
    {
        Schema::table('mrn_details', function (Blueprint $table) {
            // Drop the foreign key
            $table->dropForeign(['whl_id']);

            // Remove issued_qty column
            $table->dropColumn('issued_qty');

            // Make whl_id not nullable again
            $table->unsignedBigInteger('whl_id')->nullable(false)->change();

            // Recreate the original foreign key constraint
            $table->foreign('whl_id')->references('id')->on('warehouse_locations')->onDelete('restrict');
        });
    }
};
