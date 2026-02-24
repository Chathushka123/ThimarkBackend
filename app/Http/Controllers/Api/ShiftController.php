<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Shift;
use App\Http\Resources\ShiftResource;
use App\Http\Repositories\ShiftRepository;
use App\Http\Repositories\Utilities;
use Illuminate\Http\Request;

class ShiftController extends Controller
{
    private $repo;

    public function __construct()
    {
        $this->repo = new ShiftRepository();
    }

    public function index()
    {
        return ShiftResource::collection(Shift::paginate(25));
    }

    public function show($param)
    {
        $params = explode(":", $param);
        switch ($params[0]) {
            case "id":
                return $this->repo->show(Shift::findOrFail($params[1]));
                break;
                // case "key":
                //     return $this->repo->show(Shift::where('wfx_oc_no', $params[1])->firstOrFail());
                //     break;
            default:
                return response()->json(["status" => "error", "message" => "Server cannot decode the request."], 400);
        }
    }

    public function destroy(Request $request)
    {
        return Utilities::destroy(new Shift(), $request);
    }
}
