<?php

namespace App\Http\Repositories;

use Illuminate\Http\Request;
use App\QcRecoverable;
use App\Fppo;
use App\Http\Resources\QcRecoverableResource;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use App\Http\Resources\QcRecoverableWithParentsResource;
use Illuminate\Validation\Rule;
use Exception;

use App\Http\Validators\QcRecoverableCreateValidator;
use App\Http\Validators\QcRecoverableUpdateValidator;

class QcRecoverableRepository
{
  public function show(QcRecoverable $bundleBin)
  {
    return response()->json(
      [
        'status' => 'success',
        'data' => new QcRecoverableWithParentsResource($bundleBin),
      ],
      200
    );
  }

  public static function createRec(array $rec)
  {
    $validator = Validator::make(
      $rec,
      QcRecoverableCreateValidator::getCreateRules()
    );
    if ($validator->fails()) {
      throw new Exception($validator->errors());
    }
    // try {
    $model = QcRecoverable::create($rec);
    // } catch (Exception $e) {
    //   throw new Exception(json_encode([$e->getMessage()]));
    // }
    return $model;
  }

  public static function updateRec($model_id, array $rec)
  {
    // if ($master_id == null) {
    $model = QcRecoverable::findOrFail($model_id);
    // } else {
    //   $model = QcRecoverable::where($parent_key, $master_id)
    //     ->where('id', $model_id)
    //     ->firstOrFail();
    // }
    // return $model;
    if (!$model->updated_at->eq(\Carbon\Carbon::parse($rec['updated_at']))) {
      $entity = (new \ReflectionClass($model))->getShortName();
      throw new \App\Exceptions\ConcurrencyCheckFailedException($entity);
    }
    Utilities::hydrate($model, $rec);
    $validator = Validator::make(
      $rec,
      QcRecoverableUpdateValidator::getUpdateRules($model_id)
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
    QcRecoverable::destroy($recs);
  }
}
