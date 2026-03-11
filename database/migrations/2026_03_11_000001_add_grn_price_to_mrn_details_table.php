<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mrn_details', function (Blueprint $table) {
            $table->double('grn_price')->nullable()->after('issued_qty');
        });
    }

    public function down(): void
    {
        Schema::table('mrn_details', function (Blueprint $table) {
            $table->dropColumn('grn_price');
        });
    }
};
