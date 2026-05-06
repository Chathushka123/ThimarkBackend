<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Repositories\GrnReportRepository;
use Illuminate\Http\Request;

class GrnReportController extends Controller
{
    private GrnReportRepository $repo;

    public function __construct(GrnReportRepository $repo)
    {
        $this->repo = $repo;
    }

    public function index(Request $request)
    {
        $fromDate = $request->input('from_date', $request->input('params.from_date'));
        $toDate = $request->input('to_date', $request->input('params.to_date'));

        $validated = validator(
            [
                'from_date' => $fromDate,
                'to_date' => $toDate,
            ],
            [
                'from_date' => 'required|date',
                'to_date' => 'required|date|after_or_equal:from_date',
            ]
        )->validate();

        $data = $this->repo->getReport($validated['from_date'], $validated['to_date']);

        return response()->json([
            'from_date' => $validated['from_date'],
            'to_date' => $validated['to_date'],
            'count' => count($data),
            'data' => $data,
        ]);
    }

    public function download(Request $request)
    {
        $fromDate = $request->input('from_date', $request->input('params.from_date'));
        $toDate = $request->input('to_date', $request->input('params.to_date'));

        $validated = validator(
            [
                'from_date' => $fromDate,
                'to_date' => $toDate,
            ],
            [
                'from_date' => 'required|date',
                'to_date' => 'required|date|after_or_equal:from_date',
            ]
        )->validate();

        $rows = $this->repo->getReport($validated['from_date'], $validated['to_date']);
        $fileName = 'grn_report_' . $validated['from_date'] . '_to_' . $validated['to_date'] . '_' . now()->format('Ymd_His') . '.csv';

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
