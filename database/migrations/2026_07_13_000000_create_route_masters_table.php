<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Creates the "route_masters" table used as a standalone routing master
     * lookup. Kept separate from the existing "routings" table/model to
     * avoid clashing with the current Routing / RoutingOperation / Style
     * relations already in production use.
     */
    public function up(): void
    {
        Schema::create('route_masters', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('route_code')->unique();
            $table->string('description')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamp('created_at')->useCurrent();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();

            $table->index('active');

            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
            $table->foreign('updated_by')->references('id')->on('users')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('route_masters');
    }
};
