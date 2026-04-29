<?php

namespace App\Http\Repositories;

use Illuminate\Support\Facades\DB;

class CurrentStockReportRepository
{
    public function getReport(int $queryType): array
    {
        switch ($queryType) {
            case 1:
                return DB::select(
                    "SELECT
                        w.name AS warehouse,
                        sm.category AS category,
                        sm.code AS material_code,
                        sm.name AS material_name,
                        SUM(wi.qty) AS total_on_hand,
                        sm.min_qty AS reorder_level,
                        CASE
                            WHEN sm.min_qty IS NOT NULL AND SUM(wi.qty) <= sm.min_qty THEN 'LOW STOCK'
                            WHEN SUM(wi.qty) = 0 THEN 'OUT OF STOCK'
                            ELSE 'OK'
                        END AS stock_status,
                        MAX(wi.updated_at) AS last_movement
                    FROM whl_items wi
                    JOIN warehouse_locations wl ON wl.id = wi.whl_id
                    JOIN warehouses w ON w.id = wl.warehouse_id
                    JOIN stock_materials sm ON sm.id = wi.stock_item_id
                    WHERE wi.active = 1
                      AND wl.active = 1
                      AND sm.active = 1
                    GROUP BY w.id, sm.id
                    ORDER BY w.name, sm.category, sm.code"
                );

            case 2:
                return DB::select(
                    "SELECT
                        w.name AS warehouse,
                        wl.rack,
                        wl.bin,
                        sm.category AS category,
                        sm.code AS material_code,
                        sm.name AS material_name,
                        wi.qty AS qty_in_slot,
                        sm.min_qty AS reorder_level,
                        CASE
                            WHEN sm.min_qty IS NOT NULL AND wi.qty <= sm.min_qty THEN 'LOW'
                            WHEN wi.qty = 0 THEN 'EMPTY'
                            ELSE 'OK'
                        END AS status,
                        wi.updated_at AS last_updated
                    FROM whl_items wi
                    JOIN warehouse_locations wl ON wl.id = wi.whl_id
                    JOIN warehouses w ON w.id = wl.warehouse_id
                    JOIN stock_materials sm ON sm.id = wi.stock_item_id
                    WHERE wi.active = 1
                      AND wl.active = 1
                      AND sm.active = 1
                    ORDER BY w.name, wl.rack, wl.bin, sm.code"
                );

            case 3:
                return DB::select(
                    "SELECT
                        w.name AS warehouse,
                        g.rmpono AS po_number,
                        sm.code AS material_code,
                        sm.name AS material_name,
                        gd.qty AS original_received_qty,
                        gd.available_qty AS remaining_qty,
                        (gd.qty - gd.available_qty) AS consumed_qty,
                        ROUND((gd.qty - gd.available_qty) / gd.qty * 100, 1) AS consumed_pct,
                        gd.grn_price AS unit_price,
                        ROUND(gd.available_qty * gd.grn_price, 2) AS remaining_stock_value,
                        g.created_at AS received_date
                    FROM grn_details gd
                    JOIN grns g ON g.id = gd.grn_id
                    JOIN whl_items wi ON wi.id = gd.whl_item_id
                    JOIN warehouse_locations wl ON wl.id = wi.whl_id
                    JOIN warehouses w ON w.id = wl.warehouse_id
                    JOIN stock_materials sm ON sm.id = wi.stock_item_id
                    WHERE gd.active = 1
                      AND gd.available_qty > 0
                    ORDER BY w.name, sm.code, received_date ASC"
                );

            case 4:
                return DB::select(
                    "SELECT
                        w.name AS warehouse,
                        sm.category AS category,
                        COUNT(DISTINCT sm.id) AS distinct_materials,
                        SUM(wi.qty) AS total_units,
                        ROUND(SUM(gd_val.available_value), 2) AS estimated_stock_value
                    FROM whl_items wi
                    JOIN warehouse_locations wl ON wl.id = wi.whl_id
                    JOIN warehouses w ON w.id = wl.warehouse_id
                    JOIN stock_materials sm ON sm.id = wi.stock_item_id
                    LEFT JOIN (
                        SELECT
                            whl_item_id,
                            SUM(available_qty * grn_price) AS available_value
                        FROM grn_details
                        WHERE active = 1
                        GROUP BY whl_item_id
                    ) gd_val ON gd_val.whl_item_id = wi.id
                    WHERE wi.active = 1
                      AND wl.active = 1
                      AND sm.active = 1
                    GROUP BY w.id, sm.category
                    ORDER BY w.name, sm.category"
                );

            default:
                return [];
        }
    }
}