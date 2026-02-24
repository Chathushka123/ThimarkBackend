<?php

namespace App\Http\Repositories;

use App\QcExclude;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use App\Http\Resources\QcExcludeWithParentsResource;
use Illuminate\Validation\Rule;
use Exception;

use App\Http\Validators\QcExcludeCreateValidator;
use App\Http\Validators\QcExcludeUpdateValidator;
use Illuminate\Log\Logger;
use Illuminate\Support\Facades\Log;

class QcExcludeRepository
{
  const DISCARD_COLUMNS = [];

  const FIELD_MAPPING = [];

  public function show(QcExclude $qcExclude)
  {
    return response()->json(
      [
        'status' => 'success',
        'data' => new QcExcludeWithParentsResource($qcExclude),
      ],
      200
    );
  }

  public static function createRec(array $rec)
  {
    $validator = Validator::make(
      $rec,
      QcExcludeCreateValidator::getCreateRules()
    );
    if ($validator->fails()) {
      throw new Exception($validator->errors());
    }
    try {
      $model = QcExclude::create($rec);
    } catch (Exception $e) {
      throw new \App\Exceptions\GeneralException($e->getMessage());
    }
    return $model;
  }

  public static function updateRec($model_id, array $rec)
  {
    $model = QcExclude::findOrFail($model_id);

    Utilities::hydrate($model, $rec);

    $validator = Validator::make(
      $rec,
      QcExcludeUpdateValidator::getUpdateRules($model_id)
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
      $model = QcExclude::findOrFail($id);
      QcExclude::destroy([$id]);
    }
  }
}
