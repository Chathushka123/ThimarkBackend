<?php

namespace App\Http\Repositories;

use App\Fpo;
use Illuminate\Http\Request;
use App\FpoOperation;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use App\Http\Resources\FpoOperationResource;
use Exception;

use App\Http\Validators\FpoOperationCreateValidator;
use App\Http\Validators\FpoOperationUpdateValidator;
use Illuminate\Support\Facades\Log;

class FpoOperationRepository
{

  public function show(FpoOperation $fpoOperation)
  {
    return response()->json(
      [
        'status' => 'success',
        'data' => new FpoOperationResource($fpoOperation),
      ],
      200
    );
  }

  public static function createRec(array $rec)
  {
    $validator = Validator::make(
      $rec,
      FpoOperationCreateValidator::getCreateRules()
    );
    if ($validator->fails()) {
      throw new Exception($validator->errors());
    }
    try {
      $model = FpoOperation::create($rec);
    } catch (Exception $e) {
      throw new \App\Exceptions\GeneralException($e->getMessage());
    }
    return $model;
  }

  public static function updateRec($model_id, array $rec)
  {
    // if ($master_id == null) {
    $model = FpoOperation::findOrFail($model_id);
    // } else {
    //   $model = FpoOperation::where($parent_key, $master_id)
    //     ->where('id', $model_id)
    //     ->firstOrFail();
    // }
    // return $model;
    Utilities::hydrate($model, $rec);
    $validator = Validator::make(
      $rec,
      FpoOperationUpdateValidator::getUpdateRules($model_id)
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
    FpoOperation::destroy($recs);
  }

  public static function getFpoOperationsStructure($fpoId)
  {
    $fpo = Fpo::find($fpoId);
    
    $ret = DB::table('styles')
      ->join('socs', 'socs.style_id', '=', 'styles.id')
      ->select('styles.routing_id')
      ->where('socs.id', $fpo->soc_id)
      ->first();
    
    
    $operations = RoutingOperationRepository::getOperationStructure($ret->routing_id);
    
    foreach($operations as $operation)
    {
       $fpo_operation = FpoOperation::where('routing_operation_id', $operation->id)->where('fpo_id', $fpoId)->first();
       if(!(is_null($fpo_operation))) {
        $operation->wip_point = $fpo_operation->wip_point;
        $operation->print_bundle = $fpo_operation->print_bundle;
        $operation->fpo_operation_id = $fpo_operation->id;
        $operation->updated_at = $fpo_operation->updated_at;
       }
    }  
    return $operations;
  }
}
