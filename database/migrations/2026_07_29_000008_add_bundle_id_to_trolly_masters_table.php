<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('trolly_masters', function (Blueprint $table) {
            $table->unsignedBigInteger('bundle_id')->nullable()->after('name');
            $table->foreign('bundle_id')->references('id')->on('bundles')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('trolly_masters', function (Blueprint $table) {
            $table->dropForeign(['bundle_id']);
            $table->dropColumn('bundle_id');
        });
    }
};
