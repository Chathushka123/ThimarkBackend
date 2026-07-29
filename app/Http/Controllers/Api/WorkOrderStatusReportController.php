<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Repositories\WorkOrderStatusReportRepository;
use Illuminate\Http\Request;

class WorkOrderStatusReportController extends Controller
{
    private WorkOrderStatusReportRepository $repo;

    public function __construct(WorkOrderStatusReportRepository $repo)
    {
        $this->repo = $repo;
    }

    public function index(Request $request)
    {
        $validated = $request->validate([
            'status' => 'nullable|string|in:OPEN,FINALIZED,COMPLETED',
            'from_date' => 'nullable|date',
            'to_date' => 'nullable|date',
        ]);

        $report = $this->repo->getReport(
            $validated['status'] ?? null,
            $validated['from_date'] ?? null,
            $validated['to_date'] ?? null
        );

        return response()->json([
            'status' => $validated['status'] ?? null,
            'from_date' => $validated['from_date'] ?? null,
            'to_date' => $validated['to_date'] ?? null,
            'count' => count($report['data']),
            'operations' => $report['operations'],
            'data' => $report['data'],
        ]);
    }

    public function download(Request $request)
    {
        $validated = $request->validate([
            'status' => 'nullable|string|in:OPEN,FINALIZED,COMPLETED',
            'from_date' => 'nullable|date',
            'to_date' => 'nullable|date',
        ]);

        $report = $this->repo->getReport(
            $validated['status'] ?? null,
            $validated['from_date'] ?? null,
            $validated['to_date'] ?? null
        );
        $rows = $report['data'];
        $fileName = 'work_order_status_' . now()->format('Ymd_His') . '.csv';

        return response()->streamDownload(function () use ($rows) {
            $handle = fopen('php://output', 'w');

            if (!empty($rows)) {
                fputcsv($handle, array_keys($rows[0]));
                foreach ($rows as $row) {
                    fputcsv($handle, $row);
                }
            }

            fclose($handle);
        }, $fileName, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }
}
