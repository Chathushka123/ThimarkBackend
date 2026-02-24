<?php

namespace App\Http\Repositories;

use Illuminate\Http\Request;
use App\IntegrationLog;
use App\Http\Resources\IntegrationLogResource;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use App\Http\Resources\IntegrationLogWithParentsResource;
use Illuminate\Validation\Rule;
use Carbon\Carbon;
use Exception;

use App\Http\Validators\IntegrationLogCreateValidator;
use App\Http\Validators\IntegrationLogUpdateValidator;

class IntegrationLogRepository
{
  public function show(IntegrationLog $integrationlog)
  {
    return response()->json(
      [
        'status' => 'success',
        'data' => new IntegrationLogWithParentsResource($integrationlog),
      ],
      200
    );
  }

  public static function createRec(array $rec)
  {
    $validator = Validator::make(
      $rec,
      IntegrationLogCreateValidator::getCreateRules()
    );
    if ($validator->fails()) {
      throw new Exception($validator->errors());
    }
    try {
      $model = IntegrationLog::create($rec);
    } catch (Exception $e) {
      throw new \App\Exceptions\GeneralException($e->getMessage());
    }
    return $model;
  }

  public static function updateRec($model_id, array $rec)
  {

    $model = IntegrationLog::findOrFail($model_id);

    if (!$model->updated_at->eq(\Carbon\Carbon::parse($rec['updated_at']))) {
      $entity = (new \ReflectionClass($model))->getShortName();
      throw new \App\Exceptions\ConcurrencyCheckFailedException($entity);
    }
    Utilities::hydrate($model, $rec);
    $validator = Validator::make(
      $rec,
      IntegrationLogUpdateValidator::getUpdateRules($model_id)
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
    IntegrationLog::destroy($recs);
  }

  public function createLogEntry($request){
    try {
      DB::beginTransaction();
        $rec = [];
        $rec['process_name'] = $request->process_name;
        $rec['file_name'] = $request->file_name;
        $rec['status'] = $request->status;
        $rec['start_time'] = Carbon::now();
        $model = $this->createRec($rec);

      DB::commit();
      return response()->json(["status" => "success", "data" => $model], 200);
    } catch (Exception $e) {
      DB::rollBack();
      throw new \App\Exceptions\GeneralException($e->getMessage());
    }
  }

  public function updateLogEntry($request){
    try {
      DB::beginTransaction();
        $rec = [];
        $rec['id'] = $request->id;
        $rec['status'] = $request->status;
        $rec['error_count'] = $request->error_count;
        $rec['end_time'] = Carbon::now();
        $old_model = IntegrationLog::find($request->id);
        $rec['updated_at'] = $old_model->updated_at;
        $model = $this->updateRec($request->id, $rec);

      DB::commit();
      return response()->json(["status" => "success", "data" => $model], 200);
    } catch (Exception $e) {
      DB::rollBack();
      throw new \App\Exceptions\GeneralException($e->getMessage());
    }
  }

  public function getLogsByDate($search_date){
    return IntegrationLog::whereDate('created_at', $search_date)->get();
  }
}
