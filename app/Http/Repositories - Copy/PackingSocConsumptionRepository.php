<?php

namespace App\Http\Repositories;

use Illuminate\Http\Request;
use App\PackingSocConsumption;
use App\Http\Resources\PackingSocConsumptionResource;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use App\Http\Resources\PackingSocConsumptionWithParentsResource;
use Illuminate\Validation\Rule;
use Exception;

use App\Http\Validators\PackingSocConsumptionCreateValidator;
use App\Http\Validators\PackingSocConsumptionUpdateValidator;

class PackingSocConsumptionRepository
{
  public function show(PackingSocConsumption $packingsocconsumption)
  {
    return response()->json(
      [
        'status' => 'success',
        'data' => new PackingSocConsumptionWithParentsResource($packingsocconsumption),
      ],
      200
    );
  }

  public static function createRec(array $rec)
  {
    $validator = Validator::make(
      $rec,
      PackingSocConsumptionCreateValidator::getCreateRules()
    );
    if ($validator->fails()) {
      throw new Exception($validator->errors());
    }
    try {
      $model = PackingSocConsumption::create($rec);
    } catch (Exception $e) {
      throw new \App\Exceptions\GeneralException($e->getMessage());
    }
    return $model;
  }

  public static function updateRec($model_id, array $rec)
  {

    $model = PackingSocConsumption::findOrFail($model_id);

    if (!$model->updated_at->eq(\Carbon\Carbon::parse($rec['updated_at']))) {
      $entity = (new \ReflectionClass($model))->getShortName();
      throw new \App\Exceptions\ConcurrencyCheckFailedException($entity);
    }
    Utilities::hydrate($model, $rec);
    $validator = Validator::make(
      $rec,
      PackingSocConsumptionUpdateValidator::getUpdateRules($model_id)
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
    PackingSocConsumption::destroy($recs);
  }
}
