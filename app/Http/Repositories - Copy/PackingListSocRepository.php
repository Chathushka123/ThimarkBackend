<?php

namespace App\Http\Repositories;

use Illuminate\Http\Request;
use App\PackingSocConsumption;
use App\PackingListSoc;
use App\CartonPackingList;
use App\Http\Resources\PackingListSocResource;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use App\Http\Resources\PackingListSocWithParentsResource;
use Illuminate\Validation\Rule;
use Exception;

use App\Http\Validators\PackingListSocCreateValidator;
use App\Http\Validators\PackingListSocUpdateValidator;
use App\Soc;
use Illuminate\Support\Facades\Log;

class PackingListSocRepository
{
  public function show(PackingListSoc $packinglistsoc)
  {
    return response()->json(
      [
        'status' => 'success',
        'data' => new PackingListSocWithParentsResource($packinglistsoc),
      ],
      200
    );
  }

  public static function createRec(array $rec)
  {
    
    $validator = Validator::make(
      $rec,
      PackingListSocCreateValidator::getCreateRules()
    );
    if ($validator->fails()) {
      throw new Exception($validator->errors());
    }
    try {
      $model = PackingListSoc::create($rec);
    } catch (Exception $e) {
      throw new \App\Exceptions\GeneralException($e->getMessage());
    }
    return $model;
  }

  public static function updateRec($model_id, array $rec)
  {

    $model = PackingListSoc::findOrFail($model_id);

    if (!$model->updated_at->eq(\Carbon\Carbon::parse($rec['updated_at']))) {
      $entity = (new \ReflectionClass($model))->getShortName();
      throw new \App\Exceptions\ConcurrencyCheckFailedException($entity);
    }
    Utilities::hydrate($model, $rec);
    $validator = Validator::make(
      $rec,
      PackingListSocUpdateValidator::getUpdateRules($model_id)
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
    PackingListSoc::destroy($recs);
  }

  public static function getSearchByPackingListSoc($buyer_id, $customer_style_ref)
  {
    $results = Soc::select(
      'socs.id as id',
      'socs.wfx_soc_no as soc_no'
    )
    ->where('buyer_id', $buyer_id)
    ->where('customer_style_ref', $customer_style_ref)
    ->get();

    return $results;
  }

  public static function getSearchResultsByPackingListSoc($buyer_id, $customer_style_ref, $soc_no)
  {
    $results = Soc::select(
      'socs.id as id',
      'socs.wfx_soc_no as soc_no'
    )
    ->where('buyer_id', $buyer_id)
    ->where('customer_style_ref', $customer_style_ref)
    ->where('wfx_soc_no', 'LIKE', (is_null($soc_no) ? '%' : '%' . $soc_no . '%'))
    ->get();

    return $results;
  }

  public function getPackingListSocQuantities($soc_id,$packing_list_id)
  {
      /// get round method from 
      $round_method = DB::table('team_validation_status')
      ->select('cutting_tolerance')
      ->first();
  
      $method = 'round';
      if(!is_null($round_method)){
        $method = $round_method->cutting_tolerance;
      }

    $results = Soc::select(
      'socs.id as id',
      'socs.wfx_soc_no as soc_no',
      'garment_color',
      'qty_json',
      'tolerance',
      'tolerance_json'
    )
    ->where('id', $soc_id)
    ->first();

    $balance_soc_qty_json = $results['qty_json'];
    $balance_with_tolerance_soc_qty_json = $results['qty_json'];
   // $tolerance = (floatval($results->tolerance)>0)?floatval($results->tolerance)/100 : 0;
   $tolerance_json = $results['tolerance_json'];

    foreach($balance_soc_qty_json as $key => $val){
      //$balance_soc_qty_json[$key] = $balance_soc_qty_json[$key] +(ceil($balance_soc_qty_json[$key]*$tolerance));
      //$balance_with_tolerance_soc_qty_json[$key] = $balance_with_tolerance_soc_qty_json[$key] +(ceil($balance_with_tolerance_soc_qty_json[$key]*$tolerance));
      if(!is_null($tolerance_json)){
        $balance_with_tolerance_soc_qty_json[$key] = $balance_with_tolerance_soc_qty_json[$key] +($method($balance_with_tolerance_soc_qty_json[$key]*intval($tolerance_json[$key])/100));
      }else{
        $balance_with_tolerance_soc_qty_json[$key] = $balance_with_tolerance_soc_qty_json[$key];
      }
    }

    //$soc_qty_json = $results['qty_json'];

    $allocate_qty_json =PackingListSoc::select('quantity_json')
    ->where('soc_id', $soc_id)
    //->where('packing_list_id', $packing_list_id)
    ->distinct()->get();
    
      foreach ($allocate_qty_json as $rec) {
        foreach ($rec->quantity_json as $key=>$value) {  
          if (!(is_null($value) || $value == 0)) {
            $amount = intval($value);
            if (array_key_exists($key, $results['qty_json'])) {
              $balance_soc_qty_json[$key] = $balance_soc_qty_json[$key] - $amount;
            } else {
            }
          }
        }
      }
    
    // $planned_qty_json =  CartonPackingList::select('carton_packing_list.packing_list_id','carton_packing_list.ratio_json','carton_packing_list.no_of_cartons','packing_list_soc.pack_ratio')
    //   ->join('packing_list_soc', 'packing_list_soc.packing_list_id', '=', 'carton_packing_list.packing_list_id')
    //   ->where('packing_list_soc.soc_id', $soc_id)
    //   ->distinct()->get();

    //   foreach ($planned_qty_json as $rec) {
    //     foreach ($rec->ratio_json as $key=>$value) {  
    //       if (!(is_null($value) || $value == 0)) {
    //         $amount = intval($value)*intval($rec->no_of_cartons)*intval($rec->pack_ratio);
    //         if (array_key_exists($key, $results['qty_json'])) {
    //           $arr[$key] = $arr[$key] - $amount;
    //         } else {
    //         }
    //       }
    //     }
    //   }
      $results['qty_json']=$balance_soc_qty_json;
      $results['qty_json_tolerance']=$balance_with_tolerance_soc_qty_json;
    //  $results['balance_soc_qty_json']=$balance_soc_qty_json;
    
    
    return $results;
 }

}
