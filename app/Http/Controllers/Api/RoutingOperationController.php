<?php

namespace App\Http\Controllers\Api;

use App\RoutingOperation;
use App\Http\Controllers\Controller;
use App\Http\Repositories\RoutingOperationRepository;
use Illuminate\Http\Request;
use Exception;
use App\Http\Resources\RoutingOperationResource;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;

class RoutingOperationController extends Controller
{
  private $repo;

  public function __construct()
  {
    $this->repo = new RoutingOperationRepository();
  }

  public function index()
  {
    return RoutingOperationResource::collection(RoutingOperation::paginate(25));
  }

  public function addOperation(Request $request)
  {
    try {
      $rec = [];
      $rec['operation_code'] = $request->operation_code;
      $rec['description'] = $request->description;
      $rec['smv'] = $request->smv;
      $rec['base_operation_id'] = $request->base_operation_id;
      $rec['level'] = $request->level;
      $rec['child'] = $request->child;
      $rec['parallel'] = $request->parallel;
      $rec['position'] = $request->position;
      $rec['wip_point'] = $request->wip_point;
      $rec['print_bundle'] = $request->print_bundle;
      $rec['in'] = $request->in;
      $rec['out'] = $request->out;
      $rec['routing_id'] = $request->routing_id;
      $ret = $this->repo::addOperation($rec);
      return response()->json(["status" => "success", "data" => $ret], 200);
    } catch (Exception $e) {
      return response()->json(["status" => "error", "message" => ($e->getMessage()), "trace" => $e->getTraceAsString()], 400);
    }
  }

  public function removeOperation(Request $request)
  {
    try {
      $rec = [];
      $rec['id'] = $request->id;
      $this->repo::removeOperation($rec);
      return response()->json(["status" => "success"], 200);
    } catch (Exception $e) {
      return response()->json(["status" => "error", "message" => ($e->getMessage()), "trace" => $e->getTraceAsString()], 400);
    }
  }

  public function getOperationStructure(Request $request)
  {
    $ret =  $this->repo::getOperationStructure($request->routing_id);
    return response()->json(["status" => "success", "data" => $ret], 200);
  }

  public function get_operation(){
    $ret =  $this->repo::get_operation();
    return response()->json(["status" => "success", "data" => $ret], 200);
}
}
