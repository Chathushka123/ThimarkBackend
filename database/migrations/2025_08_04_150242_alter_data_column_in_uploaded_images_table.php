<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
public function up()
{
    Schema::table('uploaded_images', function (Blueprint $table) {
         DB::statement('ALTER TABLE uploaded_images MODIFY data MEDIUMBLOB');
    });
}

public function down()
{
    Schema::table('uploaded_images', function (Blueprint $table) {
         DB::statement('ALTER TABLE uploaded_images MODIFY data BLOB');
    });
}
};
