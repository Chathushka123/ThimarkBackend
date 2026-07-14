<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Creates "routing_operation_masters", a pivot table linking routings to
     * operation_masters. Named distinctly from the existing "routing_operations"
     * table (used by App\RoutingOperation / RoutingOperationController with a
     * different schema) to avoid colliding with it.
     */
    public function up(): void
    {
        Schema::create('routing_operation_masters', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('routing_id');
            $table->unsignedBigInteger('operation_id');
            $table->decimal('smv', 8, 4);
            $table->integer('seq');
            $table->boolean('in')->default(true);
            $table->boolean('out')->default(true);
            $table->boolean('active')->default(true);
            $table->timestamp('created_at')->useCurrent();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();

            $table->index('active');
            $table->index(['routing_id', 'seq']);

            $table->foreign('routing_id')->references('id')->on('route_masters')->cascadeOnDelete();
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
        Schema::dropIfExists('routing_operation_masters');
    }
};
