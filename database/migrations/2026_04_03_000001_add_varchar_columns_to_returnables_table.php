<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('returnables', function (Blueprint $table) {
            $table->string('remarks', 255)->nullable();
            // Add more columns as needed
        });
    }

    public function down(): void
    {
        Schema::table('returnables', function (Blueprint $table) {
            $table->dropColumn(['column1', 'column2']);
            // Drop more columns as needed
        });
    }
};
