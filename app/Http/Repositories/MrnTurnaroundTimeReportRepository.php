<?php

namespace App\Http\Repositories;

use Illuminate\Support\Facades\DB;

class MrnTurnaroundTimeReportRepository
{
    public function getReport(int $queryType): array
    {
        switch ($queryType) {
            case 1:
                return DB::select(
                    "SELECT
                        m.id AS mrn_id,
                        b.batch_no AS batch,
                        mo.name AS model,
                        w.name AS warehouse,
                        m.status,
                        u_creator.name AS created_by,
                        u_updater.name AS completed_by,
                        m.created_at,
                        COALESCE(m.complete_at, m.finalized_at, m.updated_at) AS completed_at,
                        TIMESTAMPDIFF(MINUTE,
                            m.created_at,
                            COALESCE(m.complete_at, m.finalized_at, m.updated_at)
                        ) AS turnaround_mins,
                        ROUND(TIMESTAMPDIFF(MINUTE,
                            m.created_at,
                            COALESCE(m.complete_at, m.finalized_at, m.updated_at)
                        ) / 60.0, 2) AS turnaround_hrs,
                        CASE
                            WHEN TIMESTAMPDIFF(MINUTE, m.created_at,
                                 COALESCE(m.complete_at, m.finalized_at, m.updated_at)) = 0
                                THEN 'INSTANT (<1 min)'
                            WHEN TIMESTAMPDIFF(MINUTE, m.created_at,
                                 COALESCE(m.complete_at, m.finalized_at, m.updated_at)) <= 15
                                THEN 'FAST (<=15 min)'
                            WHEN TIMESTAMPDIFF(MINUTE, m.created_at,
                                 COALESCE(m.complete_at, m.finalized_at, m.updated_at)) <= 60
                                THEN 'NORMAL (<=1 hr)'
                            WHEN TIMESTAMPDIFF(MINUTE, m.created_at,
                                 COALESCE(m.complete_at, m.finalized_at, m.updated_at)) <= 240
                                THEN 'SLOW (1-4 hrs)'
                            ELSE 'DELAYED (>4 hrs)'
                        END AS speed_flag,
                        CASE
                            WHEN m.complete_at IS NOT NULL THEN 'complete_at'
                            WHEN m.finalized_at IS NOT NULL THEN 'finalized_at'
                            ELSE 'updated_at (fallback)'
                        END AS timestamp_source
                    FROM mrns m
                    JOIN batches b ON b.id = m.batch_id
                    JOIN models mo ON mo.id = b.model_id
                    JOIN warehouses w ON w.id = m.warehouse_id
                    LEFT JOIN users u_creator ON u_creator.id = m.created_by
                    LEFT JOIN users u_updater ON u_updater.id = m.updated_by
                    WHERE m.active = 1
                      AND m.status IN ('complete', 'finalized')
                    ORDER BY turnaround_mins DESC"
                );

            case 2:
                return DB::select(
                    "SELECT
                        u.name AS user_name,
                        r.role_code AS role,
                        COUNT(m.id) AS mrns_completed,
                        MIN(TIMESTAMPDIFF(MINUTE, m.created_at,
                            COALESCE(m.complete_at, m.finalized_at, m.updated_at))) AS fastest_mins,
                        MAX(TIMESTAMPDIFF(MINUTE, m.created_at,
                            COALESCE(m.complete_at, m.finalized_at, m.updated_at))) AS slowest_mins,
                        ROUND(AVG(TIMESTAMPDIFF(MINUTE, m.created_at,
                            COALESCE(m.complete_at, m.finalized_at, m.updated_at))), 1) AS avg_turnaround_mins,
                        ROUND(AVG(TIMESTAMPDIFF(MINUTE, m.created_at,
                            COALESCE(m.complete_at, m.finalized_at, m.updated_at))) / 60.0, 2) AS avg_turnaround_hrs,
                        SUM(CASE WHEN TIMESTAMPDIFF(MINUTE, m.created_at,
                                 COALESCE(m.complete_at, m.finalized_at, m.updated_at)) <= 15
                                 THEN 1 ELSE 0 END) AS fast_count,
                        SUM(CASE WHEN TIMESTAMPDIFF(MINUTE, m.created_at,
                                 COALESCE(m.complete_at, m.finalized_at, m.updated_at))
                                 BETWEEN 16 AND 60 THEN 1 ELSE 0 END) AS normal_count,
                        SUM(CASE WHEN TIMESTAMPDIFF(MINUTE, m.created_at,
                                 COALESCE(m.complete_at, m.finalized_at, m.updated_at)) > 60
                                 THEN 1 ELSE 0 END) AS slow_count
                    FROM mrns m
                    JOIN users u ON u.id = m.created_by
                    LEFT JOIN roles r ON r.id = u.role_id
                    WHERE m.active = 1
                      AND m.status IN ('complete', 'finalized')
                    GROUP BY u.id, r.role_code
                    ORDER BY avg_turnaround_mins ASC"
                );

            case 3:
                return DB::select(
                    "SELECT
                        mo.name AS model,
                        b.batch_no AS batch,
                        COUNT(m.id) AS mrns_completed,
                        ROUND(AVG(TIMESTAMPDIFF(MINUTE, m.created_at,
                            COALESCE(m.complete_at, m.finalized_at, m.updated_at))), 1) AS avg_turnaround_mins,
                        MIN(TIMESTAMPDIFF(MINUTE, m.created_at,
                            COALESCE(m.complete_at, m.finalized_at, m.updated_at))) AS fastest_mins,
                        MAX(TIMESTAMPDIFF(MINUTE, m.created_at,
                            COALESCE(m.complete_at, m.finalized_at, m.updated_at))) AS slowest_mins,
                        ROUND(SUBSTRING_INDEX(
                            SUBSTRING_INDEX(
                                GROUP_CONCAT(
                                    TIMESTAMPDIFF(MINUTE, m.created_at,
                                        COALESCE(m.complete_at, m.finalized_at, m.updated_at))
                                    ORDER BY TIMESTAMPDIFF(MINUTE, m.created_at,
                                        COALESCE(m.complete_at, m.finalized_at, m.updated_at))
                                ),
                                ',', FLOOR(COUNT(*)/2) + 1
                            ), ',', -1
                        ), 0) AS median_turnaround_mins
                    FROM mrns m
                    JOIN batches b ON b.id = m.batch_id
                    JOIN models mo ON mo.id = b.model_id
                    WHERE m.active = 1
                      AND m.status IN ('complete', 'finalized')
                    GROUP BY mo.id, b.id
                    ORDER BY avg_turnaround_mins DESC"
                );

            case 6:
                return DB::select(
                    "SELECT
                        m.id AS mrn_id,
                        b.batch_no AS batch,
                        mo.name AS model,
                        m.status,
                        u.name AS created_by,
                        m.created_at,
                        NOW() AS checked_at,
                        TIMESTAMPDIFF(MINUTE, m.created_at, NOW()) AS mins_elapsed,
                        ROUND(TIMESTAMPDIFF(MINUTE, m.created_at, NOW()) / 60.0, 1) AS hrs_elapsed,
                        TIMESTAMPDIFF(DAY, m.created_at, NOW()) AS days_elapsed,
                        CASE
                            WHEN TIMESTAMPDIFF(DAY, m.created_at, NOW()) = 0 THEN 'Today'
                            WHEN TIMESTAMPDIFF(DAY, m.created_at, NOW()) = 1 THEN 'Yesterday'
                            ELSE CONCAT(TIMESTAMPDIFF(DAY, m.created_at, NOW()), ' days old')
                        END AS age_flag
                    FROM mrns m
                    JOIN batches b ON b.id = m.batch_id
                    JOIN models mo ON mo.id = b.model_id
                    LEFT JOIN users u ON u.id = m.created_by
                    WHERE m.active = 1
                      AND m.status IN ('open', 'processing')
                    ORDER BY m.created_at ASC"
                );

            default:
                return [];
        }
    }
}
