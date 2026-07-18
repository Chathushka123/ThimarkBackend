<?php

namespace App\Http\Repositories;

use Illuminate\Http\Request;
use App\DailyShiftTeam;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use App\Http\Resources\DailyShiftTeamWithParentsResource;
use Illuminate\Validation\Rule;
use Exception;
use Illuminate\Support\Facades\Log;

use App\Http\Validators\DailyShiftTeamCreateValidator;
use App\Http\Validators\DailyShiftTeamUpdateValidator;

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
      Utilities::extractError($validator);
    }
    try {
      $model = DailyShiftTeam::create($rec);
    } catch (Exception $e) {
      throw new \App\Exceptions\GeneralException($e->getMessage());
    }
    return $model;
  }

  public static function updateRec($model_id, array $rec)
  {
    $model = DailyShiftTeam::findOrFail($model_id);

    if (!$model->updated_at->eq(\Carbon\Carbon::parse($rec['updated_at']))) {
      $entity = (new \ReflectionClass($model))->getShortName();
      throw new \App\Exceptions\ConcurrencyCheckFailedException($entity);
    }
    Utilities::hydrate($model, $rec);
    $validator = Validator::make(
      $rec,
      DailyShiftTeamUpdateValidator::getUpdateRules($model_id)
    );
    if ($validator->fails()) {
      throw new \App\Exceptions\GeneralException($validator->errors());
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
    DailyShiftTeam::destroy($recs);
  }
}
