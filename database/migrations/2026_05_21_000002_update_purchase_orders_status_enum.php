<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up()
    {
        DB::statement("ALTER TABLE purchase_orders MODIFY COLUMN status ENUM('DRAFT','OPEN','PENDING APPROVAL','APPROVED','SENT','RECEIVED','CLOSED','CANCELLED') NOT NULL DEFAULT 'DRAFT'");
    }

    public function down()
    {
        DB::statement("ALTER TABLE purchase_orders MODIFY COLUMN status ENUM('DRAFT','APPROVED','SENT','RECEIVED','CANCELLED') NOT NULL DEFAULT 'DRAFT'");
    }
};
