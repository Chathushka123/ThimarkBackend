<?php

namespace App\Http\Repositories;

use Illuminate\Support\Facades\DB;

class GrnReportRepository
{
    public function getReport(string $fromDate, string $toDate): array
    {
        return DB::select(
            "SELECT
                DATE(gd.created_at) AS Date,
                g.id AS GrnID, 
                COALESCE(po.po_number, g.rmpono) AS RMPONO,
                s.name AS supplier,
                sm.code,
                sm.name,
                gd.grn_price AS unite_price,
                gd.qty,
                u.email AS created_by,
                g.status as grn_status
                
            FROM grns g
            LEFT JOIN purchase_orders po ON po.id = g.rmpono
            LEFT JOIN suppliers s ON s.id = po.supplier_id
            JOIN grn_details gd ON gd.grn_id = g.id
            JOIN whl_items wi ON gd.whl_item_id = wi.id
            JOIN stock_materials sm ON sm.id = wi.stock_item_id
            JOIN users u ON u.id = gd.created_by
            WHERE gd.active = 1
              AND DATE(gd.created_at) >= ?
              AND DATE(gd.created_at) <= ?
            ORDER BY gd.created_at ASC",
            [$fromDate, $toDate]
        );
    }
}
