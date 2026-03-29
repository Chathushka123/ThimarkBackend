<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('grn_details', function (Blueprint $table) {
            $table->unsignedBigInteger('grn_id')->nullable()->after('id');
            $table->foreign('grn_id')->references('id')->on('grns')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::table('grn_details', function (Blueprint $table) {
            $table->dropForeign(['grn_id']);
            $table->dropColumn('grn_id');
        });
    }
};
