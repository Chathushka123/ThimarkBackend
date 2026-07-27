<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * reject_reason is now a supplementary free-text remark — reason_id (the
 * reasons master dropdown) is the required field going forward. Raw SQL is
 * used here instead of Blueprint::change() since doctrine/dbal isn't
 * installed in this project.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::statement('ALTER TABLE bundle_ticket_rejects MODIFY reject_reason VARCHAR(255) NULL');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement("UPDATE bundle_ticket_rejects SET reject_reason = '' WHERE reject_reason IS NULL");
        DB::statement('ALTER TABLE bundle_ticket_rejects MODIFY reject_reason VARCHAR(255) NOT NULL');
    }
};
