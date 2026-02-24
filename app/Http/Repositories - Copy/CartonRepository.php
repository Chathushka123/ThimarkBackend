<?php

namespace App\Http\Repositories;

use Illuminate\Http\Request;
use App\Carton;
use App\Http\Resources\CartonResource;
use App\Http\Resources\CartonWithParentsResource;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
//use App\Http\Resources\CartonWithParentResource;
use Illuminate\Validation\Rule;
use Exception;

use App\Http\Validators\CartonCreateValidator;
use App\Http\Validators\CartonUpdateValidator;

class CartonRepository
{
  public function show(Carton $carton)
  {
    return response()->json(
      [
        'status' => 'success',
        'data' => new CartonWithParentsResource($carton),
      ],
      200
    );
  }

  public static function createRec(array $rec)
  {
    $validator = Validator::make(
      $rec,
      CartonCreateValidator::getCreateRules()
    );
    if ($validator->fails()) {
      throw new Exception($validator->errors());
    }
    try {
      $model = Carton::create($rec);
    } catch (Exception $e) {
      throw new \App\Exceptions\GeneralException($e->getMessage());
    }
    return $model;
  }

  public static function updateRec($model_id, array $rec)
  {
    $model = Carton::findOrFail($model_id);

    if (!$model->updated_at->eq(\Carbon\Carbon::parse($rec['updated_at']))) {
      $entity = (new \ReflectionClass($model))->getShortName();
      throw new \App\Exceptions\ConcurrencyCheckFailedException($entity);
    }
    Utilities::hydrate($model, $rec);
    $validator = Validator::make(
      $rec,
      CartonUpdateValidator::getUpdateRules($model_id, $rec)
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
    Carton::destroy($recs);
  }
}
