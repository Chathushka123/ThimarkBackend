<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        $this->addIndexIfMissing('purchase_orders', 'idx_po_order_date', ['order_date']);
        $this->addIndexIfMissing('purchase_orders', 'idx_po_supplier_id', ['supplier_id']);
        $this->addIndexIfMissing('purchase_orders', 'idx_po_status', ['status']);
        $this->addIndexIfMissing('purchase_orders', 'idx_po_created_by', ['created_by']);

        $this->addIndexIfMissing('grns', 'idx_grns_created_at', ['created_at']);
        $this->addIndexIfMissing('grns', 'idx_grns_warehouse_id', ['warehouse_id']);
        $this->addIndexIfMissing('grns', 'idx_grns_status', ['status']);

        $this->addIndexIfMissing('grn_details', 'idx_grn_details_grn_id', ['grn_id']);
        $this->addIndexIfMissing('grn_details', 'idx_grn_details_stock_item_id', ['stock_item_id']);
        $this->addIndexIfMissing('grn_details', 'idx_grn_details_active', ['active']);

        $this->addIndexIfMissing('mrns', 'idx_mrns_created_at', ['created_at']);
        $this->addIndexIfMissing('mrns', 'idx_mrns_batch_id', ['batch_id']);
        $this->addIndexIfMissing('mrns', 'idx_mrns_warehouse_id', ['warehouse_id']);
        $this->addIndexIfMissing('mrns', 'idx_mrns_status', ['status']);
        $this->addIndexIfMissing('mrns', 'idx_mrns_issued_to', ['issued_to']);

        $this->addIndexIfMissing('mrn_details', 'idx_mrn_details_mrn_id', ['mrn_id']);
        $this->addIndexIfMissing('mrn_details', 'idx_mrn_details_stock_item_id', ['stock_item_id']);
        $this->addIndexIfMissing('mrn_details', 'idx_mrn_details_active', ['active']);

        $this->addIndexIfMissing('purchase_order_payments', 'idx_pop_purchase_order_id', ['purchase_order_id']);
        $this->addIndexIfMissing('purchase_order_payments', 'idx_pop_active', ['active']);
    }

    public function down(): void
    {
        $this->dropIndexIfExists('purchase_orders', 'idx_po_order_date');
        $this->dropIndexIfExists('purchase_orders', 'idx_po_supplier_id');
        $this->dropIndexIfExists('purchase_orders', 'idx_po_status');
        $this->dropIndexIfExists('purchase_orders', 'idx_po_created_by');

        $this->dropIndexIfExists('grns', 'idx_grns_created_at');
        $this->dropIndexIfExists('grns', 'idx_grns_warehouse_id');
        $this->dropIndexIfExists('grns', 'idx_grns_status');

        $this->dropIndexIfExists('grn_details', 'idx_grn_details_grn_id');
        $this->dropIndexIfExists('grn_details', 'idx_grn_details_stock_item_id');
        $this->dropIndexIfExists('grn_details', 'idx_grn_details_active');

        $this->dropIndexIfExists('mrns', 'idx_mrns_created_at');
        $this->dropIndexIfExists('mrns', 'idx_mrns_batch_id');
        $this->dropIndexIfExists('mrns', 'idx_mrns_warehouse_id');
        $this->dropIndexIfExists('mrns', 'idx_mrns_status');
        $this->dropIndexIfExists('mrns', 'idx_mrns_issued_to');

        $this->dropIndexIfExists('mrn_details', 'idx_mrn_details_mrn_id');
        $this->dropIndexIfExists('mrn_details', 'idx_mrn_details_stock_item_id');
        $this->dropIndexIfExists('mrn_details', 'idx_mrn_details_active');

        $this->dropIndexIfExists('purchase_order_payments', 'idx_pop_purchase_order_id');
        $this->dropIndexIfExists('purchase_order_payments', 'idx_pop_active');
    }

    private function addIndexIfMissing(string $table, string $indexName, array $columns): void
    {
        if (!Schema::hasTable($table) || $this->indexExists($table, $indexName)) {
            return;
        }

        Schema::table($table, function (Blueprint $blueprint) use ($columns, $indexName) {
            $blueprint->index($columns, $indexName);
        });
    }

    private function dropIndexIfExists(string $table, string $indexName): void
    {
        if (!Schema::hasTable($table) || !$this->indexExists($table, $indexName)) {
            return;
        }

        Schema::table($table, function (Blueprint $blueprint) use ($indexName) {
            $blueprint->dropIndex($indexName);
        });
    }

    private function indexExists(string $table, string $indexName): bool
    {
        $database = DB::getDatabaseName();

        $row = DB::table('information_schema.statistics')
            ->where('table_schema', $database)
            ->where('table_name', $table)
            ->where('index_name', $indexName)
            ->first();

        return $row !== null;
    }
};
