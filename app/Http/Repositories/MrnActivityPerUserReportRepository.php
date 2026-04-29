<?php

namespace App\Http\Repositories;

use Illuminate\Support\Facades\DB;

class MrnActivityPerUserReportRepository
{
    public function getReport(int $queryType): array
    {
        switch ($queryType) {
            case 1:
                return DB::select(
                    "SELECT
                        u.id AS user_id,
                        u.name AS user_name,
                        u.email,
                        r.role_code AS role,
                        COUNT(DISTINCT m_created.id) AS mrns_created,
                        COUNT(DISTINCT m_updated.id) AS mrns_processed,
                        COUNT(DISTINCT m_issued.id) AS mrns_issued_to,
                        COUNT(DISTINCT COALESCE(m_created.id, m_updated.id)) AS total_mrns_touched,
                        LEAST(
                            COALESCE(MIN(m_created.created_at), NOW()),
                            COALESCE(MIN(m_updated.updated_at), NOW())
                        ) AS first_activity,
                        GREATEST(
                            COALESCE(MAX(m_created.created_at), '1970-01-01'),
                            COALESCE(MAX(m_updated.updated_at), '1970-01-01')
                        ) AS last_activity
                    FROM users u
                    LEFT JOIN roles r ON r.id = u.role_id
                    LEFT JOIN mrns m_created ON m_created.created_by = u.id AND m_created.active = 1
                    LEFT JOIN mrns m_updated ON m_updated.updated_by = u.id AND m_updated.active = 1
                    LEFT JOIN mrns m_issued ON m_issued.issued_to = u.id AND m_issued.active = 1
                    WHERE u.is_active = 1
                    GROUP BY u.id, r.role_code
                    HAVING (mrns_created + mrns_processed + mrns_issued_to) > 0
                    ORDER BY mrns_created DESC, mrns_processed DESC"
                );

            case 2:
                return DB::select(
                    "SELECT
                        u.name AS user_name,
                        r.role_code AS role,
                        COUNT(m.id) AS total_created,
                        SUM(CASE WHEN m.status = 'complete' THEN 1 ELSE 0 END) AS completed,
                        SUM(CASE WHEN m.status = 'finalized' THEN 1 ELSE 0 END) AS finalized,
                        SUM(CASE WHEN m.status = 'processing' THEN 1 ELSE 0 END) AS processing,
                        SUM(CASE WHEN m.status = 'open' THEN 1 ELSE 0 END) AS open_mrns,
                        ROUND(
                            SUM(CASE WHEN m.status IN ('complete','finalized') THEN 1 ELSE 0 END)
                            / COUNT(m.id) * 100, 1
                        ) AS completion_rate_pct,
                        ROUND(AVG(
                            CASE
                                WHEN m.complete_at IS NOT NULL
                                THEN TIMESTAMPDIFF(MINUTE, m.created_at, m.complete_at)
                            END
                        ), 0) AS avg_completion_mins,
                        COUNT(DISTINCT DATE(m.created_at)) AS active_days
                    FROM mrns m
                    JOIN users u ON u.id = m.created_by
                    LEFT JOIN roles r ON r.id = u.role_id
                    WHERE m.active = 1
                    GROUP BY u.id, r.role_code
                    ORDER BY total_created DESC"
                );

            case 3:
                return DB::select(
                    "SELECT
                        DATE(m.created_at) AS activity_date,
                        u.name AS user_name,
                        r.role_code AS role,
                        COUNT(m.id) AS mrns_created,
                        SUM(CASE WHEN m.status IN ('complete','finalized') THEN 1 ELSE 0 END) AS mrns_completed,
                        SUM(CASE WHEN m.status = 'open' THEN 1 ELSE 0 END) AS mrns_still_open
                    FROM mrns m
                    JOIN users u ON u.id = m.created_by
                    LEFT JOIN roles r ON r.id = u.role_id
                    WHERE m.active = 1
                      AND m.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                    GROUP BY DATE(m.created_at), u.id, r.role_code
                    ORDER BY activity_date DESC, mrns_created DESC"
                );

            case 4:
                return DB::select(
                    "SELECT
                        u.name AS user_name,
                        r.role_code AS role,
                        COUNT(DISTINCT m.id) AS mrns_created,
                        COUNT(md.id) AS total_line_items,
                        ROUND(COUNT(md.id) / COUNT(DISTINCT m.id), 1) AS avg_lines_per_mrn,
                        ROUND(SUM(md.qty), 0) AS total_qty_issued,
                        ROUND(SUM(md.qty * md.grn_price), 2) AS total_stock_value_issued,
                        COUNT(DISTINCT DATE(m.created_at)) AS active_days,
                        ROUND(COUNT(md.id) / NULLIF(COUNT(DISTINCT DATE(m.created_at)), 0), 1) AS avg_lines_per_day
                    FROM mrns m
                    JOIN mrn_details md ON md.mrn_id = m.id AND md.active = 1
                    JOIN users u ON u.id = m.created_by
                    LEFT JOIN roles r ON r.id = u.role_id
                    WHERE m.active = 1
                    GROUP BY u.id, r.role_code
                    ORDER BY total_line_items DESC"
                );

            default:
                return [];
        }
    }
}
