<?php

namespace App\Http\Repositories;

use Illuminate\Http\Request;
use App\Routing;
use App\CutPlan;
use App\CutUpdate;
use App\Http\Controllers\Api\RoutingOperationController;
use App\Http\Resources\RoutingResource;
// use App\HashStore;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use App\Http\Resources\RoutingWithParentsResource;
use Illuminate\Validation\Rule;
use Exception;
use Illuminate\Support\Str;

use App\Http\Validators\RoutingCreateValidator;
use App\Http\Validators\RoutingUpdateValidator;
use Illuminate\Support\Facades\Log;

class RoutingRepository
{
  public function show(Routing $routing)
  {
    return response()->json(
      [
        'status' => 'success',
        'data' => new RoutingWithParentsResource($routing),
      ],
      200
    );
  }

  public static function getFullStructure($routingId)
  {
    $route = Routing::find($routingId);
    $operations = RoutingOperationRepository::getOperationStructure($routingId);
    $route->routing_operations = $operations;
    return $route;
  }

  public static function createRec(array $rec)
  {
    $validator = Validator::make(
      $rec,
      RoutingCreateValidator::getCreateRules()
    );
    if ($validator->fails()) {
      throw new Exception($validator->errors());
    }
    $rec['route_code'] = Str::upper($rec['route_code']);
    // try {
    $model = Routing::create($rec);
    // } catch (Exception $e) {
    //   throw new \App\Exceptions\GeneralException($e->getMessage());
    // }
    return $model;
  }

  public static function updateRec($model_id, array $rec)
  {
    $model = Routing::findOrFail($model_id);
    Utilities::validateCode($model->route_code, $rec['route_code'], "Routing Code");

    if (!$model->updated_at->eq(\Carbon\Carbon::parse($rec['updated_at']))) {
      $entity = (new \ReflectionClass($model))->getShortName();
      throw new \App\Exceptions\ConcurrencyCheckFailedException($entity);
    }

    Utilities::hydrate($model, $rec);
    $validator = Validator::make(
      $rec,
      RoutingUpdateValidator::getUpdateRules($model_id)
    );
    if ($validator->fails()) {
      throw new Exception($validator->errors());
    }
    try {
      $model->update($rec);
    } catch (Exception $e) {
      throw new \App\Exceptions\GeneralException($e->getMessage());
    }
    return $model;
  }

  public static function createMultipleRecs($master_id, array $recs)
  {
    $ret = [];
    foreach ($recs as $rec) {
      $parent_key = array_search("!PARENT_KEY!", $rec);
      if ($parent_key) {
        $rec[$parent_key] = $master_id;
      }
      $ret[] = self::createRec($rec);
    }

    return $ret;
  }

  public static function updateMultipleRecs($master_id, array $recs)
  {
    $ret = [];
    foreach ($recs as $index => $body) {
      // below loop only executes once. foreach is used to extract [key, value] pair
      foreach ($body as $child_id => $rec) {
        $parent_key = array_search("!PARENT_KEY!", $rec);
        if ($parent_key) {
          $rec[$parent_key] = $master_id;
        }
        $ret[] = self::updateRec($child_id, $rec);
      }
    }

    return $ret;
  }

  public static function deleteRecs(array $recs)
  {
    Routing::destroy($recs);
  }

  public function getRoutingsByFppoAndCutNo($fppo_id, $cut_no = null)
  {
    // $data = [
    //   "A" => ["id" => 12, "name" => "test1"],
    //   "B" => ["id" => 13, "name" => "test2"]
    // ];
    // data_fill($data, '*.cut_id', 1);

    // return $data;

    $cut_plans = CutPlan::select('id')
      ->distinct()
      ->where('fppo_id', $fppo_id)
      ->where('cut_no', 'LIKE', (is_null($cut_no) ? '%' : $cut_no))
      ->get()
      ->toArray();
    $cut_update_ids = CutUpdate::select('id')->distinct()->whereIn('cut_plan_id', [$cut_plans])->get()->toArray();
    $routings = Routing::whereHas('cut_updates', function ($q) use ($cut_update_ids) {
      $q->whereIn('id', $cut_update_ids);
    })->get();

    return RoutingResource::collection($routings);
  }
}
