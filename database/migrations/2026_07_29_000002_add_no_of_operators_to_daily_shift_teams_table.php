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
        Schema::table('daily_shift_teams', function (Blueprint $table) {
            $table->unsignedInteger('no_of_operators')->default(0)->after('team_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('daily_shift_teams', function (Blueprint $table) {
            $table->dropColumn('no_of_operators');
        });
    }
};
