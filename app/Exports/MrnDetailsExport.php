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
            $createdAt = $this->splitDateTime($row->CreatedAt);
            $issuedAt = $this->splitDateTime($row->UpdatedAt);

            return [
                'code' => $row->code,
                'name' => $row->name,
                'MRN_Status' => $row->MRNStatus,
                'Req_Qty' => $row->Req_Qty,
                'IssuedQty' => $row->IssuedQty,
                'IssuedTo' => $row->IssuedTo,
                'CreatedBy' => $row->CreatedBy,
                'UpdatedBy' => $row->UpdatedBy,
                'CreatedDate' => $createdAt['date'],
                'CreatedTime' => $createdAt['time'],
                'IssuedDate' => $issuedAt['date'],
                'IssuedTime' => $issuedAt['time'],
            ];
        });
    }

    protected function splitDateTime($value): array
    {
        if (empty($value)) {
            return [
                'date' => '',
                'time' => '',
            ];
        }

        $parts = explode(' ', (string) $value, 2);

        return [
            'date' => $parts[0] ?? '',
            'time' => $parts[1] ?? '',
        ];
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
            'CreatedDate',
            'CreatedTime',
            'IssuedDate',
            'IssuedTime',
        ];
    }
}
