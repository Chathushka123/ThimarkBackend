<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\RecoverableScan;
use App\Http\Resources\RecoverableScanResource;
use App\Http\Repositories\RecoverableScanRepository;
use App\Http\Repositories\Utilities;
use Illuminate\Http\Request;

class RecoverableScanController extends Controller
{
    private $repo;

    public function __construct()
    {
        $this->repo = new RecoverableScanRepository();
    }

    public function index()
    {
        return RecoverableScanResource::collection(RecoverableScan::paginate(25));
    }

    public function show($param)
    {
        $params = explode(":", $param);
        switch ($params[0]) {
            case "id":
                return $this->repo->show(RecoverableScan::findOrFail($params[1]));
                break;
            default:
                return response()->json(["status" => "error", "message" => "Server cannot decode the request."], 400);
        }
    }

    public function destroy(Request $request)
    {
        return Utilities::destroy(new RecoverableScan(), $request);
    }
}
