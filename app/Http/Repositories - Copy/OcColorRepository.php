<?php

namespace App\Http\Repositories;

use Illuminate\Http\Request;
use App\OcColor;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use App\Http\Resources\OcColorResource;
use Exception;

use App\Http\Validators\OcColorCreateValidator;
use App\Http\Validators\OcColorUpdateValidator;

class OcColorRepository
{

  public function show(OcColor $ocColor)
  {
    return response()->json(
      [
        'status' => 'success',
        'data' => new OcColorResource($ocColor),
      ],
      200
    );
  }

  public static function createRec(array $rec)
  {
    $rec['qty_json'] = json_encode($rec['qty_json']);
    $validator = Validator::make(
      $rec,
      OcColorCreateValidator::getCreateRules()
    );
    if ($validator->fails()) {
      throw new Exception($validator->errors());
    }
    $rec['qty_json'] = json_decode($rec['qty_json']);
    try {
      $model = OcColor::create($rec);
    } catch (Exception $e) {
      throw new \App\Exceptions\GeneralException($e->getMessage());
    }
    return $model;
  }

  public static function updateRec($model_id, array $rec)
  {
    // if ($master_id == null) {
    $model = OcColor::findOrFail($model_id);
    // } else {
    //   $model = OcColor::where($parent_key, $master_id)
    //     ->where('id', $model_id)
    //     ->firstOrFail();
    // }
    // return $model;
    if (!$model->updated_at->eq(\Carbon\Carbon::parse($rec['updated_at']))) {
      $entity = (new \ReflectionClass($model))->getShortName();
      throw new ConcurrencyCheckFailedException($entity);
    }
    if (array_key_exists('qty_json', $rec)) {
      $rec['qty_json'] = json_encode($rec['qty_json']);
    }
    Utilities::hydrate($model, $rec);
    $validator = Validator::make(
      $rec,
      OcColorUpdateValidator::getUpdateRules($model_id)
    );
    if ($validator->fails()) {
      throw new Exception($validator->errors());
    }
    if (array_key_exists('qty_json', $rec)) {
      $rec['qty_json'] = json_decode($rec['qty_json']);
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
    OcColor::destroy($recs);
  }

  public function getBalanceQuantities(OcColor $ocColor)
  {
    try {
      $soc_sum = array();
      $ret = array();

      $ret = array_merge($ret, ['oc_qty' => $ocColor->qty_json]);
      foreach ($ocColor->socs as $key => $value) {
        foreach ($value['qty_json'] as $key => $value) {
          if (isset($soc_sum[$key])) {
            $soc_sum[$key] += $value;
          } else {
            $soc_sum[$key] = $value;
          }
        }
      }
      $ret = array_merge($ret, ['socs_qty' => $soc_sum]);
      foreach ($soc_sum as $key => $value) {
        $soc_sum[$key] = $ocColor->qty_json[$key] - $soc_sum[$key];
      }
      return array_merge($ret, ['balance_qty' => (sizeof($soc_sum) == 0) ? $ocColor->qty_json : $soc_sum]);
    } catch (Exception $e) {
      throw new \App\Exceptions\GeneralException($e->getMessage());
    }
  }
}
