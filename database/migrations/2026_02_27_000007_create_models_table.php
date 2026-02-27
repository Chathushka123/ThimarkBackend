<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('models', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('main_model_id');
            $table->string('color');
            $table->json('sizes');
            $table->string('name');
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->foreign('main_model_id')->references('id')->on('main_models')->onDelete('cascade');
        });
    }

    public function down()
    {
        Schema::dropIfExists('models');
    }
};
