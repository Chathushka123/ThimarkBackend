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
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('invoice_id');
            $table->decimal('amount', 10, 2); // Amount of the transaction
            $table->string('transaction_type')->nullable(); // e.g., 'payment', 'refund'
            $table->boolean('active')->default(true); // true = completed, false = failed/pending
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
        Schema::dropIfExists('transactions');
    }
};
