<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Repositories\RoutingRepository;
use App\Routing;
use Illuminate\Http\Request;
use Exception;
use App\Http\Resources\RoutingResource;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class RoutingController extends Controller
{
    private $repo;

    public function __construct()
    {
        $this->repo = new RoutingRepository();
    }
    

    public function index()
    {
        return RoutingResource::collection(Routing::paginate(25));
    }

    public function getFullStructure(Request $request)
    {
        $ret =  $this->repo::getFullStructure($request->routing_id);
        return response()->json(["status" => "success", "data" => $ret], 200);
    }



}
