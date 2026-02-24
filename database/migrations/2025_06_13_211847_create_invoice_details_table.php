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
        Schema::create('invoice_details', function (Blueprint $table) {
            $table->id();

            $table->unsignedInteger('invoice_id'); // Foreign key to the invoice table
            $table->string('description'); // Name of the item
            $table->decimal('quantity', 10, 2); // Quantity of the item
            $table->decimal('unit_price', 10, 2)->nullable(); // Price per unit of the item
            $table->decimal('total_price', 10, 2); // Total price for the item (quantity
            $table->boolean('active')->default(true); // true = active, false = inactive
            $table->string('created_by')->nullable(); // User who created the invoice
            $table->string('updated_by')->nullable(); // User who last updated the invoice
            $table->string('deleted_by')->nullable(); // User who deleted the invoice, if applicable
            $table->timestamps();

            $table->foreign('invoice_id')->references('id')->on('invoice');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('invoice_details');
    }
};
