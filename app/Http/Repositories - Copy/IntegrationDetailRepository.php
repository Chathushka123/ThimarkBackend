<?php

namespace App\Http\Repositories;

use Illuminate\Http\Request;
use App\IntegrationDetail;
use App\Http\Resources\IntegrationDetailResource;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use App\Http\Resources\IntegrationDetailWithParentsResource;
use Illuminate\Validation\Rule;
use Exception;

use App\Http\Validators\IntegrationDetailCreateValidator;
use App\Http\Validators\IntegrationDetailUpdateValidator;
use App\IntegrationLog;

class IntegrationDetailRepository
{
  public function show(IntegrationDetail $integrationdetail)
  {
    return response()->json(
      [
        'status' => 'success',
        'data' => new IntegrationDetailWithParentsResource($integrationdetail),
      ],
      200
    );
  }

  public static function createRec(array $rec)
  {
    $validator = Validator::make(
      $rec,
      IntegrationDetailCreateValidator::getCreateRules()
    );
    if ($validator->fails()) {
      throw new Exception($validator->errors());
    }
    try {
      $model = IntegrationDetail::create($rec);
    } catch (Exception $e) {
      throw new \App\Exceptions\GeneralException($e->getMessage());
    }
    return $model;
  }

  public static function updateRec($model_id, array $rec)
  {

    $model = IntegrationDetail::findOrFail($model_id);

    if (!$model->updated_at->eq(\Carbon\Carbon::parse($rec['updated_at']))) {
      $entity = (new \ReflectionClass($model))->getShortName();
      throw new \App\Exceptions\ConcurrencyCheckFailedException($entity);
    }
    Utilities::hydrate($model, $rec);
    $validator = Validator::make(
      $rec,
      IntegrationDetailUpdateValidator::getUpdateRules($model_id)
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
    IntegrationDetail::destroy($recs);
  }

  public function createEntry($request){
    try {
      DB::beginTransaction();
        $rec = [];
        $rec['log_detail'] = $request->log_detail;
        $rec['integration_log_id'] = $request->integration_log_id;
        $rec['row_no'] = $request->row_no;
        $model = $this->createRec($rec);

      DB::commit();
      return response()->json(["status" => "success", "data" => $model], 200);
    } catch (Exception $e) {
      DB::rollBack();
      throw new \App\Exceptions\GeneralException($e->getMessage());
    }
  }

  public function getDetailsByLogId($log_id){
    $header = IntegrationLog::find($log_id);
    $detail =  IntegrationDetail::where('integration_log_id', $log_id)->get();
    return response()->json(['Header' => $header, 'Detail'=>$detail], 200);

  }
}
