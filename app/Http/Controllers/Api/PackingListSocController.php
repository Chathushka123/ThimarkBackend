<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\PackingListSoc;
use App\Http\Resources\PackingListSocResource;
use App\Http\Repositories\PackingListSocRepository;
use App\Http\Repositories\Utilities;
use Illuminate\Http\Request;

class PackingListSocController extends Controller
{
    private $repo;

    public function __construct()
    {
        $this->repo = new PackingListSocRepository();
    }

    public function getSearchByPackingListSoc(Request $request)
    {
        $buyer_id = $request->buyer_id;
        $customer_style_ref = $request->customer_style_ref;
        return $this->repo->getSearchByPackingListSoc($buyer_id, $customer_style_ref);
    }

    public function getSearchResultsByPackingListSoc(Request $request)
    {
        $buyer_id = $request->buyer_id;
        $customer_style_ref = $request->customer_style_ref;
        $soc_no = $request->soc_no;
        return $this->repo->getSearchResultsByPackingListSoc($buyer_id, $customer_style_ref, $soc_no);
    }

    public function getPackingListSocQuantities(Request $request)
    {
        $soc_id = $request->soc_id;
        $packing_list_id = $request->packing_list_id;
        return $this->repo->getPackingListSocQuantities($soc_id,$packing_list_id);
    }





}
