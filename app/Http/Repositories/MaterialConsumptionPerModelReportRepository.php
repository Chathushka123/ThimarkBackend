<?php

namespace App\Http\Repositories;

use Illuminate\Support\Facades\DB;

class MaterialConsumptionPerModelReportRepository
{
    public function getReport(int $queryType): array
    {
        switch ($queryType) {
            case 1:
                return DB::select(
                    "SELECT
                        mm.name AS main_model,
                        mo.name AS component_model,
                        sm.category AS material_category,
                        sm.code AS material_code,
                        sm.name AS material_name,
                        COUNT(DISTINCT m.id) AS mrns_count,
                        SUM(md.qty) AS total_qty_consumed,
                        ROUND(AVG(md.qty), 2) AS avg_qty_per_mrn,
                        ROUND(SUM(md.qty * COALESCE(md.grn_price, sm.unit_price, 0)), 2) AS total_consumption_value,
                        MIN(m.created_at) AS first_consumed,
                        MAX(m.created_at) AS last_consumed
                    FROM mrn_details md
                    JOIN mrns m ON m.id = md.mrn_id AND m.active = 1
                    JOIN batches b ON b.id = m.batch_id
                    JOIN models mo ON mo.id = b.model_id
                    JOIN main_models mm ON mm.id = mo.main_model_id
                    JOIN stock_materials sm ON sm.id = md.stock_item_id AND sm.active = 1
                    WHERE md.active = 1
                    GROUP BY mm.id, mo.id, sm.id
                    ORDER BY mm.name, mo.name, total_qty_consumed DESC"
                );

            case 2:
                return DB::select(
                    "SELECT
                        mm.name AS main_model,
                        sm.category AS material_category,
                        sm.code AS material_code,
                        sm.name AS material_name,
                        COUNT(DISTINCT mo.id) AS component_models_using,
                        COUNT(DISTINCT m.id) AS total_mrns,
                        SUM(md.qty) AS total_qty_consumed,
                        ROUND(SUM(md.qty) / COUNT(DISTINCT m.id), 2) AS avg_qty_per_mrn,
                        ROUND(SUM(md.qty * COALESCE(md.grn_price, sm.unit_price, 0)), 2) AS total_value_consumed,
                        ROUND(
                            SUM(md.qty) /
                            NULLIF(SUM(SUM(md.qty)) OVER (PARTITION BY mm.id), 0) * 100,
                        2) AS pct_of_model_consumption
                    FROM mrn_details md
                    JOIN mrns m ON m.id = md.mrn_id AND m.active = 1
                    JOIN batches b ON b.id = m.batch_id
                    JOIN models mo ON mo.id = b.model_id
                    JOIN main_models mm ON mm.id = mo.main_model_id
                    JOIN stock_materials sm ON sm.id = md.stock_item_id AND sm.active = 1
                    WHERE md.active = 1
                    GROUP BY mm.id, sm.id
                    ORDER BY mm.name, total_qty_consumed DESC"
                );

            case 3:
                return DB::select(
                    "SELECT
                        sm.category AS category,
                        sm.code AS material_code,
                        sm.name AS material_name,
                        sm.supplier,
                        COUNT(DISTINCT mo.id) AS models_using,
                        COUNT(DISTINCT mm.id) AS main_models_using,
                        COUNT(DISTINCT m.id) AS total_mrns,
                        SUM(md.qty) AS total_qty_consumed,
                        ROUND(SUM(md.qty * COALESCE(md.grn_price, sm.unit_price, 0)), 2) AS total_value_consumed,
                        ROUND(
                            SUM(md.qty) /
                            (SELECT SUM(qty) FROM mrn_details WHERE active = 1) * 100,
                        2) AS pct_of_all_consumption
                    FROM mrn_details md
                    JOIN mrns m ON m.id = md.mrn_id AND m.active = 1
                    JOIN batches b ON b.id = m.batch_id
                    JOIN models mo ON mo.id = b.model_id
                    JOIN main_models mm ON mm.id = mo.main_model_id
                    JOIN stock_materials sm ON sm.id = md.stock_item_id AND sm.active = 1
                    WHERE md.active = 1
                    GROUP BY sm.id
                    ORDER BY total_qty_consumed DESC
                    LIMIT 30"
                );

            case 4:
                return DB::select(
                    "SELECT
                        sm.code AS material_code,
                        sm.name AS material_name,
                        sm.category,
                        ROUND(SUM(CASE WHEN mm.name = 'N160' THEN md.qty ELSE 0 END), 2) AS N160_qty,
                        ROUND(SUM(CASE WHEN mm.name = 'CT100' THEN md.qty ELSE 0 END), 2) AS CT100_qty,
                        ROUND(SUM(CASE WHEN mm.name = 'Senaro GN125' THEN md.qty ELSE 0 END), 2) AS GN125_qty,
                        ROUND(SUM(CASE WHEN mm.name = 'Senaro Click' THEN md.qty ELSE 0 END), 2) AS SenaroClick_qty,
                        SUM(md.qty) AS grand_total_qty
                    FROM mrn_details md
                    JOIN mrns m ON m.id = md.mrn_id AND m.active = 1
                    JOIN batches b ON b.id = m.batch_id
                    JOIN models mo ON mo.id = b.model_id
                    JOIN main_models mm ON mm.id = mo.main_model_id
                    JOIN stock_materials sm ON sm.id = md.stock_item_id AND sm.active = 1
                    WHERE md.active = 1
                    GROUP BY sm.id
                    HAVING grand_total_qty > 0
                    ORDER BY grand_total_qty DESC"
                );

            case 5:
                return DB::select(
                    "SELECT
                        mm.name AS main_model,
                        b.batch_no AS batch,
                        mo.name AS component_model,
                        sm.category AS category,
                        sm.code AS material_code,
                        sm.name AS material_name,
                        COUNT(DISTINCT m.id) AS mrns,
                        SUM(md.qty) AS total_qty_consumed,
                        ROUND(SUM(md.qty * COALESCE(md.grn_price, sm.unit_price, 0)), 2) AS consumption_value,
                        ROUND(SUM(md.qty) / COUNT(DISTINCT m.id), 2) AS qty_per_mrn
                    FROM mrn_details md
                    JOIN mrns m ON m.id = md.mrn_id AND m.active = 1
                    JOIN batches b ON b.id = m.batch_id
                    JOIN models mo ON mo.id = b.model_id
                    JOIN main_models mm ON mm.id = mo.main_model_id
                    JOIN stock_materials sm ON sm.id = md.stock_item_id AND sm.active = 1
                    WHERE md.active = 1
                    GROUP BY mm.id, b.id, mo.id, sm.id
                    ORDER BY mm.name, b.batch_no, total_qty_consumed DESC"
                );

            default:
                return [];
        }
    }
}
