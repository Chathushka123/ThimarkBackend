<?php

namespace App\Http\Repositories;

use App\BundleCutUpdate;
use App\CutPlan;
use Illuminate\Http\Request;
use App\CutUpdate;
use App\Exceptions\ConcurrencyCheckFailedException;
use App\Fpo;
use App\FpoCutPlan;
// use App\HashStore;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use App\Http\Resources\CutUpdateWithParentsResource;
use Illuminate\Validation\Rule;
use App\Http\Resources\CutUpdateResource;
use Exception;

use App\Http\Validators\CutUpdateCreateValidator;
use App\Http\Validators\CutUpdateUpdateValidator;
use Illuminate\Support\Facades\Log;

//use function GuzzleHttp\json_decode;

class CutUpdateRepository
{
  public function show(CutUpdate $cutUpdate)
  {
    return response()->json(
      [
        'status' => 'success',
        'data' => new CutUpdateWithParentsResource($cutUpdate),
      ],
      200
    );
  }

  public static function createRec(array $rec)
  {
    $rec['qty_json'] = json_encode($rec['qty_json']);
    $validator = Validator::make(
      $rec,
      CutUpdateCreateValidator::getCreateRules()
    );
    if ($validator->fails()) {
      throw new Exception($validator->errors());
    }
    $rec['qty_json'] = json_decode($rec['qty_json']);
    try {
      $model = CutUpdate::create($rec);
    } catch (Exception $e) {
      throw new \App\Exceptions\GeneralException($e->getMessage());
    }
    return $model;
  }

  public static function updateRec($model_id, array $rec)
  {
    // if ($master_id == null) {
    $model = CutUpdate::findOrFail($model_id);
    // } else {
    //   $model = CutUpdate::where($parent_key, $master_id)
    //     ->where('id', $model_id)
    //     ->firstOrFail();
    // }
    // return $model;
    if (!$model->updated_at->eq(\Carbon\Carbon::parse($rec['updated_at']))) {
      $entity = (new \ReflectionClass($model))->getShortName();
      throw new ConcurrencyCheckFailedException($entity);

    }
    if (array_key_exists('qty_json', $rec)) {
      $rec['qty_json'] = json_encode($rec['qty_json']);
    }
    Utilities::hydrate($model, $rec);
    $validator = Validator::make(
      $rec,
      CutUpdateUpdateValidator::getUpdateRules($model_id)
    );
    if ($validator->fails()) {
      throw new Exception($validator->errors());
    }
    if (array_key_exists('qty_json', $rec)) {
      $rec['qty_json'] = json_decode($rec['qty_json']);
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
    if(BundleCutUpdate::whereIn('cut_update_id',$recs)->exists()){
      throw new \App\Exceptions\GeneralException('Cut Updates has been utilzied, Not allowed to delete');
    };

    CutUpdate::destroy($recs);
  }

  public function getCutUpdates(Request $request)
  {
    $combine_order_id = $request->combine_order_id;
    $cut_plan_id = $request->cut_plan_id;
   
    $cut_updates = CutPlan::select(
      'cut_plans.cut_no',
      'cut_updates.id',
      'cut_updates.qty_json',
      'cut_updates.qty_json_order',
      'fpos.wfx_fpo_no',
      'fppos.fppo_no'      
    ) 
      ->join('cut_updates', 'cut_updates.cut_plan_id', '=', 'cut_plans.id')
      ->join('fppos', 'cut_updates.fppo_id', '=', 'fppos.id')
      ->join('fpo_cut_plans', 'fpo_cut_plans.fppo_id', '=', 'fppos.id')
      ->join('fpos','fpo_cut_plans.fpo_id', '=', 'fpos.id')
      ->where('cut_plans.combine_order_id', $combine_order_id)
      ->where('cut_updates.cut_plan_id', $cut_plan_id)
      ->distinct()
      ->get();
      
    //sorting json
    foreach($cut_updates as $key=>$result){ 

      if((isset($result->qty_json)) && (isset($result->qty_json_order))) {
        $result->qty_json = json_decode($result->qty_json,true);
        //
        $result->qty_json = Utilities::sortQtyJson($result->qty_json_order, $result->qty_json);
      }
    }
    return $cut_updates;
  }
}
