<?php

namespace App\Http\Repositories;

use Illuminate\Http\Request;
use App\ForeignKeyMapper;
use App\CutPlan;
use App\CutUpdate;
use App\Http\Resources\ForeignKeyMapperResource;
// use App\HashStore;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use App\Http\Resources\ForeignKeyMapperWithParentsResource;
use Illuminate\Validation\Rule;
use Exception;

use App\Http\Validators\ForeignKeyMapperCreateValidator;
use App\Http\Validators\ForeignKeyMapperUpdateValidator;

class ForeignKeyMapperRepository
{
  public function show(ForeignKeyMapper $foreignKeyMapper)
  {
    return response()->json(
      [
        'status' => 'success',
        'data' => new ForeignKeyMapperWithParentsResource($foreignKeyMapper),
      ],
      200
    );
  }

  public static function createRec(array $rec)
  {
    $rec['key_mapping'] = json_encode($rec['key_mapping']);
    $validator = Validator::make(
      $rec,
      ForeignKeyMapperCreateValidator::getCreateRules()
    );
    if ($validator->fails()) {
      throw new Exception($validator->errors());
    }
    $rec['key_mapping'] = json_decode($rec['key_mapping']);
    try {
      $model = ForeignKeyMapper::create($rec);
    } catch (Exception $e) {
      throw new \App\Exceptions\GeneralException($e->getMessage());
    }
    return $model;
  }

  public static function updateRec($model_id, array $rec)
  {
    // if ($master_id == null) {
    $model = ForeignKeyMapper::findOrFail($model_id);
    // } else {
    //   $model = ForeignKeyMapper::where($parent_key, $master_id)
    //     ->where('id', $model_id)
    //     ->firstOrFail();
    // }
    // return $model;
    $rec['key_mapping'] = json_encode($rec['key_mapping']);
    Utilities::hydrate($model, $rec);
    $validator = Validator::make(
      $rec,
      ForeignKeyMapperUpdateValidator::getUpdateRules($model_id)
    );
    if ($validator->fails()) {
      throw new Exception($validator->errors());
    }
    $rec['key_mapping'] = json_decode($rec['key_mapping']);
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
    ForeignKeyMapper::destroy($recs);
  }
}
