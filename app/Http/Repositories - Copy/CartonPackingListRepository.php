<?php

namespace App\Http\Repositories;

use Illuminate\Http\Request;
use App\CartonPackingList;
use App\Exceptions\GeneralException;
use App\Http\Resources\CartonPackingListResource;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use App\Http\Resources\CartonPackingListWithParentsResource;
use Illuminate\Validation\Rule;
use Exception;

use App\Http\Validators\CartonPackingListCreateValidator;
use App\Http\Validators\CartonPackingListUpdateValidator;
use App\PackingListDetail;
use App\PackingListSoc;
use Illuminate\Support\Facades\Log;

class CartonPackingListRepository
{
  public function show(CartonPackingList $cartonpackinglist)
  {
    return response()->json(
      [
        'status' => 'success',
        'data' => new CartonPackingListWithParentsResource($cartonpackinglist),
      ],
      200
    );
  }

  public static function createRec(array $rec)
  {
    $validator = Validator::make(
      $rec,
      CartonPackingListCreateValidator::getCreateRules()
    );
    if ($validator->fails()) {
      throw new Exception($validator->errors());
    }
    //self::_validateQuantity($rec['ratio_json'], $rec['packing_list_id']);
    try {
      $model = CartonPackingList::create($rec);
    } catch (Exception $e) {
      throw new \App\Exceptions\GeneralException($e->getMessage());
    }
    return $model;
  }

  public static function updateRec($model_id, array $rec)
  {

    $model = CartonPackingList::findOrFail($model_id);

    if (!$model->updated_at->eq(\Carbon\Carbon::parse($rec['updated_at']))) {
      $entity = (new \ReflectionClass($model))->getShortName();
      throw new \App\Exceptions\ConcurrencyCheckFailedException($entity);
    }
    Utilities::hydrate($model, $rec);
    $validator = Validator::make(
      $rec,
      CartonPackingListUpdateValidator::getUpdateRules($model_id)
    );
    if ($validator->fails()) {
      throw new Exception($validator->errors());
    }
    //self::_validateQuantity($rec['ratio_json'], $rec['packing_list_id']);
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
    CartonPackingList::destroy($recs);
  }

  public static function getPlannedTotals($packing_list_id)
  {
    
    $retun_qty_json = [];
    $calc_json = [];

    $planned_qty_json =  CartonPackingList::select('carton_packing_list.packing_list_id','carton_packing_list.ratio_json','carton_packing_list.no_of_cartons')
    //->join('packing_list_soc', 'packing_list_soc.packing_list_id', '=', 'carton_packing_list.packing_list_id')
    ->where('carton_packing_list.packing_list_id', $packing_list_id)
    ->get();

    foreach ($planned_qty_json as $rec) {
      foreach ($rec->ratio_json as $key=>$value) {  
        if (!(is_null($value) || $value == 0)) {
          $amount = intval($value)*intval($rec->no_of_cartons);
          if (array_key_exists($key, $retun_qty_json)) {
            $retun_qty_json[$key] = $retun_qty_json[$key] + $amount;
          } else {
            $retun_qty_json[$key] =  $amount;
          }
        }
      }
    }

    // for($i=0; $i<count($socNo); $i++){
    //   $planned_qty_json =  CartonPackingList::select('carton_packing_list.packing_list_id','carton_packing_list.ratio_json','carton_packing_list.no_of_cartons','packing_list_soc.pack_ratio')
    //   ->join('packing_list_soc', 'packing_list_soc.packing_list_id', '=', 'carton_packing_list.packing_list_id')
    //   ->where('packing_list_soc.soc_id', $socNo[$i])
    //   ->distinct()->get();

    //   foreach ($planned_qty_json as $rec) {
    //     foreach ($rec->ratio_json as $key=>$value) {  
    //       if (!(is_null($value) || $value == 0)) {
    //         $amount = intval($value)*intval($rec->no_of_cartons)*intval($rec->pack_ratio);
    //         if (array_key_exists($key, $retun_qty_json)) {
    //           $retun_qty_json[$key] = $retun_qty_json[$key] + $amount;
    //         } else {
    //           $retun_qty_json[$key] =  $amount;
    //         }
    //       }
    //     }
    //   }
    // }
    return $retun_qty_json;
    /*
    foreach ($recs as $rec) {      
      $amount = $rec->pcs_per_carton * $rec->no_of_cartons;
      Log::info('AMOUNT = ' . $amount );
      $calc_json = $rec->ratio_json;
      if (self::_isJsonSingleElement($rec->ratio_json)) {
        foreach ($rec->ratio_json as $key => $value) {        
          if (!(is_null($value) || $value == 0)) {
            $calc_json[$key] = $amount;
          }
        }
      }

      $retun_qty_json[] = $calc_json;
    }

    $retun_qty_json = array_reduce($retun_qty_json, function($carry, $item){
      foreach($item as $key => $value) {
      $carry[$key] = (array_key_exists($key,$carry) ? $carry[$key] : 0) + $value;
      }
      return $carry;
    },[]);
    Log::info($retun_qty_json);
    return $retun_qty_json;
    */
  }

  //{S:null, M:null, L:10} ---> resore value 25  == > {S:null, M:null, L:25}
  //{S:100, M:100, L:50} ---> resore value 25  ==> {S:25, M:25, L:25}

  private static function _isJsonSingleElement($ratio_json)
  {
    $return_arr =  array_filter(
      $ratio_json,
      function ($v, $k) {
        return !($v ==  0 || is_null($v));
      },
      ARRAY_FILTER_USE_BOTH
    );

    if (sizeof($return_arr) == 1) {
      return true;
    } else {
      return false;
    }
  }

  private static function _validateQuantity($ratio_json, $packing_list_id)
  {
    $pack_ratio_sum = PackingListSoc::where(['packing_list_id' => $packing_list_id])->sum('pack_ratio');
    if ($pack_ratio_sum == 0) {
      throw new Exception("Invalid pack ratio for SOCs");
    }
    foreach ($ratio_json as $size => $value) {
      if (!is_null($value)) {
        $int_val = intdiv($value, $pack_ratio_sum);
        $true_div = $value / $pack_ratio_sum;
        if ($int_val != $true_div) {
          throw new Exception("Entered quantity is not divisible by pack ratio");
        }
      }
    }
  }

  public function addNewCarton(Request $request)
  {
    try {
      DB::beginTransaction();
      $packing_list_id = $request->packing_list_id;
      $carton_id = $request->carton_id;
      $soc_data = $request->soc_data;
      $ratio_json_grandtotal = 0;

      foreach ($soc_data as $soc) {
        $ratio_json_grandtotal = $ratio_json_grandtotal + array_reduce($soc['qty_json'], function ($carry, $item) {
          $carry = $carry + $item;
          return $carry;
        }, 0);
      }

      $ratio_json_total = array_reduce($soc_data, function ($carry, $item) {
        foreach ($item['qty_json'] as $size => $qty) {
          if (array_key_exists($size, (array)$carry)) {
            $carry[$size] += $qty;
          } else {
            $carry[$size] = $qty;
          }
        }
        return $carry;
      }, []);

      $model = PackingListDetailRepository::createRec([
        'packing_list_id' => $packing_list_id,
        'carton_number' => PackingListDetail::where(['packing_list_id' => $packing_list_id])->max('carton_number') + 1,
        'manually_modified' => 1,
        'qty_json' => $ratio_json_total,
        'total' => $ratio_json_grandtotal,
        'carton_id' => $carton_id
      ]);

      foreach ($soc_data as $soc) {
        PackingSocConsumptionRepository::createRec([
          'packing_list_detail_id' => $model->id,
          'packing_list_soc_id' => PackingListSoc::where(['packing_list_id' => $packing_list_id, 'soc_id' => $soc['soc_id']])->firstOrFail()->id,
          'qty_json' => $soc['qty_json']
        ]);
      }
      DB::commit();
      return response()->json(["status" => "success"], 200);
    } catch (Exception $e) {
      DB::rollBack();
      throw new GeneralException($e->getMessage());
    }
  }
}
