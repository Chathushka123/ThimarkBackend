<?php

namespace App\Http\Repositories;

use App\DailyShiftTeam;
use App\DailyTeamEmployee;
use App\Employee;
use Illuminate\Support\Facades\Validator;
use App\Http\Resources\DailyTeamEmployeeWithParentsResource;
use Exception;

use App\Http\Validators\DailyTeamEmployeeCreateValidator;
use App\Http\Validators\DailyTeamEmployeeUpdateValidator;
use App\ShiftDetail;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class DailyTeamEmployeeRepository
{
  public function show(DailyTeamEmployee $dailyEmpAllocation)
  {
    return response()->json(
      [
        'status' => 'success',
        'data' => new DailyTeamEmployeeWithParentsResource($dailyEmpAllocation),
      ],
      200
    );
  }

  public static function createRec(array $rec)
  {
    $validator = Validator::make(
      $rec,
      DailyTeamEmployeeCreateValidator::getCreateRules()
    );
    if ($validator->fails()) {
      throw new Exception($validator->errors());
    }
    try {
      $model = DailyTeamEmployee::create($rec);
    } catch (Exception $e) {
      throw new \App\Exceptions\GeneralException($e->getMessage());
    }
    return $model;
  }

  public static function updateRec($model_id, array $rec)
  {
    $model = DailyTeamEmployee::findOrFail($model_id);
    Utilities::hydrate($model, $rec);
    $validator = Validator::make(
      $rec,
      DailyTeamEmployeeUpdateValidator::getUpdateRules($model_id)
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
    DailyTeamEmployee::destroy($recs);
  }

  public function allocateEmployees($team_id, $shift_detail_id, $current_date, array $employee_ids, array $shift_details)
  {
    try {
      DB::beginTransaction();

      //Add list of employees if not passed
      if(empty($employee_ids)){
        $employee_ids = Employee::where('base_team_id', $team_id)->pluck('id')->toArray();
        if (empty($employee_ids))
        {
          throw new Exception("Employees are not attached to the given team");
        }
      }
       $dst = null;
      // look in daily_shift_team (teamid, shift detail id, date)
      $dst = DailyShiftTeam::whereDate('current_date', '=', $current_date)
        ->where([
          'team_id' => $team_id,
          'shift_detail_id' => $shift_detail_id
        ])->first();
      if (isset($dst)) {
        $this->addDailyTeamEmployees($employee_ids, $dst->id);
      } else {
        try {
          $start_date_time = new \Carbon\Carbon($shift_details['start_date'] . ' ' . $shift_details['start_time']);
        } catch (Exception $e) {
          throw new \App\Exceptions\GeneralException("start_date or start_time is invalid.");
        }
        try {
          $end_date_time = new \Carbon\Carbon($shift_details['end_date'] . ' ' . $shift_details['end_time']);
        } catch (Exception $e) {
          throw new \App\Exceptions\GeneralException("end_date or end_time is invalid.");
        }
        $dst = DailyShiftTeamRepository::createRec([
          'current_date' => $current_date,
          'team_id' => $team_id,
          'shift_detail_id' => $shift_detail_id,
          'start_date_time' => $start_date_time,
          'end_date_time' => $end_date_time,
          'break' => $shift_details['break'],
          'scan_frequency' => null,
          'holiday' => null
        ]);
        // insert to DTE
        $this->addDailyTeamEmployees($employee_ids, $dst->id);
      }
      DB::commit();
      return response()->json(["status" => "success"], 200);
    }
     catch (Exception $e) {
      DB::rollBack();
      throw new \App\Exceptions\GeneralException($e->getMessage());
    }
  }

  public function reallocateEmployees($current_daily_shift_team_id, $team_id, $shift_detail_id, $current_date, array $employee_ids)
  {
    try {
      DB::beginTransaction();
      $this->removeDailyTeamEmployees($employee_ids, $current_daily_shift_team_id);
      $dst = null;
      // look in daily_shift_team (teamid, shift detail id, date)
      $dst = DailyShiftTeam::whereDate('current_date', '=', $current_date)
        ->where([
          'team_id' => $team_id,
          'shift_detail_id' => $shift_detail_id
        ])->first();
      Log::info('-------------------------------');
      Log::info($current_date);
      Log::info($team_id);
      Log::info($shift_detail_id);
      Log::info(DailyShiftTeam::where([
        'team_id' => $team_id,
        'shift_detail_id' => $shift_detail_id
      ])->first());
      Log::info('-------------------------------');

      if (!isset($dst)) {
        $sd = ShiftDetail::find($shift_detail_id);
        if (isset($sd)) {
          // 1. create new in daily_shift_team (look in shift_detail for necessary info)
          $start_date_time = new \Carbon\Carbon($current_date . ' ' . $sd->start_time);
          $end_date_time = new \Carbon\Carbon($current_date . ' ' . $sd->end_time);
          $break = $sd->break_hours;
          $dst = DailyShiftTeamRepository::createRec([
            'current_date' => $current_date,
            'team_id' => $team_id,
            'shift_detail_id' => $shift_detail_id,
            'start_date_time' => $start_date_time,
            'end_date_time' => $end_date_time,
            'break' => $break,
            'scan_frequency' => null,
            'holiday' => null
          ]);
        } else {
          throw new \App\Exceptions\GeneralException("Shift Detail [" . $shift_detail_id . "] cannot be found.");
        }
      }
      // 2. create records in daily_team_employee
      $this->addDailyTeamEmployees($employee_ids, $dst->id);
      DB::commit();
      return response()->json(["status" => "success"], 200);
    } catch (Exception $e) {
      DB::rollBack();
      throw $e;
    }
  }

  private function addDailyTeamEmployees(array $employee_ids, $daily_shift_team_id)
  {
    $dte_arr = [];
    foreach ($employee_ids as $employee_id) {
      $dte_arr[] = ['daily_shift_team_id' => $daily_shift_team_id, 'employee_id' => $employee_id];
    }
    if (!empty($employee_ids)) {
      $this::createMultipleRecs(null, $dte_arr);
    }
  }

  private function removeDailyTeamEmployees(array $employee_ids, $daily_shift_team_id)
  {
    $ids = DailyTeamEmployee::where('daily_shift_team_id', $daily_shift_team_id)
      ->whereIn('employee_id', array_values($employee_ids))
      ->pluck('id')->toArray();

    $this::deleteRecs($ids);
  }
}
