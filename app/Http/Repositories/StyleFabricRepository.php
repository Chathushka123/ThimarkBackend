<?php

namespace App\Http\Repositories;

use Illuminate\Http\Request;
use App\Style;
use App\CutPlan;
use App\CutUpdate;
use App\Http\Resources\StyleResource;
// use App\HashStore;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use App\Http\Resources\StyleFabricWithParentsResource;
use Illuminate\Validation\Rule;
use Exception;

use App\Http\Validators\StyleFabricCreateValidator;
use App\Http\Validators\StyleFabricUpdateValidator;
use App\StyleFabric;
use Illuminate\Support\Facades\Log;

class StyleFabricRepository
{
  public function show(StyleFabric $styleFabric)
  {
    return response()->json(
      [
        'status' => 'success',
        'data' => new StyleFabricWithParentsResource($styleFabric),
      ],
      200
    );
  }

  public static function createRec(array $rec)
  {
    $validator = Validator::make(
      $rec,
      StyleFabricCreateValidator::getCreateRules($rec)
    );
    
    if ($validator->fails()) {
      throw new Exception($validator->errors());
    }
    try {
      $model = StyleFabric::create($rec);
    } catch (Exception $e) {
      throw new \App\Exceptions\GeneralException($e->getMessage());
    }
    return $model;
  }

  public static function updateRec($model_id, array $rec)
  {
    $model = StyleFabric::findOrFail($model_id);
    if (!$model->updated_at->eq(\Carbon\Carbon::parse($rec['updated_at']))) {
      $entity = (new \ReflectionClass($model))->getShortName();
      throw new \App\Exceptions\ConcurrencyCheckFailedException($entity);
    }

    Utilities::hydrate($model, $rec);
    
    $validator = Validator::make(
      $rec,
      StyleFabricUpdateValidator::getUpdateRules($model_id,$rec)
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
    StyleFabric::destroy($recs);
  }

}
