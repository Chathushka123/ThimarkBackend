<?php

namespace App\Http\Repositories;

use App\CutPlan;
use App\Exceptions\ConcurrencyCheckFailedException;
use App\Fpo;
use App\FpoCutPlan;
use App\Http\Controllers\Api\FpoController;
use Illuminate\Http\Request;
use App\LaySheet;
// use App\HashStore;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use App\Http\Resources\LaySheetWithParentsResource;
use App\Http\Resources\FpoDistinctResource;
use Illuminate\Validation\Rule;
use Exception;

use App\Http\Validators\LaySheetCreateValidator;
use App\Http\Validators\LaySheetUpdateValidator;
use Illuminate\Support\Facades\Log;

class LaySheetRepository
{
  public function show(LaySheet $laySheet)
  {
    return response()->json(
      [
        'status' => 'success',
        'data' => new LaySheetWithParentsResource($laySheet),
      ],
      200
    );
  }

  public static function createRec(array $rec)
  {
    $validator = Validator::make(
      $rec,
      LaySheetCreateValidator::getCreateRules()
    );
    if ($validator->fails()) {
      throw new Exception($validator->errors());
    }
    try {
      $rec['status'] = LaySheet::getInitialStatus();
      $model = LaySheet::create($rec);
    } catch (Exception $e) {
      throw new \App\Exceptions\GeneralException($e->getMessage());
    }
    return $model;
  }

  public static function updateRec($model_id, array $rec)
  {
    // if ($master_id == null) {
    $model = LaySheet::findOrFail($model_id);
    // } else {
    //   $model = LaySheet::where($parent_key, $master_id)
    //     ->where('id', $model_id)
    //     ->firstOrFail();
    // }
    // return $model;
    if (!$model->updated_at->eq(\Carbon\Carbon::parse($rec['updated_at']))) {
      $entity = (new \ReflectionClass($model))->getShortName();
      throw new ConcurrencyCheckFailedException($entity);
      
    }
    Utilities::hydrate($model, $rec);
    $validator = Validator::make(
      $rec,
      LaySheetUpdateValidator::getUpdateRules($model_id)
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
    $is_any_laysheet_closed = (LaySheet::whereIn('id', $recs)->where('status', 'Closed')->get()->count() > 0);
    if ($is_any_laysheet_closed)
      throw new \App\Exceptions\GeneralException("One or more of the Laysheets is Closed. Deletion was not successful.");

    foreach (Laysheet::whereIn('id', $recs)->with('cut_plans.fpo_cut_plans')->get() as $laySheet) {
      foreach ($laySheet->cut_plans as $cutPlan) {
        $cutPlan->fpo_cut_plans()->delete();
      }
      $laySheet->cut_plans()->delete();
    }
    LaySheet::destroy($recs);
  }

  public function getDistinctFpos(LaySheet $laySheet)
  {
    foreach ($laySheet->cut_plans as $cut_plan) {
      $cut_plan_ids[] = $cut_plan->id;
    }
    $fpoIds = FpoCutPlan::select('fpo_id')->distinct()->whereIn('cut_plan_id', $cut_plan_ids)->get();
    $fpos = [];
    foreach ($fpoIds as $key => $value) {
      $fpos[] = FpoDistinctResource::collection(DB::table('fpos')
        ->join('socs', 'fpos.soc_id', '=', 'socs.id')
        ->select('fpos.id', 'fpos.wfx_fpo_no', 'socs.wfx_soc_no', 'fpos.qty_json')
        ->where('fpos.id', $value['fpo_id'])
        ->get())[0];
    }

    return $fpos;
  }
}
