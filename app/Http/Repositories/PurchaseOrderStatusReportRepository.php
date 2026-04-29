<?php

namespace App\Http\Repositories;

use Illuminate\Support\Facades\DB;

class PurchaseOrderStatusReportRepository
{
    public function getReport(int $queryType): array
    {
        switch ($queryType) {
            case 1:
                return DB::select(
                    "SELECT
                        po.id AS po_id,
                        po.po_number,
                        s.name AS supplier,
                        s.contact_no AS supplier_contact,
                        po.status,
                        po.order_date,
                        po.expected_delivery_date,
                        CASE
                            WHEN po.status = 'RECEIVED' THEN NULL
                            WHEN po.status = 'CANCELLED' THEN NULL
                            ELSE DATEDIFF(po.expected_delivery_date, CURDATE())
                        END AS days_to_delivery,
                        CASE
                            WHEN po.status NOT IN ('RECEIVED', 'CANCELLED')
                             AND po.expected_delivery_date < CURDATE() THEN 'OVERDUE'
                            WHEN po.status NOT IN ('RECEIVED', 'CANCELLED')
                             AND DATEDIFF(po.expected_delivery_date, CURDATE()) <= 3 THEN 'DUE SOON'
                            WHEN po.status = 'RECEIVED' THEN 'RECEIVED'
                            WHEN po.status = 'CANCELLED' THEN 'CANCELLED'
                            ELSE 'IN PROGRESS'
                        END AS delivery_flag,
                        po.subtotal,
                        po.discount,
                        po.tax,
                        po.shipping_cost,
                        po.total_amount,
                        COUNT(poi.id) AS line_items,
                        SUM(poi.quantity) AS total_qty_ordered,
                        u.name AS created_by,
                        po.created_at,
                        po.updated_at AS last_updated,
                        po.notes
                    FROM purchase_orders po
                    JOIN suppliers s ON s.id = po.supplier_id
                    LEFT JOIN purchase_order_items poi ON poi.purchase_order_id = po.id
                    LEFT JOIN users u ON u.id = po.created_by
                    GROUP BY po.id, s.id, u.id
                    ORDER BY
                        FIELD(po.status, 'SENT', 'APPROVED', 'DRAFT', 'RECEIVED', 'CANCELLED'),
                        po.expected_delivery_date ASC"
                );

            case 2:
                return DB::select(
                    "SELECT
                        po.po_number,
                        po.status AS po_status,
                        s.name AS supplier,
                        po.order_date,
                        po.expected_delivery_date AS po_delivery_date,
                        poi.id AS line_id,
                        sm.code AS material_code,
                        sm.name AS material_name,
                        sm.category AS material_category,
                        poi.quantity AS qty_ordered,
                        poi.unit_price,
                        poi.total AS line_total,
                        poi.expected_delivery_date AS line_delivery_date,
                        CASE
                            WHEN EXISTS (
                                SELECT 1
                                FROM grn_details gd
                                JOIN whl_items wi ON wi.id = gd.whl_item_id
                                WHERE wi.stock_item_id = sm.id
                                  AND gd.created_at >= po.order_date
                            ) THEN 'GRN Exists'
                            ELSE 'Awaiting GRN'
                        END AS grn_status
                    FROM purchase_orders po
                    JOIN suppliers s ON s.id = po.supplier_id
                    JOIN purchase_order_items poi ON poi.purchase_order_id = po.id
                    JOIN stock_materials sm ON sm.id = poi.material_id
                    ORDER BY
                        FIELD(po.status, 'SENT', 'APPROVED', 'DRAFT', 'RECEIVED', 'CANCELLED'),
                        po.po_number,
                        sm.category,
                        sm.code"
                );

            case 6:
                return DB::select(
                    "SELECT
                        po.po_number,
                        po.status AS po_status,
                        s.name AS supplier,
                        sm.code AS material_code,
                        sm.name AS material_name,
                        poi.quantity AS qty_ordered,
                        poi.unit_price AS po_unit_price,
                        COALESCE(grn_received.total_received, 0) AS qty_received_via_grn,
                        GREATEST(0, poi.quantity - COALESCE(grn_received.total_received, 0)) AS qty_outstanding,
                        CASE
                            WHEN COALESCE(grn_received.total_received, 0) = 0 THEN 'Not Received'
                            WHEN grn_received.total_received >= poi.quantity THEN 'Fully Received'
                            ELSE 'Partially Received'
                        END AS receipt_status,
                        poi.expected_delivery_date
                    FROM purchase_orders po
                    JOIN suppliers s ON s.id = po.supplier_id
                    JOIN purchase_order_items poi ON poi.purchase_order_id = po.id
                    JOIN stock_materials sm ON sm.id = poi.material_id
                    LEFT JOIN (
                        SELECT
                            wi.stock_item_id,
                            SUM(gd.qty) AS total_received,
                            MIN(g.created_at) AS first_received_at
                        FROM grn_details gd
                        JOIN whl_items wi ON wi.id = gd.whl_item_id
                        JOIN grns g ON g.id = gd.grn_id
                        WHERE gd.active = 1
                          AND g.active = 1
                        GROUP BY wi.stock_item_id
                    ) grn_received ON grn_received.stock_item_id = sm.id
                    ORDER BY
                        FIELD(po.status, 'SENT', 'APPROVED', 'DRAFT', 'RECEIVED', 'CANCELLED'),
                        po.po_number,
                        receipt_status"
                );

            default:
                return [];
        }
    }
}
