<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;

class MrnDetailsExport implements FromCollection, WithHeadings, ShouldAutoSize
{
    protected $rows;

    public function __construct(Collection $rows)
    {
        $this->rows = $rows;
    }

    public function collection()
    {
        return $this->rows->map(function ($row) {
            return [
                'code' => $row->code,
                'name' => $row->name,
                'MRN_Status' => $row->MRNStatus,
                'Req_Qty' => $row->Req_Qty,
                'IssuedQty' => $row->IssuedQty,
                'IssuedTo' => $row->IssuedTo,
                'CreatedBy' => $row->CreatedBy,
                'UpdatedBy' => $row->UpdatedBy,
                'CreatedAt' => $row->CreatedAt,
                'IssuedAt' => $row->UpdatedAt,
            ];
        });
    }

    public function headings(): array
    {
        return [
            'Code',
            'Name',
            'MRN_Status',
            'Req_Qty',
            'IssuedQty',
            'IssuedTo',
            'CreatedBy',
            'UpdatedBy',
            'CreatedAt',
            'IssuedAt',
        ];
    }
}
