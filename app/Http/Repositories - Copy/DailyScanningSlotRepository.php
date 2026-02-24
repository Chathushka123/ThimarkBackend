<?php

namespace App\Http\Repositories;

// use App\CutPlan;
// use App\Fpo;
// use App\FpoCutPlan;

use App\BundleTicket;
use App\DailyScanningSlot;
use App\DailyShift;
use App\DailyShiftTeam;
// use App\HashStore;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use App\Http\Resources\DailyScanningSlotWithParentsResource;
use Illuminate\Validation\Rule;
use Exception;

use App\Http\Validators\DailyScanningSlotCreateValidator;
use App\Http\Validators\DailyScanningSlotUpdateValidator;
use App\ShiftDetail;
use Illuminate\Log\Logger;
use Illuminate\Support\Facades\Log;

class DailyScanningSlotRepository
{
  const DISCARD_COLUMNS = [];

  const FIELD_MAPPING = [];

  public function show(DailyScanningSlot $dailyScanningSlot)
  {
    return response()->json(
      [
        'status' => 'success',
        'data' => new DailyScanningSlotWithParentsResource($dailyScanningSlot),
      ],
      200
    );
  }

  public static function createRec(array $rec)
  {
    $validator = Validator::make(
      $rec,
      DailyScanningSlotCreateValidator::getCreateRules()
    );
    if ($validator->fails()) {
      throw new Exception($validator->errors());
    }
    try {
      //$count = DailyScanningSlot::where('daily_shift_id', $rec['daily_shift_id'])->count();
      //$rec['seq_no'] = $count + 1;
      $model = DailyScanningSlot::create($rec);
    } catch (Exception $e) {
      throw new \App\Exceptions\GeneralException($e->getMessage());
    }
    return $model;
  }

  public static function updateRec($model_id, array $rec)
  {
    $model = DailyScanningSlot::findOrFail($model_id);

    Utilities::hydrate($model, $rec);

    $validator = Validator::make(
      $rec,
      DailyScanningSlotUpdateValidator::getUpdateRules($model_id)
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
    foreach ($recs as $id) {
      $model = DailyScanningSlot::findOrFail($id);
      // if ($model->isReadOnly()) {
      //   throw new Exception("Oc " . $model->wfx_oc_no . " is " . Oc::GetClientState($model->state));
      // }
      DailyScanningSlot::destroy([$id]);
    }
  }

  public function getBySeqNo($current_date, $team_id, $shift_id, $seq_no)
  {
    $daily_shift_team = null;
    $daily_scanning_slot = null;
    try {
      $current_date_day = $current_date->format('l');
    } catch (Exception $e) {
      throw new \App\Exceptions\GeneralException("Date conversion error.");
    }

  //  $shift_detail = ShiftDetail::where([
   //   'day' => $current_date_day,
   //   'shift_id' => $shift_id
   // ])->first();

  //  print_r($shift_detail->id);
    //if (isset($shift_detail)) {
     // $daily_shift = DailyShift::where('shift_detail_id', $shift_detail->id)->whereDate('current_date', '=', $current_date)->first();
	 
	  date_default_timezone_set("Asia/Calcutta"); 
    $dateTime = date("Y-m-d H:i:s");
    $daily_shift = DailyShift::select('daily_shifts.id')
	  ->join('shift_details','shift_details.id','=','daily_shifts.shift_detail_id')
	  ->where('shift_details.shift_id', $shift_id)
	  ->where('shift_details.day', $current_date_day)
	  //->where('daily_shifts.start_date_time', $current_date_day)
	  //->whereDate('daily_shifts.current_date', '=', $current_date)
	  ->where('daily_shifts.start_date_time', '<=', $dateTime)
	  ->where('daily_shifts.end_date_time', '>=', $dateTime)
	  ->first();

    if(is_null($daily_shift)){
      throw new \App\Exceptions\GeneralException("Daily Shift Not Found.");
    }
	  
	 // print_r($daily_shift->id);
	  
      $daily_shift_team = DailyShiftTeam::where('daily_shift_id', $daily_shift->id)
        ->where('team_id', $team_id)->first();
        
      $daily_scanning_slot = DailyScanningSlot::where('daily_shift_id', $daily_shift->id)->where('seq_no', $seq_no)->first();
   // }

    return response()->json(['DailyScanningSlot' => $daily_scanning_slot, 'DailyShiftTeam' => $daily_shift_team], 200);
  }

  public function getSlotsWithProgress($daily_shift_id)
  {

    $progressed = 0;
    $slots = DailyScanningSlot::where('daily_shift_id', $daily_shift_id)->get();

    foreach ($slots as $slot) {
      $bundle_tickets = BundleTicket::where('daily_scanning_slot_id', $slot->id)->get();
      if ($bundle_tickets->count() > 0) {
        $progressed = 1;
      }
    }
    return ['DailyScanningSlots' => $slots, 'Progressed' =>  $progressed];
  }
}
