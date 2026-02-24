<?php

namespace App\Http\Repositories;

use App\DailyScanningSlot;
use App\DailyScanningSlotEmployee;
use App\DailyShift;
use App\DailyShiftTeam;
use App\Employee;
use App\Exceptions\ConcurrencyCheckFailedException;
use Illuminate\Support\Facades\Validator;
use App\Http\Resources\DailyShiftWithParentsResource;

use App\Http\Validators\DailyShiftCreateValidator;
use Exception;
use App\Http\Validators\DailyShiftUpdateValidator;
use App\ShiftDetail;
use App\Team;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class DailyShiftRepository
{
  public function show(DailyShiftTeam $dailyShift)
  {
    return response()->json(
      [
        'status' => 'success',
        'data' => new DailyShiftWithParentsResource($dailyShift),
      ],
      200
    );
  }

  public static function createRec(array $rec)
  {
    $validator = Validator::make(
      $rec,
      DailyShiftCreateValidator::getCreateRules()

    );
    if ($validator->fails()) {
      throw new Exception($validator->errors());
    }
    try {
      $model = DailyShift::create($rec);
    } catch (Exception $e) {
      throw new \App\Exceptions\GeneralException($e->getMessage());
    }
    return $model;
  }

  public static function updateRec($model_id, array $rec)
  {

    $model = DailyShift::findOrFail($model_id);

    if (!$model->updated_at->eq(\Carbon\Carbon::parse($rec['updated_at']))) {
      $entity = (new \ReflectionClass($model))->getShortName();
      throw new ConcurrencyCheckFailedException($entity);
    }
    
    Utilities::hydrate($model, $rec);
    

    $validator = Validator::make(
      $rec,
      DailyShiftUpdateValidator::getUpdateRules($model_id)
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
    DailyShift::destroy($recs);
  }

  public static function createShiftTeamsSlotsPerDay($current_date, $shift_detail, array $team_ids, array $slots)
  {

    try {
      DB::beginTransaction();

      try {
        $start_date_time = new \Carbon\Carbon($current_date . ' ' . $shift_detail['start_time']);
      } catch (Exception $e) {
        throw new Exception("Invalid start_time in shift details.");
      }
      try {
        $end_date_time = new \Carbon\Carbon($current_date . ' ' . $shift_detail['end_time']);
      } catch (Exception $e) {
        throw new Exception(" Invalid start_time in shift details.");
      }

      //if($shift_detail['mid_night_cross'] == 0){
      // if ($shift_detail['start_time'] > $shift_detail['end_time']) {
      //   throw new Exception('Unless its a mid night cross, end time cannot be smaller than start time');
      // }
      //}

      $model = self::createRec([
        'current_date' => $current_date,
        'shift_detail_id' => $shift_detail['shift_detail_id'],
        'start_date_time' => $start_date_time,
        'end_date_time' => $end_date_time,
        'break' => $shift_detail['break'],
        'holiday' => $shift_detail['holiday'],
        'over_time_hours' => $shift_detail['over_time_hours'],
        'mid_night_cross' => $shift_detail['mid_night_cross']
      ]);


      if (empty($slots)) {
        throw new Exception('Incomplete information about Slots');
      } else {
        foreach ($slots as $slot) {
          try {
            $from_date_time = new \Carbon\Carbon($current_date . ' ' . $slot['from_time']);
          } catch (Exception $e) {
            throw new Exception("start time is invalid in slot no - " . $slot['seq_no']);
          }
          try {
            $to_date_time = new \Carbon\Carbon($current_date . ' ' . $slot['to_time']);
          } catch (Exception $e) {
            throw new Exception("end time is invalid in slot no - " . $slot['seq_no']);
          }

          $slot_model = DailyScanningSlotRepository::createRec([
            'from_date_time' => $from_date_time,
            'to_date_time' => $to_date_time,
            'duration_hours' => $slot['duration_hours'],
            'seq_no' => $slot['seq_no'],
            'daily_shift_id' => $model->id
          ]);
        }
      }

      $saved_slots = DailyScanningSlot::select('id')->where('daily_shift_id', $model->id)->get();

      if (!(empty($team_ids))) {
        foreach ($team_ids as $team_id) {

          $dst_model = DailyShiftTeamRepository::createRec([
            'current_date' => $current_date,
            'team_id' => $team_id,
            'daily_shift_id' => $model->id,
            'start_date_time' => $start_date_time,
            'end_date_time' => $end_date_time,
          ]);

          foreach ($saved_slots as $save_slot) {
            $employees = Employee::where('base_team_id', $team_id)->get();
            if ($employees->count() <= 0) {
              $team = Team::find($team_id);
              throw new Exception('Employee are not assigned to the team - ' . $team->code);
            } else {
              foreach ($employees as $employee) {
                $slot_employee_modle = DailyScanningSlotEmployeeRepository::createRec([
                  'employee_id' => $employee->id,
                  'daily_scanning_slot_id' => $save_slot->id,
                  'daily_shift_team_id' => $dst_model->id
                ]);
              }
            }
          }
        }
      }
      DB::commit();
      return response()->json(["status" => "success"], 200);
    } catch (Exception $e) {
      DB::rollBack();
      throw new \App\Exceptions\GeneralException($e->getMessage());
    }
  }

  public function getDailyShiftWithChildren($current_date, $shift_detail_id)
  {

    $daily_shift = null;
    $slots = [];
    $teams = [];
    $shift_detail = null;

    $daily_shift = DailyShift::where('current_date', $current_date)->where('shift_detail_id', $shift_detail_id)->first();
    if (!(is_null($daily_shift))) {
      $slots = DailyScanningSlot::where('daily_shift_id', $daily_shift->id)->get();

      $teams = DailyShiftTeam::select(
        'daily_shift_teams.id as daily_shift_team_id',
        'teams.id as team_id',
        'teams.code as team_code'
      )
        ->join('teams', 'teams.id', '=', 'daily_shift_teams.team_id')
        ->where('daily_shift_id',  $daily_shift->id)->get();
    } else {
      $shift_detail = ShiftDetail::find($shift_detail_id);
    }
    return response()->json(['DailyShift' => $daily_shift, 'DailyScanningSlot' => $slots, 'DailyShiftTeam' => $teams, 'ShiftDetail' => $shift_detail], 200);
  }

  public static function modifyShiftTeamsSlotsPerDay($daily_shift, array $team_ids, array $deleted_daily_shift_team_ids, array $slots)
  {
    try {
      DB::beginTransaction();
      //update daily shift
      $model = DailyShift::find($daily_shift['id']);
      $current_date = $model->current_date;
   
      
      $daily_shift['start_date_time'] = self::_validateDateTime($current_date, $daily_shift['start_time'], "Invalid start_time in shift details");
      $daily_shift['end_date_time'] = self::_validateDateTime($model->end_date_time, $daily_shift['end_time'], "Invalid start_time in shift details");
      //$daily_shift['end_date_time'] = $model->end_date_time;
      
      
      self::updateRec($daily_shift['id'], $daily_shift);
      

      //update slot since its only a content change no dependancy

      $slots_upd = $slots["UPD"];

      foreach ($slots_upd as $slot_upd) {

        $from_date = $current_date;
        $to_date = $current_date;
        if($model->mid_night_cross == 1){
          if(intval(substr($slot_upd['from_time'],0,2)) >= 0 && intval(substr($slot_upd['from_time'],0,2)) < 12){
              $from_date = $model->end_date_time;
          }
          if(intval(substr($slot_upd['to_time'],0,2)) >= 0 && intval(substr($slot_upd['to_time'],0,2)) < 12){
            $to_date = $model->end_date_time;
          }
        }

        $from_date_time =   self::_validateDateTime($from_date, $slot_upd['from_time'], "start time is invalid in slot no - " . $slot_upd['seq_no']);
        $to_date_time = self::_validateDateTime($to_date, $slot_upd['to_time'], "end time is invalid in slot no - " . $slot_upd['seq_no']);
        $slot_upd['from_date_time'] = $from_date_time;
        $slot_upd['to_date_time'] = $to_date_time;
        DailyScanningSlotRepository::updateRec($slot_upd['id'], $slot_upd);
      }

      //remove all the deleted teams as the first step
      foreach ($deleted_daily_shift_team_ids as $daily_shift_team) {
        DailyScanningSlotEmployee::where('daily_shift_team_id', $daily_shift_team)->delete();
      }
      DailyShiftTeamRepository::deleteRecs($deleted_daily_shift_team_ids);

      // remove any slots deleted next
      $slots_del = $slots["DEL"];

      foreach ($slots_del as $slot_del) {
        DailyScanningSlotEmployee::where('daily_scanning_slot_id', $slot_del)->delete();
      }
      DailyScanningSlotRepository::deleteRecs($slots_del);


      //create any new slots if exists
      $slots_cr = $slots["CRE"];
      foreach ($slots_cr as $slot_cr) {
        $from_date = $current_date;
        $to_date = $current_date;
        if($model->mid_night_cross == 1){
          if(intval(substr($slot_cr['from_time'],0,2)) >= 0 && intval(substr($slot_cr['from_time'],0,2)) < 12){
              $from_date = $model->end_date_time;
          }
          if(intval(substr($slot_cr['to_time'],0,2)) >= 0 && intval(substr($slot_cr['to_time'],0,2)) < 12){
            $to_date = $model->end_date_time;
          }
        }

        $from_date_time =   self::_validateDateTime($from_date, $slot_cr['from_time'], "start time is invalid in slot no - " . $slot_cr['seq_no']);
        $to_date_time = self::_validateDateTime($to_date, $slot_cr['to_time'], "end time is invalid in slot no - " . $slot_cr['seq_no']);

        $slot_model = DailyScanningSlotRepository::createRec([
          'from_date_time' => $from_date_time,
          'to_date_time' => $to_date_time,
          'duration_hours' => $slot_cr['duration_hours'],
          'seq_no' => $slot_cr['seq_no'],
          'daily_shift_id' => $daily_shift['id']
        ]);

        //create employees for the added new slots
        $daily_shift_teams = DailyShiftTeam::select('id', 'team_id')->where('daily_shift_id', $daily_shift['id'])->get();

        foreach ($daily_shift_teams as $daily_shift_team) {
          $employees = Employee::where('base_team_id',  $daily_shift_team->team_id)->get();
          if ($employees->count() <= 0) {
            $team = Team::find($daily_shift_team->team_id);
            throw new Exception('Employee are not assigned to the team - ' . $team->code);
          } else {
            foreach ($employees as $employee) {
              $slot_employee_modle = DailyScanningSlotEmployeeRepository::createRec([
                'employee_id' => $employee->id,
                'daily_scanning_slot_id' => $slot_model->id,
                'daily_shift_team_id' =>  $daily_shift_team->id
              ]);
            }
          }
        }
      }

      //create any new teams added  and run for all slots to assign employees    
      $teams_cr = $team_ids;
      foreach ($teams_cr as $team_cr) {
        $start_date_time =   self::_validateDateTime($current_date, $daily_shift['start_time'], "Invalid daily shift time");
        $end_date_time = self::_validateDateTime($current_date, $daily_shift['end_time'], " Invalid daily shift time");

        $dst_model = DailyShiftTeamRepository::createRec([
          'current_date' => $current_date,
          'team_id' => $team_cr,
          'daily_shift_id' => $daily_shift['id'],
          'start_date_time' =>  $start_date_time,
          'end_date_time' => $end_date_time,
        ]);

        $existing_slots = DailyScanningSlot::select('id')->where('daily_shift_id',  $daily_shift['id'])->get();
        foreach ($existing_slots as $existing_slot) {
          $employees = Employee::where('base_team_id', $team_cr)->get();
          if ($employees->count() <= 0) {
            $team = Team::find($team_cr);
            throw new Exception('Employee are not assigned to the team - ' . $team->code);
          } else {
            foreach ($employees as $employee) {
              $slot_employee_modle = DailyScanningSlotEmployeeRepository::createRec([
                'employee_id' => $employee->id,
                'daily_scanning_slot_id' => $existing_slot->id,
                'daily_shift_team_id' => $dst_model->id
              ]);
            }
          }
        }
      }

      self::validateShiftAndSlot($daily_shift);
      DB::commit();
      return response()->json(["status" => "success"], 200);
    } catch (Exception $e) {
      DB::rollBack();
      throw new \App\Exceptions\GeneralException($e->getMessage());
    }
  }

  public static function validateShiftAndSlot($daily_shift){
    $shift = DailyShift::find($daily_shift['id']);

    $slot = DailyScanningSlot::where('daily_shift_id',$daily_shift)->where('to_date_time','>',$shift->end_date_time)->first();

    if(!is_null($slot)){
      throw new Exception('Slot Time Range and Shift Time Range Need to be Equal');
    }


  }

  public static function _validateDateTime($input_date, $input_time, $error_text)
  {
    try {
      $calc_date = new \Carbon\Carbon($input_date->format('Y-m-d') . ' ' . $input_time);
      return $calc_date;
    } catch (Exception $e) {
      throw new Exception($error_text);
    }
  }

  public static function getShiftPerDay($current_date)
  {
    $shifts = DailyShift::select(
      'shifts.id as shift_id',
      'shifts.shift_code as shift_code',
      'shifts.name',
      'daily_shifts.*'
    )
      ->join('shift_details', 'daily_shifts.shift_detail_id', '=', 'shift_details.id')
      ->join('shifts', 'shift_details.shift_id', '=', 'shifts.id')
      ->where('daily_shifts.current_date', $current_date)
      ->get();

    return $shifts;
  }

  public function generateSlots($shift_detail_id, $current_date)
  {
    $current_date = Carbon::parse($current_date)->format('Y-m-d');
    $daily_scanning_slots = [];
    $shift_detail = ShiftDetail::find($shift_detail_id);
    $daily_shift = DailyShift::where([
      'shift_detail_id' => $shift_detail_id,
      'current_date' => $current_date
    ])->first();
    if (is_null($daily_shift)) {
      $start_time = Carbon::parse($current_date . ' ' . $shift_detail->start_time); // init with Carbon::now() to get a date Object
      if ($shift_detail->overlap_two_days) {
        $end_time = Carbon::parse($current_date . ' ' . $shift_detail->end_time)->addDays(1);
        print_r($end_time);
      } else {
        $end_time = Carbon::parse($current_date . ' ' . $shift_detail->end_time);
      }
      $daily_shift = DailyShiftRepository::createRec([
        'current_date' => $current_date,
        'shift_detail_id' => $shift_detail_id,
        'start_date_time' => $start_time,
        'end_date_time' => $end_time,
        'break' => $shift_detail->break_hours,
        'mid_night_cross' => $shift_detail->overlap_two_days
      ]);
      $daily_shift->refresh();
      for ($i = 1; $i <= $shift_detail->no_of_slots; $i++) {
        if ($i == 1) {
          $start_time = $daily_shift->start_date_time;
          $end_time = $daily_shift->start_date_time->addMinutes($shift_detail->slot_duration * 60);
        } else {
          $start_time = $end_time->copy();
          $end_time = $end_time->copy()->addMinutes($shift_detail->slot_duration * 60);
        }
        $daily_scanning_slots[] = DailyScanningSlotRepository::createRec([
          'from_date_time' => $start_time,
          'to_date_time' => $end_time,
          'duration_hours' => $end_time->diffInMinutes($start_time, true) / 60,
          'daily_shift_id' => $daily_shift->id,
          'seq_no' => $i
        ]);
      }
    } else {
      $daily_scanning_slots = DailyScanningSlot::where('daily_shift_id', $daily_shift->id)->get();
    }
    $daily_shift_teams = DailyShiftTeam::where('daily_shift_id', $daily_shift->id)->with('team')->get();
    return response()->json(['DailyShift' => $daily_shift, 'DailyScanningSlot' => $daily_scanning_slots, 'DailyShiftTeam' => $daily_shift_teams], 200);
  }


}
