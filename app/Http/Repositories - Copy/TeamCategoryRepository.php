<?php

namespace App\Http\Repositories;

use Illuminate\Http\Request;
use App\TeamCategory;
use App\Http\Resources\TeamCategoryResource;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use App\Http\Resources\TeamCategoryWithParentsResource;
use Illuminate\Validation\Rule;
use Exception;
use Illuminate\Support\Str;

use App\Http\Validators\TeamCategoryCreateValidator;
use App\Http\Validators\TeamCategoryUpdateValidator;

class TeamCategoryRepository
{
  public function show(TeamCategory $teamCategory)
  {
    return response()->json(
      [
        'status' => 'success',
        'data' => new TeamCategoryWithParentsResource($teamCategory),
      ],
      200
    );
  }

  public static function createRec(array $rec)
  {
    $validator = Validator::make(
      $rec,
      TeamCategoryCreateValidator::getCreateRules()
    );
    if ($validator->fails()) {
      Utilities::extractError($validator);
    }
    $rec['code'] = Str::upper($rec['code']);
    // try {
    $model = TeamCategory::create($rec);
    // } catch (Exception $e) {
    //   throw new \App\Exceptions\GeneralException($e->getMessage());
    // }
    return $model;
  }

  public static function updateRec($model_id, array $rec)
  {
    $model = TeamCategory::findOrFail($model_id);
    Utilities::validateCode($model->code, $rec['code'], "Code");

    if (!$model->updated_at->eq(\Carbon\Carbon::parse($rec['updated_at']))) {
      $entity = (new \ReflectionClass($model))->getShortName();
      throw new \App\Exceptions\ConcurrencyCheckFailedException($entity);
    }
    Utilities::hydrate($model, $rec);
    $validator = Validator::make(
      $rec,
      TeamCategoryUpdateValidator::getUpdateRules($model_id)
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
    TeamCategory::destroy($recs);
  }
}
