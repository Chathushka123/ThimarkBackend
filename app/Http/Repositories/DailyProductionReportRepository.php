<?php

namespace App\Http\Repositories;

use Illuminate\Support\Facades\DB;

class DailyProductionReportRepository
{
    public function getReport(int $queryType, ?string $fromDate = null, ?string $toDate = null): array
    {
        switch ($queryType) {
            case 1:
                return DB::select(
                    "SELECT
                        DATE(m.created_at) AS production_date,
                        b.batch_no AS batch,
                        mo.name AS model,
                        COUNT(m.id) AS total_mrns,
                        SUM(CASE WHEN m.status = 'complete' THEN 1 ELSE 0 END) AS completed,
                        SUM(CASE WHEN m.status = 'finalized' THEN 1 ELSE 0 END) AS finalized,
                        SUM(CASE WHEN m.status = 'processing' THEN 1 ELSE 0 END) AS processing,
                        SUM(CASE WHEN m.status = 'open' THEN 1 ELSE 0 END) AS open_mrns,
                        ROUND(
                            SUM(CASE WHEN m.status = 'complete' THEN 1 ELSE 0 END)
                            / COUNT(m.id) * 100, 1
                        ) AS completion_pct
                    FROM mrns m
                    JOIN batches b ON b.id = m.batch_id
                    JOIN models mo ON mo.id = b.model_id
                    WHERE m.active = 1
                    GROUP BY DATE(m.created_at), b.id, mo.id
                    ORDER BY production_date DESC, total_mrns DESC"
                );

            case 2:
                return DB::select(
                    "SELECT
                        DATE(m.created_at) AS production_date,
                        b.batch_no AS batch,
                        mo.name AS model,
                        u.name AS created_by,
                        COUNT(m.id) AS total_mrns,
                        SUM(CASE WHEN m.status = 'complete' THEN 1 ELSE 0 END) AS completed,
                        SUM(CASE WHEN m.status = 'finalized' THEN 1 ELSE 0 END) AS finalized,
                        SUM(CASE WHEN m.status = 'open' THEN 1 ELSE 0 END) AS open_mrns
                    FROM mrns m
                    JOIN batches b ON b.id = m.batch_id
                    JOIN models mo ON mo.id = b.model_id
                    LEFT JOIN users u ON u.id = m.created_by
                    WHERE m.active = 1
                    GROUP BY DATE(m.created_at), b.id, mo.id, u.id
                    ORDER BY production_date DESC, total_mrns DESC"
                );

            case 3:
                return DB::select(
                    "SELECT
                        DATE(m.created_at) AS production_date,
                        COUNT(m.id) AS total_mrns,
                        SUM(CASE WHEN m.status = 'complete' THEN 1 ELSE 0 END) AS completed,
                        SUM(CASE WHEN m.status = 'finalized' THEN 1 ELSE 0 END) AS finalized,
                        SUM(CASE WHEN m.status = 'open' THEN 1 ELSE 0 END) AS open_mrns,
                        COUNT(DISTINCT m.batch_id) AS batches_active,
                        COUNT(DISTINCT b.model_id) AS models_in_production
                    FROM mrns m
                    JOIN batches b ON b.id = m.batch_id
                    WHERE m.active = 1
                    GROUP BY DATE(m.created_at)
                    ORDER BY production_date DESC"
                );

            case 4:
                return DB::select(
                    "SELECT
                        DATE(m.created_at) AS production_date,
                        b.batch_no AS batch,
                        mo.name AS model,
                        COUNT(m.id) AS total_mrns,
                        SUM(CASE WHEN m.status = 'complete' THEN 1 ELSE 0 END) AS completed,
                        SUM(CASE WHEN m.status = 'open' THEN 1 ELSE 0 END) AS open_mrns,
                        MIN(m.created_at) AS first_mrn_at,
                        MAX(m.created_at) AS last_mrn_at
                    FROM mrns m
                    JOIN batches b ON b.id = m.batch_id
                    JOIN models mo ON mo.id = b.model_id
                    WHERE m.active = 1
                      AND DATE(m.created_at) BETWEEN ? AND ?
                    GROUP BY DATE(m.created_at), b.id, mo.id
                    ORDER BY production_date DESC, total_mrns DESC",
                    [$fromDate, $toDate]
                );

            case 5:
                return DB::select(
                    "SELECT
                        DATE(m.created_at) AS production_date,
                        w.name AS warehouse,
                        b.batch_no AS batch,
                        COUNT(m.id) AS total_mrns,
                        SUM(CASE WHEN m.status = 'complete' THEN 1 ELSE 0 END) AS completed
                    FROM mrns m
                    JOIN batches b ON b.id = m.batch_id
                    JOIN warehouses w ON w.id = m.warehouse_id
                    WHERE m.active = 1
                    GROUP BY DATE(m.created_at), w.id, b.id
                    ORDER BY production_date DESC, warehouse, total_mrns DESC"
                );

            default:
                return [];
        }
    }
}