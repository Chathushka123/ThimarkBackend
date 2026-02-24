<?php

namespace App\Http\Repositories;

use App\Fpo;
use Illuminate\Http\Request;
use App\Soc;
use App\SocToleranceLog;
use App\Style;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use App\Http\Resources\SocWithParentsResource;
use Illuminate\Validation\Rule;
use Exception;

use App\Http\Validators\SocCreateValidator;
use App\Http\Validators\SocUpdateValidator;
use Illuminate\Support\Facades\Log;

class SocRepository
{
  public function show(Soc $soc)
  {
    return response()->json(
      [
        'status' => 'success',
        'data' => new SocWithParentsResource($soc),
      ],
      200
    );
  }

  private static function getValidationMessages()
  {
    return [
      'wfx_soc_no.required' => 'SOC No is required',
      'wfx_soc_no.unique' => 'SOC No has already been taken',
    ];
  }

  public static function createRec(array $rec)
  {
    $rec['qty_json'] = json_encode($rec['qty_json']);
    $validator = Validator::make(
      $rec,
      SocCreateValidator::getCreateRules(),
      self::getValidationMessages()
    );
    if ($validator->fails()) {
      throw new Exception($validator->errors());
    }
    $rec['qty_json'] = Utilities::json_numerize(json_decode($rec['qty_json'], true), "int");

    try {
      $rec['status'] = Soc::getInitialStatus();
      $model = Soc::create($rec);
    } catch (Exception $e) {
      throw new \App\Exceptions\GeneralException($e->getMessage());
    }
    return $model;
  }

  public static function updateRec($model_id, array $rec)
  {

    $model = Soc::findOrFail($model_id);
    

    if ($model->status == "Closed") {
      throw new \App\Exceptions\NoModificationsAllowedException("SOC", "Closed");
    }

    // if (!$model->updated_at->eq(\Carbon\Carbon::parse($rec['updated_at']))) {
    //   $entity = (new \ReflectionClass($model))->getShortName();
    //   throw new \App\Exceptions\ConcurrencyCheckFailedException($entity);
    // }

    if (array_key_exists('qty_json', $rec)) {
      $rec['qty_json'] = json_encode($rec['qty_json']);
    }

    Utilities::hydrate($model, $rec);
    $validator = Validator::make(
      $rec,
      SocUpdateValidator::getUpdateRules($model_id),
      self::getValidationMessages()
    );

    if ($validator->fails()) {
      throw new Exception($validator->errors());
    }

    if (array_key_exists('qty_json', $rec)) {
      $rec['qty_json'] = Utilities::json_numerize(json_decode($rec['qty_json'], true), "int");
    }

    try {
      self::runBeforeUpdate($model, $rec);
      $model->update($rec);

      if(isset($rec['tolerance_json'])){
        $ct_log['cutting_tolerance_json'] = $rec['tolerance_json'];
        $ct_log['soc_id'] = $model_id;

        SocToleranceLog::create($ct_log);
      }
      
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

    if (Fpo::whereIn('soc_id', $recs)->exists()) {
      throw new \App\Exceptions\GeneralException('Order has been progressed, Not allowed to delete');
    };

    Soc::destroy($recs);
  }

  public static function getBalanceQuantities(Soc $soc)
  {
    
    try {
      $fpos_qty = array();
      $balance_qty = array();
      $ret = array();
      $has_fpo = false;
      //sort json
      if ((isset($soc->qty_json)) && (isset($soc->qty_json_order))) {
        $soc->qty_json = Utilities::sortQtyJson($soc->qty_json_order, $soc->qty_json);
      }
      $ret = array_merge($ret, ['soc_qty' => $soc->qty_json]);

      
      foreach ($soc->fpos as $fpo => $value) {
        //sort json
        if ((isset($value->qty_json)) && (isset($value->qty_json_order))) {
          $value->qty_json = Utilities::sortQtyJson($value->qty_json_order, $value->qty_json);
        }

        $has_fpo = true;
        foreach ($value['qty_json'] as $key => $value) {
          if (isset($fpos_qty[$key])) {
            $fpos_qty[$key] += $value;
          } else {
            $fpos_qty[$key] = $value;
          }
        }
      }

      //if no fpos use soc quantity
      if (!$has_fpo) {
        $fpos_qty = array_map(function ($arg) {
          return 0;
        }, $soc->qty_json);
      }

      $ret = array_merge($ret, ['fpos_qty' => $fpos_qty]);
      foreach ($fpos_qty as $key => $value) {
        $balance_qty[$key] = (isset($soc->qty_json[$key])? $soc->qty_json[$key] : 0) - ((isset($fpos_qty[$key]) ? $fpos_qty[$key] : 0));
      }

      $results =  array_merge($ret, ['balance_qty' => $balance_qty]);

      return $results;
    } catch (Exception $e) {
      throw new \App\Exceptions\GeneralException($e->getMessage());
    }
  }

  public static function getSearchByCustomerStyleRef()
  {
    $results = Soc::select(
      'socs.id as soc_id',
      'socs.wfx_soc_no as soc_no',
      'buyers.id as buyer_id',
      'buyers.buyer_code as buyer_code',
      'styles.id as style_id',
      'styles.style_code as style_code',
      'customer_style_ref'
    )
      ->join('buyers', 'socs.buyer_id', '=', 'buyers.id')
      ->join('styles', 'socs.style_id', '=', 'styles.id')
      ->distinct()->get();

    return $results;
  }

  public static function getSearchByCustomerStyleRefCode($buyer_code, $style_code, $customer_style_ref, $soc_no)
  {

    $results = Soc::select(
      'socs.wfx_soc_no as soc_no',
      'buyers.id as buyer_id',
      'buyers.buyer_code as buyer_code',
      'styles.id as style_id',
      'styles.style_code as style_code',
      'customer_style_ref'
    )
      ->join('buyers', 'socs.buyer_id', '=', 'buyers.id')
      ->join('styles', 'socs.style_id', '=', 'styles.id')
      ->where('buyers.buyer_code', 'LIKE', (is_null($buyer_code) ? '%' : '%' . $buyer_code . '%'))
      ->where('styles.style_code',  'LIKE', (is_null($style_code) ? '%' : '%' . $style_code . '%'))
      ->where('socs.customer_style_ref', 'LIKE', (is_null($customer_style_ref) ? '%' : '%' . $customer_style_ref . '%'))
      ->where('socs.wfx_soc_no', 'LIKE', (is_null($soc_no) ? '%' : '%' . $soc_no . '%'))
      ->distinct()->get();

    return $results;
  }

  public static function getPendingConnectedFpos($buyer_id, $style_id, $customer_style_ref)
  {

    $results = Soc::select(
      'socs.id as soc_id',
      'socs.wfx_soc_no as wfx_soc_no',
      'socs.garment_color as garment_color',
      'fpos.id as fpo_id',
      'fpos.wfx_fpo_no as wfx_fpo_no',
      'fpos.qty_json as qty_json',
      'fpos.qty_json_order as qty_json_order',
      'fpos.updated_at as fpo_updated_at',
      'socs.customer_style_ref'
      
    )
      ->join('fpos', 'fpos.soc_id', '=', 'socs.id')
      ->where('socs.buyer_id', $buyer_id)
    //  ->where('socs.style_id', $style_id)
      ->where('socs.customer_style_ref', $customer_style_ref)
      ->whereNull('fpos.combine_order_id')
      ->get();

      // $results = Soc::select(
      //   'socs.id as soc_id',
      //   'socs.wfx_soc_no as wfx_soc_no',
      //   'socs.garment_color as garment_color',
      //   'fpos.id as fpo_id',
      //   'fpos.wfx_fpo_no as wfx_fpo_no',
      //   'fpos.qty_json as qty_json',
      //   'fpos.qty_json_order as qty_json_order',
      //   'fpos.updated_at as fpo_updated_at',
      //   'socs.customer_style_ref'
      // )
      //   ->join('fpos', 'fpos.soc_id', '=', 'socs.id')
      //   ->where('socs.buyer_id', $buyer_id)
      // //  ->where('socs.style_id', $style_id)
      //   ->where('socs.customer_style_ref', $customer_style_ref)
      //   ->whereNull('fpos.combine_order_id')
      //   ->distinct()->get();
    
    ///////////////////////  Get All Fpos for this customer style ref and size must be equal ///////////

    $size_json = Style::select(     
      'size_fit'      
    )      
      ->where('id', $style_id)      
      ->distinct()->first();

    $equal = true;
    foreach ($results as $rec) {      
      foreach ($rec->qty_json_order as $key => $val) {
        $found = false;
        foreach ($size_json->size_fit as $index => $value) {
			
          if(strval($val)  == strval($value) && strlen($val) == strlen($value)){
            $found = true;
          }
        }
        if(!$found){
          $equal = false;
        }
      }
    }

    if(!$equal){
      $results = Soc::select(
        'socs.id as soc_id',
        'socs.wfx_soc_no as wfx_soc_no',
        'socs.garment_color as garment_color',
        'fpos.id as fpo_id',
        'fpos.wfx_fpo_no as wfx_fpo_no',
        'fpos.qty_json as qty_json',
        'fpos.qty_json_order as qty_json_order',
        'fpos.updated_at as fpo_updated_at',
        'socs.customer_style_ref'
      )
        ->join('fpos', 'fpos.soc_id', '=', 'socs.id')
        ->where('socs.buyer_id', $buyer_id)
        ->where('socs.style_id', $style_id)
        ->where('socs.customer_style_ref', $customer_style_ref)
        ->whereNull('fpos.combine_order_id')
        ->distinct()->get();
    }
    
    //sorting json
    foreach ($results as $key => $result) {
      if ((isset($result->qty_json)) && (isset($result->qty_json_order))) {
       // $result->qty_json = Utilities::sortQtyJson($result->qty_json_order, $result->qty_json);
      }
    }

    return $results;
  }

  private static function runBeforeUpdate($model, $rec)
  {
    $fpo_sum_json = array_reduce(
      $model->fpos->all(),
      function ($carry, $item) {
        foreach ($item->qty_json as $size => $qty) {
          if (array_key_exists($size, (array)$carry)) {
            $carry[$size] += $qty;
          } else {
            $carry[$size] = $qty;
          }
        }
        return $carry;
      },
      []
    );

    if ((sizeof($fpo_sum_json) > 0) && (sizeof($rec['qty_json']) > 0)) {
      if (Utilities::json_compare($fpo_sum_json, $rec['qty_json'])) {
        throw new Exception("Soc quantities cannot be less than sum of Fpo quantities");
      }
    }
  }
}
