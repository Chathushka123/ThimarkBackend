<?php

namespace App\Http\Repositories;

use Illuminate\Http\Request;
use App\RoutingOperation;
use App\CutPlan;
use App\CutUpdate;
use App\Http\Resources\RoutingOperationResource;
// use App\HashStore;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use App\Http\Resources\RoutingOperationWithParentsResource;
use Illuminate\Validation\Rule;
use Exception;
use Illuminate\Support\Str;

use App\Http\Validators\RoutingOperationCreateValidator;
use App\Http\Validators\RoutingOperationUpdateValidator;
use Illuminate\Support\Facades\Log;

class RoutingOperationRepository
{
  public function show(RoutingOperation $routingOperation)
  {
    return response()->json(
      [
        'status' => 'success',
        'data' => new RoutingOperationWithParentsResource($routingOperation),
      ],
      200
    );
  }

  public static function getOperationStructure($routeId)
  {
    $ret = [];
    $operations = RoutingOperation::where('routing_id', $routeId)->where('level', 1)->orderBy('shop_floor_seq')->get();
    foreach ($operations as $operation) {
      if (!(is_null($operation->parent_operation_id))) {
        $parent = RoutingOperation::find($operation->parent_operation_id);
        $operation->parent_operation_code = $parent->operation_code;
        $operation->sort_seq = intval($parent->shop_floor_seq) . '.' . intval($operation->shop_floor_seq);
      } else {
        $operation->parent_operation_code = null;
        $operation->sort_seq = intval($operation->shop_floor_seq);
      }

      $operation->parallel = self::_checkIsParallel($operation);

      $ret[] = $operation;
      self::_getChildren($operation, $ret);
    }

    return $ret;
  }
  public static function _getChildren($parent, &$ret)
  {

    $arrCnt = sizeof($ret);
    $parent_sort_seq = $ret[$arrCnt - 1]['sort_seq'];

    $children = RoutingOperation::where('parent_operation_id', $parent['id'])
      ->where('routing_id', $parent['routing_id'])
      ->orderBy('shop_floor_seq')->get();

    if ($children->count() > 0) {
      foreach ($children as $child) {

        if (!(is_null($child->parent_operation_id))) {
          $parent = RoutingOperation::find($child->parent_operation_id);
          $child->parent_operation_code = $parent->operation_code;
          $child->sort_seq = $parent_sort_seq . '.' . intval($child->shop_floor_seq);
        } else {
          $child->parent_operation_code = null;
          $child->sort_seq = intval($child->shop_floor_seq);
        }

        $child->parallel = self::_checkIsParallel($child);
        $ret[] = $child;

        self::_getChildren($child, $ret);
      }
    }
    return $ret;
  }

  public static function createRec(array $rec)
  {

    $validator = Validator::make(
      $rec,
      RoutingOperationCreateValidator::getCreateRules($rec)
    );

    if ($validator->fails()) {
      throw new Exception($validator->errors());
    }
    $rec['operation_code'] = Str::upper($rec['operation_code']);
    try {
      $model = RoutingOperation::create($rec);
    } catch (Exception $e) {
      throw new \App\Exceptions\GeneralException($e->getMessage());
    }
    return $model;
  }

  public static function addOperation(array $rec)
  {
    try {
      DB::beginTransaction();
      $rec = self::_prepare($rec);
      $model = self::createRec($rec);
      self::_reorderOperationGivenLevel($model);
      DB::commit();
      return $model;
    } catch (Exception $e) {
      DB::rollBack();
      throw new \App\Exceptions\GeneralException($e->getMessage());
    }
  }

  private static function _prepare($rec)
  {

    if (is_null($rec['base_operation_id'])) {

      $rec['shop_floor_seq'] = 0;
      $rec['parallel_operation_no']  = self::_getNextParrallelOperationNo(null);
      $rec['level'] =  1;
    } else {
      $base_operation =   RoutingOperation::find($rec['base_operation_id']);

      //Not a Child
      if ((is_null($rec['child'])) || ($rec['child'] == false)) {

        $rec['parent_operation_id'] = $base_operation->parent_operation_id;
        $rec['level'] = $base_operation->level;

        //set parallel
        if ((!(is_null($rec['parallel']))) && ($rec['parallel'] == true)) {
          $rec['parallel_operation_no'] = $base_operation->parallel_operation_no;
        } else {

          $rec['parallel_operation_no']  = self::_getNextParrallelOperationNo($base_operation);
        }

        //Set Running Sequence
        if ($rec['position'] == "BEFORE") {
          $lowestOperation = self::_getlowestParrelOperation($base_operation);

          if (is_null($lowestOperation)) {
            $rec['shop_floor_seq'] = $base_operation->shop_floor_seq - 0.1;
          } else {
            $rec['shop_floor_seq'] = $lowestOperation->shop_floor_seq - 0.1;
          }
        }

        if ($rec['position'] == "AFTER") {

          $highestOperation = self::_getHighestParrelOperation($base_operation);

          if (is_null($highestOperation)) {
            $rec['shop_floor_seq'] = $base_operation->shop_floor_seq + 0.1;
          } else {
            $rec['shop_floor_seq'] = $highestOperation->shop_floor_seq + 0.1;
          }
        }
      } else {
        $rec['shop_floor_seq'] = 1;
        $rec['level'] = $base_operation->level + 1;
        $rec['parent_operation_id'] = $base_operation->id;
        $rec['parallel_operation_no']  = self::_getNextParrallelOperationNo(null);
      }
    }
    $rec['source'] = "SHOPFLOOR";
    return $rec;
  }

  private static function _getHighestParrelOperation($baseOperation)
  {

    if (!(is_null($baseOperation))) {
      $operation =   RoutingOperation::where('shop_floor_seq', '>', $baseOperation->shop_floor_seq)
        ->where('parallel_operation_no', $baseOperation->parallel_operation_no)
        ->where('routing_id', '=', $baseOperation->routing_id)
        ->orderBy('shop_floor_seq', 'desc')->first();
      return $operation;
    }
  }

  private static function _getlowestParrelOperation($baseOperation)
  {
    if (!(is_null($baseOperation))) {
      $operation =   RoutingOperation::where('shop_floor_seq', '<', $baseOperation->shop_floor_seq)
        ->where('parallel_operation_no', $baseOperation->parallel_operation_no)
        ->where('routing_id', '=', $baseOperation->routing_id)
        ->orderBy('shop_floor_seq', 'asc')->first();
      return $operation;
    }
  }

  private static function _getNextParrallelOperationNo($baseOperation)
  {

    if (!(is_null($baseOperation))) {
      //First Level
      if (is_null($baseOperation->parent_operation_id)) {
        $maxId =   RoutingOperation::where('level', $baseOperation->level)
          ->whereNull('parent_operation_id')
          ->where('routing_id', $baseOperation->routing_id)
          ->max('parallel_operation_no');
      }
      // All other levels
      else {
        $maxId =   RoutingOperation::where('level', $baseOperation->level)->where('parent_operation_id', $baseOperation->parent_operation_id)->max('parallel_operation_no');
      }
      return $maxId + 100;
    } else {
      return 100;
    }
  }

  private static function _reorderOperationGivenLevel($operation)
  {
    $cnt = 0;
    // First Level
    if (is_null($operation->parent_operation_id)) {
      $operations =   RoutingOperation::where(['level' => $operation->level])
        ->whereNull('parent_operation_id')
        ->select('id', 'shop_floor_seq')
        ->where('routing_id', '=', $operation->routing_id)
        ->orderBy('shop_floor_seq')->get();
    }
    // All other levels
    else {
      $operations =   RoutingOperation::where(['parent_operation_id' => $operation->parent_operation_id])
        ->select('id', 'shop_floor_seq')
        ->where('routing_id', '=', $operation->routing_id)
        ->orderBy('shop_floor_seq')->get();
    }

    if (!(is_null($operation->parent_operation_id))) {
      $parent = RoutingOperation::find($operation->parent_operation_id);
    }

    foreach ($operations as $operation) {
      $operation->update(['shop_floor_seq' => ++$cnt]);
    }
  }

  private static function _checkIsParallel($runningOperation)
  {
    if (!(is_null($runningOperation))) {
      //First Level
      if (is_null($runningOperation->parent_operation_id)) {
        $operations =   RoutingOperation::where('parallel_operation_no', $runningOperation->parallel_operation_no)
          ->where('level', $runningOperation->level)
          ->where('id', '!=', $runningOperation->id)
          ->where('routing_id', $runningOperation->routing_id)
          ->whereNull('parent_operation_id')->get();
      }
      // All other levels
      else {
        $operations =   RoutingOperation::where('parallel_operation_no', $runningOperation->parallel_operation_no)
          ->where('level', $runningOperation->level)
          ->where('id', '!=', $runningOperation->id)
          ->where('routing_id', $runningOperation->routing_id)
          ->where('parent_operation_id', $runningOperation->parent_operation_id)->get();
      }

      if ($operations->count() > 0) {
        return 1;
      } else {
        return 0;
      }
    } else {
      return 0;
    }
  }

  public static function updateRec($model_id, array $rec)
  {

    $model = RoutingOperation::findOrFail($model_id);

    if (!$model->updated_at->eq(\Carbon\Carbon::parse($rec['updated_at']))) {
      $entity = (new \ReflectionClass($model))->getShortName();
      throw new \App\Exceptions\ConcurrencyCheckFailedException($entity);
    }

    if ($model->smv != $rec['smv']) {
      $rec['smv_hist_1'] = $model->smv;
      $rec['smv_hist_2'] = $model->smv_hist_1;
    }

    Utilities::hydrate($model, $rec);
    $validator = Validator::make(
      $rec,
      RoutingOperationUpdateValidator::getUpdateRules($model_id, $rec)
    );

    if ($validator->fails()) {
      throw new Exception($validator->errors());
    }
    try {
      $model->update($rec);
      return $model;
    } catch (Exception $e) {
      throw new \App\Exceptions\GeneralException($e->getMessage());
    }
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
    RoutingOperation::destroy($recs);
  }

  public static function removeOperation(array $rec)
  {
    try {
      DB::beginTransaction();

      $children = RoutingOperation::where('parent_operation_id', $rec['id'])->get();

      if ($children->count() > 0) {
        throw new Exception("Cannot Remove Operation Child Operations Exist.");
      }

      $currentOperation = RoutingOperation::find($rec['id']);
      RoutingOperation::destroy([$rec['id']]);

      if (!(is_null($currentOperation->parent_operation_id))) {
        $otherOperation = RoutingOperation::where(['parent_operation_id' => $currentOperation->parent_operation_id])
          ->where('routing_id', '=', $currentOperation->routing_id)
          ->orderBy('shop_floor_seq')->first();
      } else {
        //First Level
        $otherOperation = RoutingOperation::whereNull('parent_operation_id')
          ->where('routing_id', '=', $currentOperation->routing_id)
          ->orderBy('shop_floor_seq')->first();
      }
      if (!(is_null($otherOperation))) {
        self::_reorderOperationGivenLevel($otherOperation);
      }
      DB::commit();
    } catch (Exception $e) {
      DB::rollBack();
      throw new \App\Exceptions\GeneralException($e->getMessage());
    }
  }

  public  function getRoutingOperationsByFppoAndCutNo($fppo_id, $cut_no = null)
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
    $routingOperations = RoutingOperation::whereHas('cut_updates', function ($q) use ($cut_update_ids) {
      $q->whereIn('id', $cut_update_ids);
    })->get();

    return RoutingOperationResource::collection($routingOperations);
  }

  public static function get_operation(){
    $cut_plans = RoutingOperation::select('operation_code')
    ->distinct('operation_code')
    ->orderBy('operation_code')
    ->get();

    return $cut_plans;
    
  }
}
