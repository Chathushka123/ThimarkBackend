<?php

namespace App\Http\Repositories;

use App\DailyTeamSlotTarget;
use Illuminate\Support\Facades\Validator;
use App\Http\Resources\DailyTeamSlotTargetWithParentsResource;
use Exception;

use App\Http\Validators\DailyTeamSlotTargetCreateValidator;
use App\Http\Validators\DailyTeamSlotTargetUpdateValidator;

class DailyTeamSlotTargetRepository
{
  public function show(DailyTeamSlotTarget $bailyTeamSlotTarget)
  {
    return response()->json(
      [
        'status' => 'success',
        'data' => new DailyTeamSlotTargetWithParentsResource($bailyTeamSlotTarget),
      ],
      200
    );
  }

  public static function createRec(array $rec)
  {
    $validator = Validator::make(
      $rec,
      DailyTeamSlotTargetCreateValidator::getCreateRules()
    );
    if ($validator->fails()) {
      throw new Exception($validator->errors());
    }
    try {
      $model = DailyTeamSlotTarget::create($rec);
    } catch (Exception $e) {
      throw new \App\Exceptions\GeneralException($e->getMessage());
    }
    return $model;
  }

  public static function updateRec($model_id, array $rec)
  {
    // if ($master_id == null) {
    $model = DailyTeamSlotTarget::findOrFail($model_id);
    // } else {
    //   $model = DailyTeamSlotTarget::where($parent_key, $master_id)
    //     ->where('id', $model_id)
    //     ->firstOrFail();
    // }
    // return $model;
    Utilities::hydrate($model, $rec);
    $validator = Validator::make(
      $rec,
      DailyTeamSlotTargetUpdateValidator::getUpdateRules($model_id)
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
    DailyTeamSlotTarget::destroy($recs);
  }
}
