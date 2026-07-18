<?php

namespace App\Http\Repositories;

use Illuminate\Http\Request;
use App\DailyShift;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use App\Http\Resources\DailyShiftWithParentsResource;
use Illuminate\Validation\Rule;
use Exception;
use Illuminate\Support\Facades\Log;

use App\Http\Validators\DailyShiftCreateValidator;
use App\Http\Validators\DailyShiftUpdateValidator;

class DailyShiftRepository
{
  public function show(DailyShift $dailyShift)
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
      Utilities::extractError($validator);
    }
    self::checkShiftAvailability($rec['shift_id'], $rec['shift_date']);
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
      throw new \App\Exceptions\ConcurrencyCheckFailedException($entity);
    }
    Utilities::hydrate($model, $rec);
    $validator = Validator::make(
      $rec,
      DailyShiftUpdateValidator::getUpdateRules($model_id)
    );
    if ($validator->fails()) {
      throw new \App\Exceptions\GeneralException($validator->errors());
    }
    self::checkShiftAvailability($rec['shift_id'], $rec['shift_date'], $model_id);
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

  private static function checkShiftAvailability($shift_id, $shift_date, $ignore_id = null)
  {
    $exists = DailyShift::where('shift_id', $shift_id)
      ->where('shift_date', $shift_date)
      ->when($ignore_id, function ($query) use ($ignore_id) {
        $query->where('id', '!=', $ignore_id);
      })
      ->exists();

    if ($exists) {
      throw new \App\Exceptions\GeneralException('A daily shift already exists for this shift on the selected date.');
    }
  }
}
