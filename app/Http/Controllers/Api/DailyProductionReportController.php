<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Repositories\DailyProductionReportRepository;
use Illuminate\Http\Request;

class DailyProductionReportController extends Controller
{
    private DailyProductionReportRepository $repo;

    public function __construct(DailyProductionReportRepository $repo)
    {
        $this->repo = $repo;
    }

    public function index(Request $request)
    {
        $validated = $request->validate([
            'query_type' => 'required|integer|in:1,2,3,4,5',
            'from_date' => 'nullable|date_format:Y-m-d',
            'to_date' => 'nullable|date_format:Y-m-d',
        ]);

        if ((int) $validated['query_type'] === 4 && (empty($validated['from_date']) || empty($validated['to_date']))) {
            return response()->json([
                'message' => 'from_date and to_date are required when query_type=4',
            ], 422);
        }

        $data = $this->repo->getReport(
            (int) $validated['query_type'],
            $validated['from_date'] ?? null,
            $validated['to_date'] ?? null
        );

        return response()->json([
            'query_type' => (int) $validated['query_type'],
            'from_date' => $validated['from_date'] ?? null,
            'to_date' => $validated['to_date'] ?? null,
            'count' => count($data),
            'data' => $data,
        ]);
    }

    public function download(Request $request)
    {
        $validated = $request->validate([
            'query_type' => 'required|integer|in:1,2,3,4,5',
            'from_date' => 'nullable|date_format:Y-m-d',
            'to_date' => 'nullable|date_format:Y-m-d',
        ]);

        if ((int) $validated['query_type'] === 4 && (empty($validated['from_date']) || empty($validated['to_date']))) {
            return response()->json([
                'message' => 'from_date and to_date are required when query_type=4',
            ], 422);
        }

        $rows = $this->repo->getReport(
            (int) $validated['query_type'],
            $validated['from_date'] ?? null,
            $validated['to_date'] ?? null
        );

        $fileName = 'daily_production_q' . $validated['query_type'] . '_' . now()->format('Ymd_His') . '.csv';

        return response()->streamDownload(function () use ($rows) {
            $handle = fopen('php://output', 'w');

            if (!empty($rows)) {
                fputcsv($handle, array_keys((array) $rows[0]));
                foreach ($rows as $row) {
                    fputcsv($handle, (array) $row);
                }
            }

            fclose($handle);
        }, $fileName, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }
}