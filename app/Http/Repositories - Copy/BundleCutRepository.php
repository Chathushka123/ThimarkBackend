<?php

namespace App\Http\Repositories;

use App\BundleCutUpdate;
use App\CutUpdate;
use App\FpoCutPlan;
use App\Http\Resources\BundleCutResource;
// use App\HashStore;
use Illuminate\Support\Facades\Validator;
use App\Http\Resources\BundleCutWithParentsResource;
use Exception;

use App\Http\Validators\BundleCutCreateValidator;
use App\Http\Validators\BundleCutUpdateValidator;

class BundleCutRepository
{
  public function show(BundleCutUpdate $bundleCut)
  {
    return response()->json(
      [
        'status' => 'success',
        'data' => new BundleCutWithParentsResource($bundleCut),
      ],
      200
    );
  }

  public static function createRec(array $rec)
  {
    $validator = Validator::make(
      $rec,
      BundleCutCreateValidator::getCreateRules()
    );
    if ($validator->fails()) {
      throw new Exception($validator->errors());
    }
    try {
      $model = BundleCutUpdate::create($rec);
    } catch (Exception $e) {
      throw new \App\Exceptions\GeneralException($e->getMessage());
    }
    return $model;
  }

  public static function updateRec($model_id, array $rec)
  {
    // if ($master_id == null) {
    $model = BundleCutUpdate::findOrFail($model_id);
    // } else {
    //   $model = BundleCut::where($parent_key, $master_id)
    //     ->where('id', $model_id)
    //     ->firstOrFail();
    // }
    // return $model;
    Utilities::hydrate($model, $rec);
    $validator = Validator::make(
      $rec,
      BundleCutUpdateValidator::getUpdateRules($model_id)
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
    BundleCutUpdate::destroy($recs);
  }

  public function getBundlesByFppoAndCutNo($fppo_id, $cut_no = null)
  {
    $fpo_cut_plans = FpoCutPlan::select('fpo_cut_plans.id')
      ->distinct()
      ->join('cut_plans', 'fpo_cut_plans.cut_plan_id', '=', 'cut_plans.id')
      ->where('fppo_id', $fppo_id)
      ->where('cut_plans.cut_no', 'LIKE', (is_null($cut_no) ? '%' : $cut_no))
      ->get()
      ->toArray();
    $cut_update_ids = CutUpdate::select('id')->distinct()->whereIn('fpo_cut_plan_id', $fpo_cut_plans)->get()->toArray();
    $bundles = BundleCutUpdate::whereHas('cut_updates', function ($q) use ($cut_update_ids) {
      $q->whereIn('id', $cut_update_ids);
    })->get();

    return BundleCutResource::collection($bundles);
  }
}
