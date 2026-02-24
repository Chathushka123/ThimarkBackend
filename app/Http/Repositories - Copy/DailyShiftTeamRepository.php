<?php

namespace App\Http\Repositories;

use App\DailyScanningSlot;
use App\DailyScanningSlotEmployee;
use App\DailyShift;
use App\DailyShiftTeam;
use App\DailyTeamEmployee;
use App\DailyTeamSlotTarget;
use App\Employee;
use App\Http\FunctionalValidators\DailyShiftTeamFunctionalValidator;
use App\Http\Resources\DailyShiftTeamResource;
use App\Http\Resources\DailyShiftTeamWithAdditionalColsResource;
use Illuminate\Support\Facades\Validator;
use App\Http\Resources\DailyShiftTeamWithParentsResource;
use App\Http\Resources\DailyTeamEmployeeWithParentsResource;
use App\Http\Resources\ShiftDetailWithAdditionalColsResource;
use Exception;

use App\Http\Validators\DailyShiftTeamCreateValidator;
use App\Http\Validators\DailyShiftTeamUpdateValidator;
use App\Shift;
use App\ShiftDetail;
use App\Team;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class DailyShiftTeamRepository
{
  public function show(DailyShiftTeam $dailyShiftTeam)
  {
    return response()->json(
      [
        'status' => 'success',
        'data' => new DailyShiftTeamWithParentsResource($dailyShiftTeam),
      ],
      200
    );
  }

  public static function createRec(array $rec)
  {

    $validator = Validator::make(
      $rec,
      DailyShiftTeamCreateValidator::getCreateRules()
    );
    if ($validator->fails()) {
      throw new Exception($validator->errors());
    }
    try {
      DailyShiftTeamFunctionalValidator::EnforceUnique($rec);
      $model = DailyShiftTeam::create($rec);
    } catch (Exception $e) {
      throw new \App\Exceptions\GeneralException($e->getMessage());
    }
    return $model;
  }

  public static function updateRec($model_id, array $rec)
  {
    // if ($master_id == null) {
    $model = DailyShiftTeam::findOrFail($model_id);
    // } else {
    //   $model = DailyShiftTeam::where($parent_key, $master_id)
    //     ->where('id', $model_id)
    //     ->firstOrFail();
    // }
    // return $model;
    Utilities::hydrate($model, $rec);
    $validator = Validator::make(
      $rec,
      DailyShiftTeamUpdateValidator::getUpdateRules($model_id)
    );
    if ($validator->fails()) {
      throw new Exception($validator->errors());
    }
    try {
      DailyShiftTeamFunctionalValidator::EnforceUnique($rec, $model->id);
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
    DailyShiftTeam::destroy($recs);
  }

  public function getEmployeeAllocation($current_date, $team_id, $shift_detail_id)
  {
    $dst = DailyShiftTeam::whereDate(
      'current_date',
      '=',
      $current_date
    )
      ->where(['team_id' => $team_id, 'shift_detail_id' => $shift_detail_id])
      ->first();

    if (isset($dst)) {
      // return DailyShiftTeam as well
      $dte = DailyTeamEmployee::where('daily_shift_team_id', $dst->id)->get();
      $dte_ids = DailyTeamEmployee::where('daily_shift_team_id', $dst->id)->pluck('employee_id')->toArray();

      if (!$dte->isEmpty()) {
        $dstIdsOfCurrentDate = DailyShiftTeam::whereDate('current_date', '=', $current_date)
          ->whereNotIn('id', [$dst->id])
          ->pluck('id')
          ->toArray();
        $employeesAttachedToTeams = DailyTeamEmployee::whereIn('daily_shift_team_id', $dstIdsOfCurrentDate)
          ->pluck('employee_id')
          ->toArray();
        $emp = Employee::where('base_team_id', $team_id)
          ->whereNotIn('id', $employeesAttachedToTeams)
          ->whereNotIn('id', $dte_ids)
          ->get();
        return response()->json(["DailyTeamEmployee" => DailyTeamEmployeeWithParentsResource::collection($dte), "Employee" => $emp, 'ShiftDetail' => (new DailyShiftTeamWithAdditionalColsResource($dst))], 200);
      } else {
        // return shift_detail model as well
        $sd = ShiftDetail::find($shift_detail_id);
        $emp = Employee::where('base_team_id', $team_id)->get();
        return response()->json(["DailyTeamEmployee" => [], "Employee" => $emp, "ShiftDetail" => (new ShiftDetailWithAdditionalColsResource($sd))], 200);
      }
    } else {
      // return shift_detail model as well
      $sd = ShiftDetail::find($shift_detail_id);
      $emp = Employee::where('base_team_id', $team_id)->get();
      return response()->json(["DailyTeamEmployee" => [], "Employee" => $emp, "ShiftDetail" => (new ShiftDetailWithAdditionalColsResource($sd))], 200);
    }
    return response()->json([], 200);
  }

  public function getTargeInformation($current_date, $team_id, $shift_detail_id)
  {
    $has_new_lines = 0;
    $daily_shift_team = DailyShiftTeam::where('current_date', '=', $current_date)
      ->where('team_id', $team_id)
      ->where('shift_detail_id', $shift_detail_id)
      ->with('daily_scanning_slots')
      ->first();

    $daily_scanning_slots = $daily_shift_team['daily_scanning_slots'];
    foreach ($daily_scanning_slots as $slot) {
      if (is_null($slot['planned_target'])) {
        $has_new_lines = 1;
      }
    }
    $daily_shift_team['has_new_lines']  = $has_new_lines;
    return $daily_shift_team;
  }

  public function getByDay($current_date, $team_id, $shift_id)
  {
    try {
      try {
        $current_date_day = $current_date->format('l');
      } catch (Exception $e) {
        throw new \App\Exceptions\GeneralException("Date conversion error.");
      }

      $shift_detail = ShiftDetail::where([
        'day' => $current_date_day,
        'shift_id' => $shift_id
      ])->first();

      if (isset($shift_detail)) {
        $dst = DailyShiftTeam::whereDate('current_date', '=', $current_date)
          ->where([
            'team_id' => $team_id,
            'shift_detail_id' => $shift_detail->id
          ])->first();
        if (!isset($dst)) {
          throw new \App\Exceptions\GeneralException("Daily Shift Team does not exist.");
        }
      } else {
        throw new \App\Exceptions\GeneralException("Shift Detail does not exist.");
      }
      return response()->json(['DailyShiftTeam' => $dst], 200);
    } catch (Exception $e) {
      throw $e;
    }
  }

  public function getTeamsPerDay($current_date)
  {

    $teams = DailyShift::select(
      'daily_shifts.id as daily_shift_id',
      'daily_shift_teams.id as daily_shift_teams_id',
      'teams.id as team_id',
      'teams.code',
      'teams.description'
    )
      ->join('daily_shift_teams', 'daily_shift_teams.daily_shift_id', '=', 'daily_shifts.id')
      ->join('teams', 'teams.id', '=', 'daily_shift_teams.team_id')
      ->where('daily_shifts.current_date', $current_date)
      ->get();


    return $teams;
  }

  public function getTeamsPerDayByCodeAndDesc($current_date, $team_code, $team_desc)
  {

    $teams = DailyShift::select(
      'daily_shifts.id as daily_shift_id',
      'daily_shift_teams.id as daily_shift_teams_id',
      'teams.id as team_id',
      'teams.code',
      'teams.description'
    )
      ->join('daily_shift_teams', 'daily_shift_teams.daily_shift_id', '=', 'daily_shifts.id')
      ->join('teams', 'teams.id', '=', 'daily_shift_teams.team_id')
      ->where('daily_shifts.current_date', $current_date)
      ->where('teams.code', 'LIKE', '%' . $team_code . '%')
      ->where('teams.description', 'LIKE', '%' . $team_desc . '%')
      ->get();


    return $teams;
  }

  public function getTeamsPerShift($daily_shift_id)
  {

    $teams = DailyShift::select(
      'daily_shift_teams.id as daily_shift_teams_id',
      'teams.id as team_id',
      'teams.code',
      'teams.description'
    )
      ->join('daily_shift_teams', 'daily_shift_teams.daily_shift_id', '=', 'daily_shifts.id')
      ->join('teams', 'teams.id', '=', 'daily_shift_teams.team_id')
      ->where('daily_shifts.id', $daily_shift_id)
      ->get();

    return $teams;
  }

  public function getShiftSlotsByDailyShiftTeam($daily_shift_team_id)
  {

    $slots = null;
    $shift_detail = null;

    $shift_detail = Shift::select(
      'daily_shifts.id as daily_shift_id',
      'shifts.shift_code',
      'shifts.name',
      'shift_details.*'
    )
      ->join('shift_details', 'shift_details.shift_id', '=', 'shifts.id')
      ->join('daily_shifts', 'daily_shifts.shift_detail_id', '=', 'shift_details.id')
      ->join('daily_shift_teams', 'daily_shift_teams.daily_shift_id', '=', 'daily_shifts.id')
      ->where('daily_shift_teams.id', $daily_shift_team_id)
      ->first();
    if (!(is_null($shift_detail['daily_shift_id']))) {
      $slots = DailyScanningSlot::select('id as daily_scanning_slot_id', 'seq_no')->where('daily_shift_id', $shift_detail['daily_shift_id'])->get();
    }
    return response()->json(['ShiftDetail' => $shift_detail, 'DailyScanningSlot' => $slots], 200);
  }

  public function getEmployeeAllocationByProgress($daily_shift_team_id, $daily_scanning_slot_id, $progressed)
  {
    $employees = [];
    if (isset($progressed)) {
      if ($progressed == 1) {
        $employees = Employee::select(
          'employees.id as employee_id',
          'employees.emp_code',
          'employees.first_name',
          'employees.last_name',
          'daily_scanning_slot_employees.id as daily_scanning_slot_employee_id'
        )
          ->join('daily_scanning_slot_employees', 'daily_scanning_slot_employees.employee_id', 'employees.id')
          ->where('daily_scanning_slot_employees.daily_scanning_slot_id', $daily_scanning_slot_id)
          ->where('daily_scanning_slot_employees.daily_shift_team_id', $daily_shift_team_id)
          ->get();
      } else {
        $daily_shift_team = DailyShiftTeam::find($daily_shift_team_id);
        if (!(is_null($daily_shift_team))) {
          $employees = Employee::select(
            'employees.id as employee_id',
            'employees.emp_code',
            'employees.first_name',
            'employees.last_name'
          )
            ->where('base_team_id', $daily_shift_team->team_id)
            ->get();
        }
      }
      return $employees;
    } else {
      throw new \App\Exceptions\GeneralException("Progress information is not provided for the slot");
    }
  }

  public function getTargetInformationSetup($daily_shift_team_id)
  {
    $hasScanStarted = false;
    $daily_shift_team = DailyShiftTeam::findOrFail($daily_shift_team_id);
    $dtsts = DailyTeamSlotTarget::where('daily_shift_team_id', $daily_shift_team_id)->get();
    if ($dtsts->count() == 0) {
      $daily_scanning_slots = DailyScanningSlot::where('daily_shift_id', $daily_shift_team->daily_shift_id)->get(['daily_scanning_slots.*', 'daily_scanning_slots.id as daily_scanning_slot_id']);
    } else {
      $daily_scanning_slots = $dtsts;
      $hasScanStarted = (DailyTeamSlotTarget::where('daily_shift_team_id', $daily_shift_team_id)
        ->whereNotNull('actual')
        // ->where('actual', '<>', 0)
        ->get()->count() > 0) ? true : false;
    }
    $daily_shift = DailyShift::find($daily_shift_team->daily_shift_id);
    $shift_details = Shift::select(
      'daily_shifts.id as daily_shift_id',
      'shifts.shift_code',
      'shifts.name',
      'shift_details.*'
    )
      ->join('shift_details', 'shift_details.shift_id', '=', 'shifts.id')
      ->join('daily_shifts', 'daily_shifts.shift_detail_id', '=', 'shift_details.id')
      ->join('daily_shift_teams', 'daily_shift_teams.daily_shift_id', '=', 'daily_shifts.id')
      ->where('daily_shift_teams.id', $daily_shift_team_id)
      ->first();

    return [
      'DailyShiftTeam' => $daily_shift_team,
      'DailyScanningSlot' => $daily_scanning_slots,
      'DailyShift' => $daily_shift,
      'ShiftDetail' => $shift_details,
      'HasScanStarted' => $hasScanStarted
    ];
  }

  public function createTargetInformationSetup($daily_shift_team_id, $total_target, $slots,$planned_sah,$planned_efficient,$weekly_plan_pcs,$monthly_plan_pcs,$weekly_plan_hrs,$monthly_plan_hrs,$attend_hrs,$forecast_hrs,$plan_work_hrs)
  {
    function __getSumOfScannedSlots($scanning_slot_id)
    {
      return BundleTicket::where(['daily_scanning_slot_id', $scanning_slot_id, 'direction' => 'OUT'])->sum('scan_quantity');
    }

    try {

      DB::beginTransaction();

      DailyShiftTeamRepository::updateRec($daily_shift_team_id, ['total_target' => $total_target,'planned_efficient'=>$planned_efficient,'planned_sah'=>$planned_sah, 'weekly_plan_pcs'=>$weekly_plan_pcs,'monthly_plan_pcs'=>$monthly_plan_pcs,'weekly_plan_hrs'=>$weekly_plan_hrs,'monthly_plan_hrs'=>$monthly_plan_hrs,'attend_hrs'=>$attend_hrs,'forecast_hrs'=>$forecast_hrs,'plan_work_hrs'=>$plan_work_hrs]);

      $dtsts = DailyTeamSlotTarget::where('daily_shift_team_id', $daily_shift_team_id)->pluck('id')->toArray();
      if (sizeof($dtsts) > 0) {
        DailyTeamSlotTargetRepository::deleteRecs($dtsts);
      }

      $count = sizeof($slots);
      $quotient = intdiv($total_target, $count);
      for ($i = 0; $i < ($count - 1); $i++) {
        $rec = [
          'daily_scanning_slot_id' => $slots[$i]['daily_scanning_slot_id'],
          'daily_shift_team_id' => $daily_shift_team_id,
          'planned' => $quotient,
          'forecast' => $quotient,
          'revised' => $quotient,
          'seq_no' => $i + 1
        ];
        DailyTeamSlotTargetRepository::createRec($rec);
      }
      $qty = $quotient + ($total_target - $quotient * $count);
      $rec = [
        'daily_scanning_slot_id' => $slots[$count - 1]['daily_scanning_slot_id'],
        'daily_shift_team_id' => $daily_shift_team_id,
        'planned' => $qty,
        'forecast' => $qty,
        'revised' => $qty,
        'seq_no' => $count
      ];
      DailyTeamSlotTargetRepository::createRec($rec);

      DB::commit();
      return $this->getTargetInformationSetup($daily_shift_team_id);
    } catch (Exception $e) {
      DB::rollBack();
      throw new \App\Exceptions\GeneralException($e->getMessage());
    }
  }

  public function updateTargetInformationSetup($daily_shift_team_id, $total_target, $slots,$planned_sah,$planned_efficient,$weekly_plan_pcs,$monthly_plan_pcs,$weekly_plan_hrs,$monthly_plan_hrs,$attend_hrs,$forecast_hrs,$plan_work_hrs)
  {
    try {
      DB::beginTransaction();
      foreach ($slots as $slot) {
        $dtst = DailyTeamSlotTarget::where([
          'daily_shift_team_id' => $daily_shift_team_id,
          'daily_scanning_slot_id' => $slot['id']
        ])->first();
        $current_seq_no = $dtst->seq_no;

        $recs = DailyTeamSlotTarget::where(['daily_shift_team_id' => $daily_shift_team_id])->where('seq_no', '>', $current_seq_no)->whereNotNull('actual')->get();
        if (is_null($recs)) {
          throw new Exception("Scanning is in progress. Update is not allowed");
        }

        DailyTeamSlotTargetRepository::updateRec($dtst->id, ['forecast' => $slot['forecast'], 'revised' => $slot['forecast']]);
      }
        DailyShiftTeamRepository::updateRec($daily_shift_team_id, ['total_target' => $total_target,'planned_efficient'=>$planned_efficient,'planned_sah'=>$planned_sah, 'weekly_plan_pcs'=>$weekly_plan_pcs,'monthly_plan_pcs'=>$monthly_plan_pcs,'weekly_plan_hrs'=>$weekly_plan_hrs,'monthly_plan_hrs'=>$monthly_plan_hrs,'attend_hrs'=>$attend_hrs,'forecast_hrs'=>$forecast_hrs,'plan_work_hrs'=>$plan_work_hrs]);
      DB::commit();
      return response()->json(["status" => "success"], 200);
    } catch (Exception $e) {
      DB::rollBack();
      throw new \App\Exceptions\GeneralException($e->getMessage());
    }
  }
}
