<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use PDF;

class QrCodeController extends Controller
{
    public function index($data1)
    {
        $data = ['rollData' => $data1];
        $customPaper = array(1, 1, 144.00, 288.00);
        $pdf = PDF::loadView('print.tabsticker', $data);
        $pdf->setPaper($customPaper, 'landscape');
        return $pdf->stream('tab_stickers_' . date('Y_m_d_H_i_s') . '.pdf');
    }

    public function yarnSticker($data1)
    {
        $data = ['yarnData' => $data1];
        $pdf = PDF::loadView('print.yarn_sticker', $data);
//        $pdf->setPaper('A4', 'portrait');
        return $pdf->stream('yarn_stickers_' . date('Y_m_d_H_i_s') . '.pdf');
    }
}
