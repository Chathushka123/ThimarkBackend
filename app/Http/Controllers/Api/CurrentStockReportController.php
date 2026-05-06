<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Repositories\CurrentStockReportRepository;
use Illuminate\Http\Request;

class CurrentStockReportController extends Controller
{
    private CurrentStockReportRepository $repo;

    public function __construct(CurrentStockReportRepository $repo)
    {
        $this->repo = $repo;
    }

    public function index(Request $request)
    {
        $validated = $request->validate([
            'query_type' => 'required|integer|in:1,2,3,4',
        ]);

        $data = $this->repo->getReport((int) $validated['query_type']);

        return response()->json([
            'query_type' => (int) $validated['query_type'],
            'count' => count($data),
            'data' => $data,
        ]);
    }

    public function download(Request $request)
    {
        $validated = $request->validate([
            'query_type' => 'required|integer|in:1,2,3,4',
        ]);

        $rows = $this->repo->getReport((int) $validated['query_type']);
        $fileName = 'current_stock_q' . $validated['query_type'] . '_' . now()->format('Ymd_His') . '.csv';

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
