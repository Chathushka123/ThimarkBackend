<?php

namespace App\Http\Repositories;

// use App\CutPlan;
// use App\Fpo;
// use App\FpoCutPlan;

use App\BundleTicket;
use App\DailyScanningSlot;
use App\DailyScanningSlotEmployee;
use App\Employee;
use App\Exceptions\ConcurrencyCheckFailedException;
// use App\HashStore;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use App\Http\Resources\DailyScanningSlotEmployeeWithParentsResource;
use Illuminate\Validation\Rule;
use Exception;

use App\Http\Validators\DailyScanningSlotEmployeeCreateValidator;
use App\Http\Validators\DailyScanningSlotEmployeeUpdateValidator;
use App\Soc;
use Illuminate\Log\Logger;
use Illuminate\Support\Facades\Log;

class DailyScanningSlotEmployeeRepository
{
  const DISCARD_COLUMNS = [];

  const FIELD_MAPPING = [];

  public function show(DailyScanningSlotEmployee $dailyScanningSlotEmployee)
  {
    return response()->json(
      [
        'status' => 'success',
        'data' => new DailyScanningSlotEmployeeWithParentsResource($dailyScanningSlotEmployee),
      ],
      200
    );
  }

  public static function createRec(array $rec)
  {
        
    $validator = Validator::make(
      $rec,
      DailyScanningSlotEmployeeCreateValidator::getCreateRules()
    );
    if ($validator->fails()) {
      throw new Exception($validator->errors());
    }

    //Check for Unique
    $exist_model =  DailyScanningSlotEmployee::where(['employee_id' => $rec['employee_id'], 
    'daily_scanning_slot_id' => $rec['daily_scanning_slot_id'], 
    'daily_shift_team_id' => $rec['daily_shift_team_id']])
    ->first();
    
    if(!(is_null($exist_model)))
    {
      throw new Exception('Employee is already assigned to the Team and Slot for the given shift');
    }

    try {
      $model = DailyScanningSlotEmployee::create($rec);
    } catch (Exception $e) {
      throw new \App\Exceptions\GeneralException($e->getMessage());
    }
    return $model;
  }

  public static function updateRec($model_id, array $rec)
  {
    $model = DailyScanningSlotEmployee::findOrFail($model_id);

    if (!$model->updated_at->eq(\Carbon\Carbon::parse($rec['updated_at']))) {
      $entity = (new \ReflectionClass($model))->getShortName();
      throw new ConcurrencyCheckFailedException($entity);

    }
    Utilities::hydrate($model, $rec);

    $validator = Validator::make(
      $rec,
      DailyScanningSlotEmployeeUpdateValidator::getUpdateRules($model_id)
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
      $model = DailyScanningSlotEmployee::findOrFail($id);
      DailyScanningSlotEmployee::destroy([$id]);
    }
  }

  public static function assignEmployee(array $employees, $daily_scanning_slot_id, $daily_shift_team_id)
  {
    try {
      DB::beginTransaction();

      $employees_upd = $employees["UPD"];
      $employees_cre = $employees["CRE"];
      $employees_del = $employees["DEL"];

      $targe_slot = DailyScanningSlot::find($daily_scanning_slot_id);

      //if the current slot has start avoid change
      
      $bundle_tickets = BundleTicket::where('daily_scanning_slot_id', $daily_scanning_slot_id)->get();
      if ($bundle_tickets->count() > 0) {
        throw new Exception("Scanning of the given slot is started, not allowed to swap team members");
      }
      
      //get all slots from this point and forward

      $slots_to_assign = DailyScanningSlot::where('seq_no', '>=', $targe_slot->seq_no)
        ->where('daily_shift_id', $targe_slot->daily_shift_id)
        ->where('daily_shift_id', $targe_slot->daily_shift->id)
        ->pluck('id')
        ->toArray();

      foreach ($employees_upd as $emp_rem) {
        //For already assign employees delete from current team hence all slots
        $current_slot_employee = DailyScanningSlotEmployee::find($emp_rem['daily_scanning_slot_employee_id']);

        $daily_scanning_slot_employees = DailyScanningSlotEmployee::where("employee_id", $emp_rem['employee_id'])
        ->where('daily_shift_team_id', $current_slot_employee->daily_shift_team_id)
          ->whereIn('daily_scanning_slot_id', $slots_to_assign)
          ->pluck('id')
          ->toArray();
        
        self::deleteRecs($daily_scanning_slot_employees);
      }

      //crete new records for both allocated and non allocated

      foreach ($slots_to_assign as $slot) {

        //For allocated
        foreach ($employees_upd as $emp_upd) {
          $model = self::createRec([
            'employee_id' => $emp_upd['employee_id'],
            'daily_shift_team_id' => $daily_shift_team_id,
            'daily_scanning_slot_id' => $slot
          ]);
        }
        //For unalloacted        
        foreach ($employees_cre as $emp_cre) {
          $model = self::createRec([
            'employee_id' => $emp_cre['employee_id'],
            'daily_shift_team_id' => $daily_shift_team_id,
            'daily_scanning_slot_id' => $slot,
          ]);
        }
      }

      // remove reqested employees from current and next slots

      foreach ($employees_del as $emp_del) {
        // delete from current team hence all slots
        $current_slot_employee = DailyScanningSlotEmployee::find($emp_del['daily_scanning_slot_employee_id']);

        $daily_scanning_slot_employees = DailyScanningSlotEmployee::where("employee_id", $emp_del['employee_id'])
          ->where('daily_shift_team_id', $current_slot_employee->daily_shift_team_id)
          ->whereIn('daily_scanning_slot_id', $slots_to_assign)
          ->pluck('id')
          ->toArray(); 

        self::deleteRecs($daily_scanning_slot_employees);
      }

      DB::commit();
      return response()->json(["status" => "success"], 200);
    } catch (Exception $e) {
      DB::rollBack();
      throw new \App\Exceptions\GeneralException($e->getMessage());
    }

  }

  public static function getSearchByAllocation($daily_scanning_slot_id, $daily_shift_team_id)
  {
    //get unallocated Employees for this slot
    $unallocated_employees = DB::table('employees')->select(
    DB::raw("NULL as daily_scanning_slot_employee_id"),
    'employees.id as employee_id',
    'employees.emp_code',
    'employees.first_name',
    'employees.last_name',
    DB::raw("'No' as allocated"),
    DB::raw("NULL as current_team")
  )
    ->whereNotIn(
      'employees.id',
      function ($query) use ($daily_scanning_slot_id) {
        $query->select('employee_id')
        ->from('daily_scanning_slot_employees')
        ->where('daily_scanning_slot_id', $daily_scanning_slot_id);
         }
    )
    ->get();
    
    //get allocated Employees
    
    $allocated_employees = DB::table('employees')->select(
    'daily_scanning_slot_employees.id as daily_scanning_slot_employee_id',
    'employees.id as employee_id',
    'employees.emp_code',
    'employees.first_name',
    'employees.last_name',
    DB::raw("'Yes' as allocated"),
    DB::raw('(select teams.code 
            from teams , daily_shift_teams 
            where teams.id = daily_shift_teams.team_id 
            and  daily_shift_teams.id = daily_scanning_slot_employees.daily_shift_team_id) as current_team') 
  )
    ->join('daily_scanning_slot_employees', 'daily_scanning_slot_employees.employee_id','=', 'employees.id')
    ->where('daily_scanning_slot_employees.daily_scanning_slot_id', $daily_scanning_slot_id)
    ->whereNotIn(
        'employees.id', 
        function ($query) use ($daily_shift_team_id, $daily_scanning_slot_id) {
          $query->select('employee_id')
          ->from('daily_scanning_slot_employees')
          ->where('daily_scanning_slot_id', $daily_scanning_slot_id)
          ->whereIn('daily_shift_team_id', [$daily_shift_team_id]);
        }
      )
      ->get();

      //return $allocated_employees;
      return $unallocated_employees->concat($allocated_employees);
      }

  public static function getSearchResultsByAllocation(
      $emloyee_code,
      $first_name,
      $last_name,
      $allocated,
      $team_code,
      $daily_scanning_slot_id,
      $daily_shift_team_id
  ) 
  {    

    //get unallocated Employees for this slot
    $unallocated_employees = DB::table('employees')->select(
      DB::raw("NULL as daily_scanning_slot_employee_id"),
      'employees.id as employee_id',
      'employees.emp_code',
      'employees.first_name',
      'employees.last_name',
      DB::raw("'No' as allocated"),
      DB::raw("NULL as current_team")
    )
    ->whereNotIn(
        'employees.id',
        function ($query) use ($daily_scanning_slot_id) {
          $query->select('employee_id')
          ->from('daily_scanning_slot_employees')
          ->where('daily_scanning_slot_id', $daily_scanning_slot_id);
        }
      )
      ->where('employees.emp_code', 'LIKE', (is_null($emloyee_code) ? '%' :  '%' . $emloyee_code . '%'))
      ->where('employees.first_name', 'LIKE', (is_null($first_name) ? '%' :  '%' . $first_name . '%'))
      ->where('employees.last_name', 'LIKE', (is_null($last_name) ? '%' :  '%' . $last_name . '%'))
      ->whereRaw('"NO" = '.  (is_null($allocated) ? '"NO"' : ('"'. $allocated . '"') ))
      ->whereRaw('0 = '.  (is_null($team_code) ? '0' : 1 )) //if team code has a value avoid resultset from this unallocated query
      ->get();
      
      //get allocated Employees
      
      $allocated_employees = DB::table('employees')->select(
      'daily_scanning_slot_employees.id as daily_scanning_slot_employee_id',
      'employees.id as employee_id',
      'employees.emp_code',
      'employees.first_name',
      'employees.last_name',
      DB::raw("'Yes' as allocated"),
      DB::raw('(select teams.code 
              from teams , daily_shift_teams 
              where teams.id = daily_shift_teams.team_id 
              and  daily_shift_teams.id = daily_scanning_slot_employees.daily_shift_team_id) as current_team') 
    )
      ->join('daily_scanning_slot_employees', 'daily_scanning_slot_employees.employee_id','=', 'employees.id')
      ->where('daily_scanning_slot_employees.daily_scanning_slot_id', $daily_scanning_slot_id)
      ->whereNotIn(
        'employees.id', 
        function ($query) use ($daily_shift_team_id, $daily_scanning_slot_id) {
          $query->select('employee_id')
          ->from('daily_scanning_slot_employees')
          ->where('daily_scanning_slot_id', $daily_scanning_slot_id)
          ->whereIn('daily_shift_team_id', [$daily_shift_team_id]);
        }
      )
      ->where('employees.emp_code', 'LIKE', (is_null($emloyee_code) ? '%' :  '%' . $emloyee_code . '%'))
      ->where('employees.first_name', 'LIKE', (is_null($first_name) ? '%' :  '%' . $first_name . '%'))
      ->where('employees.last_name', 'LIKE', (is_null($last_name) ? '%' :  '%' . $last_name . '%'))
      ->whereRaw('"YES" = '. (is_null($allocated) ? '"YES"' : ('"'. $allocated . '"')))
      ->whereExists(function ($query) use($team_code){
                        $query->select('teams.code') 
                        ->from (DB::raw('teams,  daily_shift_teams')) 
                        ->whereRaw('teams.id = daily_shift_teams.team_id') 
                        ->whereRaw('daily_shift_teams.id = daily_scanning_slot_employees.daily_shift_team_id')
                        ->whereRaw('teams.code LIKE ' . (is_null($team_code) ? '"%"' :  '"%' . $team_code . '%"'));

            })
        ->get();

      return $unallocated_employees->concat($allocated_employees);

  } 
  

  
}
