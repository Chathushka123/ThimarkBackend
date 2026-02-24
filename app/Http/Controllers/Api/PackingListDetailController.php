<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\PackingListDetail;
use App\Http\Resources\PackingListDetailResource;
use App\Http\Repositories\PackingListDetailRepository;
use App\Http\Repositories\Utilities;
use Illuminate\Http\Request;

class PackingListDetailController extends Controller
{
    private $repo;

    public function __construct()
    {
        $this->repo = new PackingListDetailRepository();
    }

    public function getFullDetails(Request $request)
    {
        $packing_list_id = $request->packing_list_id;
        return $this->repo->getFullDetails($packing_list_id);
    }

    public function deleteDetailsByPackingList(Request $request)
    {
        $packing_list_id = $request->packing_list_id;
        return $this->repo->deleteDetailsByPackingList($packing_list_id);
    }

    public function getPackingListSocByCarton(Request $request)
    {        
        $carton_no = $request->carton_no;
        $packing_list_id = $request->packing_list_id;
        $carton_packing_list_id = $request->carton_packing_list_id;
        return $this->repo->getPackingListSocByCarton($carton_no, $packing_list_id, $carton_packing_list_id);
    }

    public function editPackingCartonQty(Request $request)
    {
        return $this->repo->editPackingCartonQty($request);
    }

    public function deleteCarton(Request $request)
    {        
        $carton_no = $request->carton_no;
        $packing_list_id = $request->packing_list_id;
        $carton_packing_list_id = $request->carton_packing_list_id;
        return $this->repo->deleteCarton($carton_no, $packing_list_id, $carton_packing_list_id);
    }

    public function getPackingListStickers(Request $request){
        $packing_list_id = $request->packing_list_id;
        return $this->repo->getPackingListStickers($packing_list_id);        
    }

}
