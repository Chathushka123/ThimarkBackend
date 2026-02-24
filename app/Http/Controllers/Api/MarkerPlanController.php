<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Repositories\MarkerPlanRepository;
use Illuminate\Http\Request;

class MarkerPlanController extends Controller
{

    private $repo;

    public function __construct()
    {
        $this->repo = new MarkerPlanRepository();
    }

    public function getSearchByStyleFabric(Request $request){
        $style_code = $request->input('style_code');
        $description = $request->input('description');
        $fabric = $request->input('fabric');

        return $this->repo->getSearchByStyleFabric($style_code,$description,$fabric);
    }

    public function generateMarkerPlan(Request $request){
        return $this->repo->generateMarkerPlan($request);
    }

}

