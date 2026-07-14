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
        Schema::table('daily_shifts', function (Blueprint $table) {
            $table->renameColumn('current_date', 'shift_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('daily_shifts', function (Blueprint $table) {
            $table->renameColumn('shift_date', 'current_date');
        });
    }
};
