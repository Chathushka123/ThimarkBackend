<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddIssuedToToMrnDetailsTable extends Migration
{
    public function up()
    {
        Schema::table('mrn_details', function (Blueprint $table) {
            $table->string('issued_to')->nullable()->after('id');
        });
    }

    public function down()
    {
        Schema::table('mrn_details', function (Blueprint $table) {
            $table->dropColumn('issued_to');
        });
    }
}
