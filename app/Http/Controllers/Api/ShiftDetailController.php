<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\ShiftDetail;
use App\Http\Resources\ShiftDetailResource;
use App\Http\Repositories\ShiftDetailRepository;
use App\Http\Repositories\Utilities;
use Illuminate\Http\Request;

class ShiftDetailController extends Controller
{
    private $repo;

    public function __construct()
    {
        $this->repo = new ShiftDetailRepository();
    }

    public function index()
    {
        return ShiftDetailResource::collection(ShiftDetail::paginate(25));
    }

    public function show($param)
    {
        $params = explode(":", $param);
        switch ($params[0]) {
            case "id":
                return $this->repo->show(ShiftDetail::findOrFail($params[1]));
                break;
                // case "key":
                //     return $this->repo->show(ShiftDetail::where('wfx_oc_no', $params[1])->firstOrFail());
                //     break;
            default:
                return response()->json(["status" => "error", "message" => "Server cannot decode the request."], 400);
        }
    }

    public function destroy(Request $request)
    {
        return Utilities::destroy(new ShiftDetail(), $request);
    }
}
