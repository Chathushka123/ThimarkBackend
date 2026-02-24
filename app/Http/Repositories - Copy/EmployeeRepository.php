<?php

namespace App\Http\Repositories;

use Illuminate\Http\Request;
use App\Employee;
use App\Http\Resources\EmployeeResource;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use App\Http\Resources\EmployeeWithParentsResource;
use Illuminate\Validation\Rule;
use Exception;
use Illuminate\Support\Str;

use App\Http\Validators\EmployeeCreateValidator;
use App\Http\Validators\EmployeeUpdateValidator;
use Illuminate\Support\Facades\Log;

class EmployeeRepository
{
  public function show(Employee $employee)
  {
    return response()->json(
      [
        'status' => 'success',
        'data' => new EmployeeWithParentsResource($employee),
      ],
      200
    );
  }

  public static function createRec(array $rec)
  {
    $validator = Validator::make(
      $rec,
      EmployeeCreateValidator::getCreateRules()
    );
    if ($validator->fails()) {
      throw new Exception($validator->errors());
    }
    $rec['emp_code'] = Str::upper($rec['emp_code']);
    try {
      $model = Employee::create($rec);
    } catch (Exception $e) {
      throw new \App\Exceptions\GeneralException($e->getMessage());
    }
    return $model;
  }

  public static function updateRec($model_id, array $rec)
  {
    // if ($master_id == null) {
    $model = Employee::findOrFail($model_id);
    // } else {
    //   $model = Employee::where($parent_key, $master_id)
    //     ->where('id', $model_id)
    //     ->firstOrFail();
    // }
    // return $model;
    Utilities::hydrate($model, $rec);
    $validator = Validator::make(
      $rec,
      EmployeeUpdateValidator::getUpdateRules($model_id)
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
    Employee::destroy($recs);
  }

  public function getEmployeeTypes()
  {
    $ret = [];
    foreach (Employee::EMPLOYEE_TYPES as $key => $value) {
      $ret[] = ["key" => $key, "value" => $value];
    }
    return $ret;
  }
}
