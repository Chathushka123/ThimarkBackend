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
        Schema::create('invoice', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('address');
            $table->string('mobile')->nullable();
            $table->string('landline')->nullable();
            $table->string('status')->default('pending'); // pending, paid, cancelled
            $table->decimal('amount', 10, 2); // Total amount of the invoice
            $table->decimal('paid_amount', 10, 2)->default(0.00); // Amount already paid
            $table->decimal('due_amount', 10, 2)->default(0.00); // Amount still due
            $table->date('invoice_date'); // Date of the invoice
            $table->date('due_date')->nullable(); // Due date for payment
            $table->boolean('active')->default(true); // true = active, false = inactive
            $table->string('created_by')->nullable(); // User who created the invoice
            $table->string('updated_by')->nullable(); // User who last updated the invoice
            $table->string('deleted_by')->nullable(); // User who deleted the invoice, if applicable
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('invoice');
    }
};
