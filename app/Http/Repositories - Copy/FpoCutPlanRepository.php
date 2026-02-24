<?php

namespace App\Http\Repositories;

use App\Bundle;
use App\BundleCutUpdate;
use App\BundleTicket;
use App\CombineOrder;
use App\CutPlan;
use Illuminate\Http\Request;
use App\FpoCutPlan;
use App\CutUpdate;
use App\DailyShift;
use App\Exceptions\ConcurrencyCheckFailedException;
use App\LaySheet;
use App\Fppo;
// use App\HashStore;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use App\Http\Resources\FpoCutPlanWithParentsResource;
use Illuminate\Validation\Rule;
use Exception;
use App\Exceptions\GeneralException;
use App\Fpo;
use App\Http\Controllers\Api\FpoController;
use App\Http\Resources\FpoCutPlanResource;
use App\Http\Validators\FpoCutPlanCreateValidator;
use App\Http\Validators\FpoCutPlanUpdateValidator;
use App\StyleFabric;
use Barryvdh\DomPDF\PDF as DomPDFPDF;
use Illuminate\Support\Facades\Log;
use PDF;

class FpoCutPlanRepository
{

  public function show(FpoCutPlan $fpoCutPlan)
  {
    return response()->json(
      [
        'status' => 'success',
        'data' => new FpoCutPlanWithParentsResource($fpoCutPlan),
      ],
      200
    );
  }

  public static function createRec(array $rec)
  {

    $rec['qty_json'] = json_encode($rec['qty_json']);
    $validator = Validator::make(
      $rec,
      FpoCutPlanCreateValidator::getCreateRules()
    );
    if ($validator->fails()) {
      throw new Exception($validator->errors());
    }
    $rec['qty_json'] = json_decode($rec['qty_json']);
    try {
      $model = FpoCutPlan::create($rec);
    } catch (Exception $e) {
      throw new \App\Exceptions\GeneralException($e->getMessage());
    }
    return $model;
  }

  public static function updateRec($model_id, array $rec)
  {
    // if ($master_id == null) {
    $model = FpoCutPlan::findOrFail($model_id);
    // } else {
    //   $model = FpoCutPlan::where($parent_key, $master_id)
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
      FpoCutPlanUpdateValidator::getUpdateRules($model_id)
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
    FpoCutPlan::destroy($recs);
  }

  public function createFppo(Request $request)
  {
    try {
      DB::beginTransaction();

      $qty_json = [];
      $fpo_cut_plan_ids = $request->all()['fpo_cut_plans'];

      foreach ($fpo_cut_plan_ids as $fpo_cut_plan_id) {
        $fpoCutPlan = FpoCutPlan::findOrFail($fpo_cut_plan_id);
        CombineOrder::where('id', $fpoCutPlan->cut_plan->combine_order->id)->update(['status' => 'Closed']);
      }
      $fpo_cut_plans = FpoCutPlan::whereIn('id', $fpo_cut_plan_ids)->get();

      //validate
      self::_validateCutPlanSameOrigin($fpo_cut_plans);

      //sorting json
      foreach ($fpo_cut_plans as $key => $result) {
        if ((isset($result->qty_json)) && (isset($result->qty_json_order))) {
          $result->qty_json = Utilities::sortQtyJson($result->qty_json_order, $result->qty_json);
        }
      }

      foreach ($fpo_cut_plans as $key => $rec) {
        foreach ($rec['qty_json'] as $key => $value) {
          $qty_json[$key] = $value + (array_key_exists($key, $qty_json) ? $qty_json[$key] : 0);
        }
      }

      $statement = DB::select("SHOW TABLE STATUS LIKE 'fppos'");
      $nextId = $statement[0]->Auto_increment;

      $fppo = FppoRepository::createRec([
        'fppo_no' => 'FPPO' . $nextId,
        'qty_json' => $qty_json,
        'qty_json_order' => array_keys($qty_json),
        'utilized' => true
      ]);

      $fpo_ids = FpoCutPlan::whereIn('id', $fpo_cut_plan_ids)->pluck('fpo_id')->toArray();

      // foreach ($fpo_ids as $fpo_id) {
      //   FpoController::fsmActionClose(Fpo::findOrFail($fpo_id));
      // }

      FpoCutPlan::whereIn('id', $request->all()['fpo_cut_plans'])->update(['fppo_id' => $fppo->id]);

      DB::commit();
      return response()->json(['status' => 'success', 'data' => $fppo], 200);
    } catch (Exception $e) {
      DB::rollBack();
      throw new \App\Exceptions\GeneralException($e->getMessage());
    }
  }

  public function manualCutUpdate(Request $request)
  {
    $fppo_id = $request->fppo_id;
    $cut_plan_id = $request->cut_plan_id;
    $qty_json = $request->qty_json;

    date_default_timezone_set("Asia/Calcutta"); 
    $dateTime = date("Y-m-d H:i:s");
  
    $shift = DailyShift::select('id')
      
      ->where('daily_shifts.start_date_time','<=' ,$dateTime)
      ->where('daily_shifts.end_date_time','>=' ,$dateTime)
      ->first();
      
      if(is_null($shift)){
        throw new \App\Exceptions\GeneralException('Shift Not Found');
      }

    try {
      $this->_checkQuantities($cut_plan_id, $fppo_id, $qty_json);
      $cutUpdate = CutUpdateRepository::createRec([
        'cut_plan_id' => $cut_plan_id,
        'fppo_id' => $fppo_id,
        'qty_json' => Utilities::json_numerize($qty_json, "int"),
        'qty_json_order' => array_keys($qty_json),
        //'daily_shift_id'=>$shift->id
        'daily_shift_id'=>is_null($shift)? null : $shift->id
      ]);
      
      return response()->json(['status' => 'success'], 200);
    } catch (Exception $e) {
      throw new \App\Exceptions\GeneralException($e->getMessage());
    }
  }

  public static function getPendingFpoCutPlansByCombineOrder($combine_order_id)
  {
    $fpo_cut_plans = FpoCutPlan::select(
      'fpo_cut_plans.id as fpo_cut_plan_id',
      'cut_plans.cut_no',
      'fpos.id as fpo_id',
      'fpos.wfx_fpo_no',
      'fpos.priority_seq',
      'fpo_cut_plans.qty_json',
      'fpo_cut_plans.qty_json_order'
    )
      ->join('cut_plans', 'fpo_cut_plans.cut_plan_id', '=', 'cut_plans.id')
      ->join('fpos', 'fpo_cut_plans.fpo_id', '=', 'fpos.id')
      ->where('cut_plans.combine_order_id', $combine_order_id)
      ->where('cut_plans.main_fabric', 1)
      ->whereNull('fpo_cut_plans.fppo_id')
      ->distinct()
      ->orderBy('cut_plans.cut_no', 'ASC')
      ->orderBy('fpos.priority_seq', 'asc')
      ->get();

    // return $fpo_cut_plans;

    //sorting json
    foreach ($fpo_cut_plans as $key => $result) {
      if ((isset($result->qty_json)) && (isset($result->qty_json_order))) {
        $result->qty_json = Utilities::sortQtyJson($result->qty_json_order, $result->qty_json);
      }
    }
    $fpo_cut_plans = $fpo_cut_plans->all();

    usort($fpo_cut_plans, function ($a, $b) {
      $a_no = (int) filter_var($a['cut_no'], FILTER_SANITIZE_NUMBER_INT);
      $b_no = (int) filter_var($b['cut_no'], FILTER_SANITIZE_NUMBER_INT);
      if (($b_no - $a_no) == 0) {
        $a_no = (int) filter_var($a['wfx_fpo_no'], FILTER_SANITIZE_NUMBER_INT);
        $b_no = (int) filter_var($b['wfx_fpo_no'], FILTER_SANITIZE_NUMBER_INT);
        return ($a_no - $b_no);
      } else {
        return ($b_no - $a_no);
      }
    });

    //$ret = $fpo_cut_plans->map(function ($item, $key) {
    // return FpoCutPlan::find($item->id)->load(['fpo', 'fppo', 'cut_plan']);
    //});
    //return FpoCutPlanWithParentsResource::collection($ret);

    return $fpo_cut_plans;
  }

  public static function _validateCutPlanSameOrigin($fpo_cut_plans)
  {
    //Validate cut plans originate from same FPO
    $source_fpo_id = null;

    foreach ($fpo_cut_plans as $fpo_cut_plan) {
      $fpo_cut_plan_first = FpoCutPlan::where('id', $fpo_cut_plan['id'])->first();

      if (is_null($source_fpo_id)) {
        $source_fpo_id = $fpo_cut_plan_first->fpo_id;
      } else {
        if ($fpo_cut_plan->fpo_id != $source_fpo_id) {
          throw new Exception('Cannot combine different Fpos to create Fppo');
        }
      }
    }
  }

  public function printConsumptionReport($combine_order_id)
  {

    $combine_order = CombineOrder::find($combine_order_id);
    $fpo_cut_plans = StyleFabric::select(
      'style_fabrics.fabric',
      'cut_plans.cut_no',
      'fpo_cut_plans.fpo_id',
      'fpo_cut_plans.consumption'
    )
      ->join('cut_plans', 'cut_plans.style_fabric_id', '=', 'style_fabrics.id')
      ->join('fpo_cut_plans', 'fpo_cut_plans.cut_plan_id', '=', 'cut_plans.id')
      ->where('cut_plans.combine_order_id', $combine_order_id)
      ->distinct()
      ->orderBy('fpo_cut_plans.fpo_id')
      ->orderBy('style_fabrics.fabric')
      ->orderBy('cut_plans.cut_no')
      ->get();

    foreach ($fpo_cut_plans as $fpo_cut_plan) {
      $fpo = Fpo::find($fpo_cut_plan->fpo_id);
      $fpo_cut_plan->wfx_fpo_no = $fpo->wfx_fpo_no;
    }


    $data = ['fpo_cut_plans' => $fpo_cut_plans];
    $pdf = PDF::loadView('print.consumptionreport', $data);
    return $pdf->stream('consumption_report_' . date('Y_m_d_H_i_s') . '.pdf');
  }

  private function _checkQuantities($cut_plan_id, $fppo_id, $qty_json)
  {
    $json_qty_sum = 0;
    foreach ($qty_json as $key => $value) {
      if ($value < 0) {
        throw new Exception("Cut update quantity must be greater than zero.");
      }
      $json_qty_sum += $value;
    }
    if ($json_qty_sum == 0) {
      throw new Exception("Cut update can not be zero.");
    }

    $cut_updates = CutUpdate::where(['cut_plan_id' => $cut_plan_id, 'fppo_id' => $fppo_id])->get()->all();

    $fpo_cut_plans_json = FpoCutPlan::where(['cut_plan_id' => $cut_plan_id, 'fppo_id' => $fppo_id])->first()->qty_json;

    $cut_updates_json = array_reduce($cut_updates, function ($carry, $item) {
      foreach ($item['qty_json'] as $size => $qty) {
        if (array_key_exists($size, (array)$carry)) {
          $carry[$size] += $qty;
        } else {
          $carry[$size] = $qty;
        }
      }
      return $carry;
    }, []);

    $balance_json = Utilities::json_subtract($fpo_cut_plans_json, $cut_updates_json);

    if ((sizeof($qty_json) > 0) && (sizeof($balance_json) > 0)) {
      //if (Utilities::json_compare($qty_json, $balance_json)) {
        if (Utilities::json_compare($qty_json, $balance_json)) {
        throw new Exception("Fppo quantities cannot be more than the balance quantity.");
      }
    }
  }
}
