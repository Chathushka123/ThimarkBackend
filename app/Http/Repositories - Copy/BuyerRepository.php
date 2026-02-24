<?php

namespace App\Http\Repositories;

use Illuminate\Http\Request;
use App\Buyer;
use App\CutPlan;
use App\CutUpdate;
use App\Http\Resources\BuyerResource;
// use App\HashStore;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use App\Http\Resources\BuyerWithParentsResource;
use Illuminate\Validation\Rule;
use Exception;
use Illuminate\Support\Str;

use App\Http\Validators\BuyerCreateValidator;
use App\Http\Validators\BuyerUpdateValidator;

class BuyerRepository
{
  public function show(Buyer $buyer)
  {
    return response()->json(
      [
        'status' => 'success',
        'data' => new BuyerWithParentsResource($buyer),
      ],
      200
    );
  }

  public static function createRec(array $rec)
  {
    $validator = Validator::make(
      $rec,
      BuyerCreateValidator::getCreateRules()
    );
    if ($validator->fails()) {
      throw new Exception($validator->errors());
    }
    $rec['buyer_code'] = Str::upper($rec['buyer_code']);
    
    try {
      $model = Buyer::create($rec);
    } catch (Exception $e) {
      throw new \App\Exceptions\GeneralException($e->getMessage());
    }
    return $model;
  }

  public static function updateRec($model_id, array $rec)
  {
    // if ($master_id == null) {
    $model = Buyer::findOrFail($model_id);
    Utilities::validateCode($model->buyer_code, $rec['buyer_code'], "Buyer Code");
    // } else {
    //   $model = Buyer::where($parent_key, $master_id)
    //     ->where('id', $model_id)
    //     ->firstOrFail();
    // }
    // return $model;
    Utilities::hydrate($model, $rec);
    $validator = Validator::make(
      $rec,
      BuyerUpdateValidator::getUpdateRules($model_id)
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
    Buyer::destroy($recs);
  }

  public function getBuyersByFppoAndCutNo($fppo_id, $cut_no = null)
  {
    // $data = [
    //   "A" => ["id" => 12, "name" => "test1"],
    //   "B" => ["id" => 13, "name" => "test2"]
    // ];
    // data_fill($data, '*.cut_id', 1);

    // return $data;

    $cut_plans = CutPlan::select('id')
      ->distinct()
      ->where('fppo_id', $fppo_id)
      ->where('cut_no', 'LIKE', (is_null($cut_no) ? '%' : $cut_no))
      ->get()
      ->toArray();
    $cut_update_ids = CutUpdate::select('id')->distinct()->whereIn('cut_plan_id', [$cut_plans])->get()->toArray();
    $buyers = Buyer::whereHas('cut_updates', function ($q) use ($cut_update_ids) {
      $q->whereIn('id', $cut_update_ids);
    })->get();

    return BuyerResource::collection($buyers);
  }

  public static function importExcel($fileNameWithPath)
  {
    $started_at = microtime();
    $models = Utilities::readFile($fileNameWithPath);

    for ($i = 1; $i < sizeof($models); $i++) {
      foreach ($models[0] as $key => $value) {
        if (($value == "") || is_null($value)) {
          unset($models[$i][$key]);
        }
      }
      $models[$i] = array_values($models[$i]);
    }
    for ($i = 1; $i < sizeof($models[0]); $i++) {
      if (($models[0][$i] == "") || ($models[0][$i] === null)) {
        unset($models[0][$i]);
      }
    }
    $models[0] = array_values(array_filter($models[0]));
    unset($models[sizeof($models) - 1]);

    $header = [];

    foreach ($models as $key => $value) {
      if (!$header) {
        $header = array_values($value);
      } else {
        $data[] = array_combine($header, array_values($value));
      }
    }

    $buyer = null;
    foreach ($data as $temp => $content) {
      unset($buyer);
      $info = '';
      try {
        if ($buyer = Buyer::where('buyer_code', $content['buyer_code'])->first()) {
          $buyer->buyer_code = $content['buyer_code'];
          $buyer->name = $content['buyer_name'];
          $buyer->save();
          $info = 'Existing Buyer was updated';
        } else {
          $buyer = new Buyer();
          $buyer->buyer_code = $content['buyer_code'];
          $buyer->name = $content['buyer_name'];
          $buyer->save();
          $info = '';
        }
        $ret[] = ["status" => "success", "data" => $content['buyer_code'], "info" => $info];
      } catch (Exception $e) {
        $ret[] = ["status" => "error", "data" => $content['buyer_code'], "info" => $e->getMessage()];
      }
    }

    return response()->json($ret, 200);
  }
}
