<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\QcExclude;
use App\Http\Resources\QcExcludeResource;
use App\Http\Repositories\QcExcludeRepository;
use App\Http\Repositories\Utilities;
use Illuminate\Http\Request;

class QcExcludeController extends Controller
{
    private $repo;

    public function __construct()
    {
        $this->repo = new QcExcludeRepository();
    }

    public function index()
    {
        return QcExcludeResource::collection(QcExclude::paginate(25));
    }

    public function show($param)
    {
        $params = explode(":", $param);
        switch ($params[0]) {
            case "id":
                return $this->repo->show(QcExclude::findOrFail($params[1]));
                break;
            default:
                return response()->json(["status" => "error", "message" => "Server cannot decode the request."], 400);
        }
    }

    public function destroy(Request $request)
    {
        return Utilities::destroy(new QcExclude(), $request);
    }
}
