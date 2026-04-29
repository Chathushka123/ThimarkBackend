<?php

namespace App\Http\Repositories;

use Illuminate\Support\Facades\DB;

class GrnPendingCompletedReportRepository
{
    public function getReport(int $queryType): array
    {
        switch ($queryType) {
            case 1:
                return DB::select(
                    "SELECT
                        g.id AS grn_id,
                        g.rmpono AS po_number,
                        g.remark AS supplier,
                        w.name AS warehouse,
                        g.created_at AS received_date,
                        DATEDIFF(NOW(), g.created_at) AS days_open,
                        COUNT(gd.id) AS line_items,
                        SUM(gd.qty) AS total_qty_received,
                        SUM(gd.available_qty) AS total_qty_available,
                        ROUND(SUM(gd.qty * gd.grn_price), 2) AS total_grn_value,
                        ROUND(SUM(gd.available_qty * gd.grn_price), 2) AS remaining_stock_value,
                        u.name AS created_by
                    FROM grns g
                    LEFT JOIN grn_details gd ON gd.grn_id = g.id AND gd.active = 1
                    LEFT JOIN warehouses w ON w.id = g.warehouse_id
                    LEFT JOIN users u ON u.id = g.created_by
                    WHERE g.active = 1
                      AND g.status = 'open'
                    GROUP BY g.id, w.id, u.id
                    ORDER BY days_open DESC"
                );

            case 2:
                return DB::select(
                    "SELECT
                        g.id AS grn_id,
                        g.rmpono AS po_number,
                        g.remark AS supplier,
                        w.name AS warehouse,
                        g.created_at AS received_date,
                        g.updated_at AS completed_date,
                        TIMESTAMPDIFF(MINUTE, g.created_at, g.updated_at) AS completion_time_mins,
                        COUNT(gd.id) AS line_items,
                        SUM(gd.qty) AS total_qty_received,
                        ROUND(SUM(gd.qty * gd.grn_price), 2) AS total_grn_value,
                        u.name AS created_by
                    FROM grns g
                    LEFT JOIN grn_details gd ON gd.grn_id = g.id AND gd.active = 1
                    LEFT JOIN warehouses w ON w.id = g.warehouse_id
                    LEFT JOIN users u ON u.id = g.created_by
                    WHERE g.active = 1
                      AND g.status = 'completed'
                    GROUP BY g.id, w.id, u.id
                    ORDER BY g.created_at DESC"
                );

            case 4:
                return DB::select(
                    "SELECT
                        g.id AS grn_id,
                        g.rmpono AS po_number,
                        g.remark AS supplier,
                        g.created_at AS grn_date,
                        DATEDIFF(NOW(), g.created_at) AS days_open,
                        w.name AS warehouse,
                        wl.rack,
                        wl.bin,
                        sm.code AS material_code,
                        sm.name AS material_name,
                        sm.category,
                        gd.qty AS received_qty,
                        gd.available_qty AS available_qty,
                        gd.grn_price AS unit_price,
                        ROUND(gd.qty * gd.grn_price, 2) AS line_value
                    FROM grns g
                    JOIN grn_details gd ON gd.grn_id = g.id AND gd.active = 1
                    JOIN whl_items wi ON wi.id = gd.whl_item_id
                    JOIN warehouse_locations wl ON wl.id = wi.whl_id
                    JOIN warehouses w ON w.id = wl.warehouse_id
                    JOIN stock_materials sm ON sm.id = wi.stock_item_id
                    WHERE g.active = 1
                      AND g.status = 'open'
                    ORDER BY days_open DESC, g.id, sm.code"
                );

            case 5:
                return DB::select(
                    "SELECT
                        g.remark AS supplier,
                        COUNT(DISTINCT g.id) AS total_grns,
                        SUM(CASE WHEN g.status = 'open' THEN 1 ELSE 0 END) AS open_grns,
                        SUM(CASE WHEN g.status = 'completed' THEN 1 ELSE 0 END) AS completed_grns,
                        ROUND(SUM(gd.qty * gd.grn_price), 2) AS total_purchase_value,
                        ROUND(SUM(CASE WHEN g.status = 'open' THEN gd.qty * gd.grn_price ELSE 0 END), 2) AS pending_value,
                        MIN(g.created_at) AS first_delivery,
                        MAX(g.created_at) AS last_delivery
                    FROM grns g
                    LEFT JOIN grn_details gd ON gd.grn_id = g.id AND gd.active = 1
                    WHERE g.active = 1
                    GROUP BY g.remark
                    ORDER BY total_purchase_value DESC"
                );

            default:
                return [];
        }
    }
}