<?php

namespace App\Exports;

use App\Bulk;
use Maatwebsite\Excel\Concerns\FromCollection;

class BulkExport implements FromCollection
{
    /**
    * @return \Illuminate\Support\Collection
    */
    public function collection()
    {
        return Bulk::all();
    }
}
