<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Repositories\TrollyMasterRepository;
use App\TrollyMaster;
use Illuminate\Http\Request;
use PDF;

class TrollyMasterController extends Controller
{
    private $repo;

    public function __construct()
    {
        $this->repo = new TrollyMasterRepository();
    }

    public function getAll(Request $request)
    {
        return $this->repo->getAll();
    }

    public function getUnusedTrolly(Request $request)
    {
        return $this->repo->getUnusedTrolly();
    }

    public function getOne(Request $request, $id)
    {
        return $this->repo->getOne($id);
    }

    public function createAndUpdate(Request $request)
    {
        return $this->repo->createAndUpdate($request);
    }

    public function delete(Request $request)
    {
        return $this->repo->delete($request);
    }

    /**
     * Print stickers (QR + id/code/name) for the given comma-separated
     * trolley ids - 3 per row, 18 per A4 page.
     */
    public function printStickersByIds($ids)
    {
        $idArray = explode(',', $ids);
        $trolleys = TrollyMaster::whereIn('id', $idArray)->where('active', true)->orderBy('code')->get();
        $pdf = PDF::loadView('print.trolly_stickers', ['trolleys' => $trolleys]);
        $pdf->setPaper('A4', 'portrait');
        return $pdf->stream('trolly_stickers_' . date('Y_m_d_H_i_s') . '.pdf');
    }
}
