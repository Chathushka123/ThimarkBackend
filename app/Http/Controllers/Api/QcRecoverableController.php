<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\QcRecoverable;
use App\Http\Resources\QcRecoverableResource;
use App\Http\Repositories\QcRecoverableRepository;
use App\Http\Repositories\Utilities;
use Illuminate\Http\Request;

class QcRecoverableController extends Controller
{
    private $repo;

    public function __construct()
    {
        $this->repo = new QcRecoverableRepository();
    }

    public function index()
    {
        return QcRecoverableResource::collection(QcRecoverable::paginate(25));
    }

    public function show($param)
    {
        $params = explode(":", $param);
        switch ($params[0]) {
            case "id":
                return $this->repo->show(QcRecoverable::findOrFail($params[1]));
                break;
                // case "key":
                //     return $this->repo->show(BundleBin::where('wfx_oc_no', $params[1])->firstOrFail());
                //     break;
            default:
                return response()->json(["status" => "error", "message" => "Server cannot decode the request."], 400);
        }
    }
}
