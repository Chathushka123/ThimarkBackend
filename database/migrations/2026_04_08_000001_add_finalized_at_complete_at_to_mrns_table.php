<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddFinalizedAtCompleteAtToMrnsTable extends Migration
{
    public function up()
    {
        Schema::table('mrns', function (Blueprint $table) {
            $table->dateTime('finalized_at')->nullable()->after('status');
            $table->dateTime('complete_at')->nullable()->after('finalized_at');
        });
    }

    public function down()
    {
        Schema::table('mrns', function (Blueprint $table) {
            $table->dropColumn(['finalized_at', 'complete_at']);
        });
    }
}
