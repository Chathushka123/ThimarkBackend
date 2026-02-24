<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\QcReject;
use App\Http\Resources\QcRejectResource;
use App\Http\Repositories\QcRejectRepository;
use App\Http\Repositories\Utilities;
use Illuminate\Http\Request;

class QcRejectController extends Controller
{
    private $repo;

    public function __construct()
    {
        $this->repo = new QcRejectRepository();
    }

    public function index()
    {
        return QcRejectResource::collection(QcReject::paginate(25));
    }

    public function show($param)
    {
        $params = explode(":", $param);
        switch ($params[0]) {
            case "id":
                return $this->repo->show(QcReject::findOrFail($params[1]));
                break;
                // case "key":
                //     return $this->repo->show(Team::where('wfx_oc_no', $params[1])->firstOrFail());
                //     break;
            default:
                return response()->json(["status" => "error", "message" => "Server cannot decode the request."], 400);
        }
    }

    public function destroy(Request $request)
    {
        return Utilities::destroy(new QcReject(), $request);
    }
}
