<?php

namespace App\Http\Repositories;

use App\Employee;
use Illuminate\Http\Request;
use App\Team;
use App\Http\Resources\TeamResource;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use App\Http\Resources\TeamWithParentsResource;
use Illuminate\Validation\Rule;
use Exception;
use Illuminate\Support\Str;

use App\Http\Validators\TeamCreateValidator;
use App\Http\Validators\TeamUpdateValidator;
use Illuminate\Support\Facades\Log;
use PDF;

class TeamRepository
{
  public function show(Team $team)
  {
    return response()->json(
      [
        'status' => 'success',
        'data' => new TeamWithParentsResource($team),
      ],
      200
    );
  }

  public static function createRec(array $rec)
  {
    $validator = Validator::make(
      $rec,
      TeamCreateValidator::getCreateRules()
    );
    if ($validator->fails()) {
      throw new Exception($validator->errors());
    }
    $rec['code'] = Str::upper($rec['code']);
    // try {
    $model = Team::create($rec);
    // } catch (Exception $e) {
    //   throw new \App\Exceptions\GeneralException($e->getMessage());
    // }
    return $model;
  }

  public static function updateRec($model_id, array $rec)
  {
    $model = Team::findOrFail($model_id);
    Utilities::validateCode($model->code, $rec['code'], "Code");

    if (!$model->updated_at->eq(\Carbon\Carbon::parse($rec['updated_at']))) {
      $entity = (new \ReflectionClass($model))->getShortName();
      throw new \App\Exceptions\ConcurrencyCheckFailedException($entity);
    }
    Utilities::hydrate($model, $rec);
    $validator = Validator::make(
      $rec,
      TeamUpdateValidator::getUpdateRules($model_id)
    );
    if ($validator->fails()) {
      throw new Exception($validator->errors());
    }
    // try {
    $model->update($rec);
    // } catch (Exception $e) {
    //   throw new Exception(json_encode([$e->getMessage()]));
    // }
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
    Team::destroy($recs);
  }

  public static function getVsmList()
  {
    $vsm = [];
    
    // $vsm = Team::whereNotNull('vsm_code')->where('vsm_code', '!=', '')->distinct()->pluck('vsm_code')->toArray();
    $vsm = DB::table('teams')
    ->select('teams.vsm_code','employees.first_name')
    ->join('employees', 'employees.emp_code', '=', 'teams.vsm_code')
    ->where('vsm_code', '!=', '')
    ->distinct('teams.vsm_code')
    ->get();
    return response()->json($vsm, 200);
  }

  public static function getSupervisorList($vsm_code)
  {
    $supervisor_codes = [];
    if ($vsm_code == 'ALL') {
      $supervisor_ids = Team::whereNotNull('supervisor_id')->where('supervisor_id', '!=', '')->pluck('supervisor_id')->distinct()->toArray();
    } else {
      $supervisor_ids = Team::whereNotNull('supervisor_id')->where('supervisor_id', '!=', '')->where('vsm_code', $vsm_code)->pluck('supervisor_id');
    }
    Log::info($supervisor_ids);
    //$supervisor_codes = Employee::whereNotNull('emp_code')->where('emp_code', '!=', '')->whereIn('id', $supervisor_ids)->pluck('emp_code')->toArray();
    $supervisor_codes = DB::table('employees')
    ->select('employees.emp_code','employees.first_name')
    ->whereNotNull('emp_code')
    ->where('emp_code', '!=', '')
    ->whereIn('id', $supervisor_ids)
    ->distinct()
    ->get();
    return response()->json($supervisor_codes, 200);
  }


  public static function ReCalculateTargetData($vsm_code)
  {
    return response()->json(["status" => "success"], 200);
  }

  public function getTeamReport(){
    
    $team = DB::table('teams')
    ->select('*')
    ->orderby('code','ASC')  
    ->get();
    $data = ['team' => $team];
    
    $pdf = PDF::loadView('print.team_report', $data);
    return $pdf->stream('team_report_' . date('Y_m_d_H_i_s') . '.pdf');
  }



}
