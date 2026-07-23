<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Assigns each user the set of Operations (scanning stations) they are
     * allowed to scan for. A user can be assigned more than one operation
     * (e.g. cross-trained operators); the Production WIP Scanning screen
     * lets them pick among their own assigned operations only.
     */
    public function up(): void
    {
        Schema::create('user_operations', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('operation_id');
            $table->boolean('active')->default(true);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'operation_id']);
            $table->index('active');

            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('operation_id')->references('id')->on('operation_masters')->cascadeOnDelete();
            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
            $table->foreign('updated_by')->references('id')->on('users')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_operations');
    }
};
