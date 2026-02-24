<?php

namespace App\Http\Repositories;

use Illuminate\Http\Request;
use App\CombineOrder;
use App\CutPlan;
use App\Fpo;
use App\FpoFabric;
use App\Http\Controllers\Api\FpoController;
use App\Http\Resources\CombineOrderResource;
use App\Http\Validators\CombineOrderCreateValidator;
use App\Soc;
use App\StyleFabric;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Exception;
use Illuminate\Support\Facades\Log;

class CombineOrderRepository
{

  public static function createRec(array $rec)
  {

    $validator = Validator::make(
      $rec,
      CombineOrderCreateValidator::getCreateRules()
    );
    if ($validator->fails()) {
      throw new Exception($validator->errors());
    }
    try {
      $rec['status'] = CombineOrder::getInitialStatus();
      $model = CombineOrder::create($rec);
      return $model;
    } catch (Exception $e) {
      throw new \App\Exceptions\GeneralException($e->getMessage());
    }
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

  public static function deleteRecs(array $recs)
  {
    if (CutPlan::whereIn('combine_order_id', $recs)->exists()) {
      throw new \App\Exceptions\GeneralException('Combine Order has been progressed, Not allowed to delete');
    };

    foreach ($recs as $co_id) {
      self::_reopenFpos($co_id);
    }

    Fpo::whereIn('combine_order_id', $recs)->update(['combine_order_id' => null]);

    CombineOrder::destroy($recs);
  }

  public static function combineFpos($recs)
  {

    try {

      self::_validateFpos($recs);

      $cnt = 0;
      $model = null;
      $combine_order_no = null;

      DB::beginTransaction();
      foreach ($recs as $fpo) {
        //create combine order at the first FPO

        if ($cnt == 0) {
          $combine_order_no = self::_getNextOrderNo($fpo['id']);
          $model =  CombineOrder::create(['combine_order_no' => $combine_order_no,'description'=>$fpo['description']]);
          $cnt++;
        }

        if (!(isset($fpo['priority_seq']))) {
          throw new Exception("Priority sequence for Fpo's has not been defined.");
        }
        $fpo['combine_order_id'] = $model->id;
        unset($fpo['description']);
        unset($fpo['fabric_status']);
        unset($fpo['style_fabric_id']);
        unset($fpo['main_fabric']);
		
        $fpo_model = FpoRepository::updateRec($fpo['id'], $fpo);
        
		
      }
      self::_finalizeFpos($model);
      DB::commit();
      return response()->json(['status' => 'success', 'data' => $model], 200);
    } catch (Exception $e) {
      DB::rollBack();
      throw new \App\Exceptions\GeneralException($e->getMessage());
    }
  }

  public static function _getNextOrderNo($fpo_id)
  {
    $fpo = Fpo::where('id', $fpo_id)->with('soc')->first();

    $order_count = CombineOrder::select('combine_orders.combine_order_no')
      ->join('fpos', 'fpos.combine_order_id', '=', 'combine_orders.id')
      ->join('socs', 'fpos.soc_id', '=', 'socs.id')
      ->where('socs.buyer_id', $fpo->soc->buyer_id)
      ->where('socs.style_id', $fpo->soc->style_id)
      ->where('socs.customer_style_ref', $fpo->soc->customer_style_ref)
      ->distinct()->get();

    return (is_null($order_count->count()) ? 0 : $order_count->count()) + 1;
  }

  public static function _validateFpos($recs)
  {
    $buyer_id = null;
    $style_id = null;
    $customer_style_ref = null;

    foreach ($recs as $rec) {
      $fpo = Fpo::where('id', $rec['id'])->with('soc')->first();

      if ((is_null($buyer_id)) && (is_null($style_id)) && (is_null($customer_style_ref))) {
        $buyer_id = $fpo->soc->buyer_id;
        $style_id = $fpo->soc->style_id;
        $customer_style_ref = $fpo->soc->customer_style_ref;
      } else {
 		  //////////////////////// Original /////////////////////////////////////////////////
        //if (($fpo->soc->buyer_id != $buyer_id)  || ($fpo->soc->style_id != $style_id) || ($fpo->soc->customer_style_ref != $customer_style_ref)) {
         // throw new Exception('Fpos are not from the same origin');
        //}

		  /// Remove Style ID to Cut four style together  /////////////////
		  if (($fpo->soc->buyer_id != $buyer_id) || ($fpo->soc->customer_style_ref != $customer_style_ref)) {
          throw new Exception('Fpos are not from the same origin');
        }
      }
    }
  }

  public static function getConnectedFpos($combine_order_id)
  {
    $results = Soc::select(
      'socs.id as soc_id',
      'socs.wfx_soc_no as wfx_soc_no',
      'socs.garment_color as garment_color',
      'fpos.id as fpo_id',
      'fpos.wfx_fpo_no as wfx_fpo_no',
      'fpos.qty_json as qty_json',
      'fpos.qty_json_order as qty_json_order',
      'fpos.priority_seq as priority_seq',
      'fpos.updated_at as fpo_updated_at',
      'fpos.tolerance as tolerance'
    )
      ->join('fpos', 'fpos.soc_id', '=', 'socs.id')
      ->where('fpos.combine_order_id', $combine_order_id)
      ->distinct()->get();

    //sorting json
    foreach ($results as $key => $result) {
      if ((isset($result->qty_json)) && (isset($result->qty_json_order))) {
        $result->qty_json = Utilities::sortQtyJson($result->qty_json_order, $result->qty_json);
      }
    }

    return $results;
  }

  public static function getCombineOrdersByStyleRef($buyer_id, $style_id, $customer_style_ref)
  {
    $combine_orders = CombineOrder::select('combine_orders.id', 'combine_orders.combine_order_no','combine_orders.description')
      ->join('fpos', 'fpos.combine_order_id', '=', 'combine_orders.id')
      ->join('socs', 'fpos.soc_id', '=', 'socs.id')
      ->where('socs.buyer_id', $buyer_id)
      ->where('socs.style_id', $style_id)
      ->where('socs.customer_style_ref', $customer_style_ref)
      ->distinct()->get();

    return $combine_orders;
  }

  public static function getFabricInfo($combine_order_id)
  {
    $arr = [];
    $arrmain = [];
    $fpo_record = Fpo::where('combine_order_id', $combine_order_id)->with('fpo_fabrics')->first();
    foreach ($fpo_record->fpo_fabrics as $fpo_record) {
      $arr["fabric_id"] = $fpo_record->style_fabric_id;
      $arr["fabric"] = StyleFabric::find($fpo_record->style_fabric_id)->fabric;
      $arr["utilized"] = 0;
      $arr["main_fabric"] = 0;
      $arrmain[$fpo_record->style_fabric_id] = $arr;
    }

    $cut_plan_records = CutPlan::select('style_fabric_id', 'main_fabric')->where('combine_order_id', $combine_order_id)->get();
    foreach ($cut_plan_records as $cut_plan_record) {
      if (array_key_exists($cut_plan_record->style_fabric_id, $arrmain)) {
        $arrmain[$cut_plan_record->style_fabric_id]['utilized'] = 1;
        if ($cut_plan_record->main_fabric == 1) {
          $arrmain[$cut_plan_record->style_fabric_id]['main_fabric'] = 1;
        }
      }
    }
    return array_values($arrmain);
  }

  public static function getSearchByStyleRefCode()
  {

    $results = Soc::select(
      'socs.wfx_soc_no as soc_no',
      'buyers.id as buyer_id',
      'buyers.buyer_code as buyer_code',
      'styles.id as style_id',
      'styles.style_code as style_code',
      'socs.customer_style_ref as customer_style_ref',
      'combine_orders.id as combine_order_id',
      'combine_orders.combine_order_no as combine_order_no'
    )
      ->join('buyers', 'socs.buyer_id', '=', 'buyers.id')
      ->join('styles', 'socs.style_id', '=', 'styles.id')
      ->join('fpos', 'fpos.soc_id', '=', 'socs.id')
      ->join('combine_orders', 'fpos.combine_order_id', '=', 'combine_orders.id')
      ->distinct()->get();

    return $results;
  }

  public static function getSearchResultByStyleRefCode($buyer_code, $style_code, $customer_style_ref, $soc_no, $combine_order_no)
  {
    DB::enableQueryLog();
    $results = Soc::select(
      'socs.wfx_soc_no as soc_no',
      'buyers.id as buyer_id',
      'buyers.buyer_code as buyer_code',
      'styles.id as style_id',
      'styles.style_code as style_code',
      'socs.customer_style_ref as customer_style_ref',
      'combine_orders.id as combine_order_id',
      'combine_orders.combine_order_no as combine_order_no'
    )
      ->join('buyers', 'socs.buyer_id', '=', 'buyers.id')
      ->join('styles', 'socs.style_id', '=', 'styles.id')
      ->join('fpos', 'fpos.soc_id', '=', 'socs.id')
      ->join('combine_orders', 'fpos.combine_order_id', '=', 'combine_orders.id')
      ->where('buyers.buyer_code', 'LIKE', (is_null($buyer_code) ? '%' :  '%' . $buyer_code . '%'))
      ->where('styles.style_code',  'LIKE', (is_null($style_code) ? '%' :  '%' . $style_code . '%'))
      ->where('socs.customer_style_ref', 'LIKE', (is_null($customer_style_ref) ? '%' : '%' . $customer_style_ref . '%'))
      ->where('socs.wfx_soc_no', 'LIKE', (is_null($soc_no) ? '%' : '%' .  $soc_no . '%'))
      ->where('combine_orders.combine_order_no', 'LIKE', (is_null($combine_order_no) ? '%' :  '%' . $combine_order_no . '%'))
      ->get();
      //->distinct()->get();
    $quries = DB::getQueryLog();

    return $results;
  }

  public function generateCutPlan(Request $request)
  {
    // Check the Documents for the Algorithem for Cut Plans

    try {
      DB::beginTransaction();

      $markers = $request->all()['lay_marker_details'];
      $main_fabric = $request->all()['main_fabric'];
      $max_plies = (int)$request->all()['max_plies'];
      $combine_order_id = (int)$request->all()['combine_order_id'];
      $style_fabric_id = (int) $request->all()['fabric_id'];

      //validate main fabric if already exist
      if (isset($main_fabric)) {
        if ($main_fabric == 1) {
          $main_fabric_recs =   CutPlan::where('combine_order_id', $combine_order_id)->where('main_fabric', 1)->get();
          if ($main_fabric_recs->count() > 0)
            throw new Exception('Main Fabric is already set for this Combine Order');
        }
      }

      $cut_marker_matrix = [];
      $run_no = 0;

      $cut_count = DB::table('cut_plans')
      ->select(DB::raw('COUNT(*) as count'))
      ->where('cut_plans.combine_order_id', $combine_order_id)
      ->where('cut_plans.style_fabric_id', $style_fabric_id)
      ->get();

      if($cut_count[0]->count> 0){
        $run_no = $cut_count[0]->count;

        $main_f = DB::table('cut_plans')
        ->select('main_fabric')
        ->where('cut_plans.combine_order_id', $combine_order_id)
        ->where('cut_plans.style_fabric_id', $style_fabric_id)
        ->where('cut_plans.main_fabric', 1)
        ->first();

        if($main_f->main_fabric && $main_f->main_fabric == 1){
          $main_fabric = $main_f->main_fabric;
        }

      }

      // Generate Cut Plans
      foreach ($markers as $index => $marker) {

        $total_plies_required = $marker['total_plies'];
        $number_of_cuts_required = ceil($total_plies_required / $max_plies);

        for ($cut_no = 1; $cut_no < ($number_of_cuts_required + 1); $cut_no++) {
          $run_no++;
          //if this is the last cut handle it seprately
          //due to partial no of plies
          if ($cut_no == $number_of_cuts_required) {
            $cut_size = $total_plies_required  - (($number_of_cuts_required - 1) * $max_plies);
          } else {
            $cut_size = $max_plies;
          }

          foreach ($marker['qty_json'] as $size => $marker_qty) {
            $value_array[$size] = $cut_size * $marker_qty;
            $qty_array[$size] = $marker_qty;
          }
          $plan_array[] = [
            'cut_no' => 'Cut-' . $run_no,
            'ratio_json' => $qty_array,
            'value_json' => $value_array,
            'qty_json_order' => array_keys($value_array),
            'marker_name' => $marker['marker_name'],
            'yrds' => $marker['yrds'],
            'inch' => $marker['inch'],
            'acc_width' => $marker['acc_width'],
            'max_plies' => $cut_size,
            'main_fabric' => $main_fabric,
            'style_fabric_id' => $style_fabric_id,
            'combine_order_id' => $combine_order_id
          ];
        }
      }

      foreach ($plan_array as $cut_plan) {
        $cut_plan_array[] =   CutPlanRepository::createRec($cut_plan);
      }

      // Generate Consumption for each FPO
      $fpos = Fpo::where('combine_order_id', $combine_order_id)->orderBy('priority_seq', 'asc')->get();
      $this->_checkQuantities($markers, $fpos);
      $count =0;

      // Get Highest Fpo
      $highest_fpo_id = '';
      $sum = 0;
      $max_sum = 0;
      foreach ($fpos as $fpo) {
        $sum = array_reduce($fpo->qty_json, function ($carry, $item) {
          $carry = $carry + $item;
          return $carry;
        }, 0);
        if ($sum > $max_sum) {
          $highest_fpo_id = $fpo->id;
          $max_sum = $sum;
        }
      }

      /// get round method  
      $round_method = DB::table('team_validation_status')
      ->select('cutting_tolerance')
      ->first();

      $method = 'round';
      if(!is_null($round_method)){
        $method = $round_method->cutting_tolerance;
      }
      // Get Fpo Balance Quantity
      foreach($fpos as $rec){
        $qty = $rec->qty_json;
        /// q is a new quantity array with tolerance
        $q = [];
        $tolerance_json = $rec->soc->tolerance_json;
        foreach($qty as $key=>$val){
          if(!isset($q[$key])){
            
            if(!is_null($tolerance_json)){
              $q[$key] = $val+$method($val*intval($tolerance_json[$key])/100);
            }else{
              $q[$key] = $val;
            }
            
          }
        }

        $use_fpo = DB::table('cut_plans')
        ->select('fpo_cut_plans.qty_json')
        ->join('fpo_cut_plans', 'fpo_cut_plans.cut_plan_id', '=', 'cut_plans.id')
        ->where('fpo_cut_plans.fpo_id', $rec->id)
        ->where('cut_plans.style_fabric_id', $style_fabric_id)
        ->get();


        if(!is_null($use_fpo)){

          foreach($use_fpo as $use_rec){

            $use_qty = json_decode($use_rec->qty_json,true);

            foreach($use_qty as $key=>$val){
              if(intval($use_qty[$key]) > 0){
                $q[$key]=(array_key_exists($key, $q) ? $q[$key]- $use_qty[$key]: $rec->qty_json[$key] - $use_qty[$key]);
                if(($q[$key]) < 0){
                  $q[$key] = 0;
                }
              }
            }
          }

          $fpos[$count]->qty_json = $q;
        }
        $count++;

      }
      //sorting json

      foreach ($fpos  as $key => $result) {
        if ((isset($result->qty_json)) && (isset($result->qty_json_order))) {
        //  $result->qty_json = Utilities::sortQtyJson($result->qty_json_order, $result->qty_json);
        }
      }

      $array_cnt_ = 0;
      $fpo_count_array = [];
      $qty_json = $fpos[0]->qty_json;
      $concat_arr = [];

      foreach ($fpos as $fpo) {

        /////////////////////  for Fpo different Size  //////////////////
        foreach($fpo->qty_json as $key =>$val){
          if(!(isset($qty_json[$key]))){
            $qty_json[$key] = $val;
          }
        }
      }

      foreach ($qty_json as $size_index => $value) {

        // generate the fpo_count_array
        $array_cnt_ = 0;
        foreach ($fpos as $fpo) {
         // $repeat_index = $fpo->qty_json[$size_index];
		      $repeat_index =0;

          if(isset($fpo->qty_json[$size_index])){

            $repeat_index = $fpo->qty_json[$size_index];

          }


          //handle 0
          if (array_key_exists($size_index, $fpo_count_array)) {
            $fpo_count_array[$size_index] = array_merge($fpo_count_array[$size_index], array_fill($array_cnt_, $repeat_index, $fpo->id));
          } else {
            $fpo_count_array[$size_index] = array_fill($array_cnt_, $repeat_index, $fpo->id);

          }
          $array_cnt_ = $array_cnt_ + $repeat_index;
        }

        // generate the cut_count_array
        $cut_count_array = [];
        $array_cnt_ = 0;
        foreach ($cut_plan_array as $plan) {
			    $repeat_index =0;
          if(isset($plan['value_json'][$size_index])){
            $repeat_index = $plan['value_json'][$size_index];
          }

          if (array_key_exists($size_index, $cut_count_array)) {
            $cut_count_array[$size_index] = array_merge($cut_count_array[$size_index], array_fill($array_cnt_, $repeat_index, $plan['id']));
          } else {
            $cut_count_array[$size_index] = array_fill($array_cnt_, $repeat_index, $plan['id']);
          }
          $array_cnt_ = $array_cnt_ + $repeat_index;
        }

        //Scenario 1 - FPO Qantity is Less than Cut Quantity
        // Handle Last items since cut plan qty can be greater than FPO quantity
        if (!(empty($cut_count_array[$size_index])) ) {

          if (sizeof($cut_count_array[$size_index]) >= sizeof($fpo_count_array[$size_index])) {

            $last_item = 0;
            for ($i = 0; $i < sizeof($cut_count_array[$size_index]); $i++) {

              if (array_key_exists($i, $fpo_count_array[$size_index])) {

                $concat_arr[$size_index][] = $fpo_count_array[$size_index][$i] . '^' . $cut_count_array[$size_index][$i];
                $last_item = $i;
              } else {
              
                //////////////////  GET Max Fpo_id By Size //
                $original_fpos = Fpo::where('combine_order_id', $combine_order_id)->orderBy('priority_seq', 'asc')->get();
                $max_fpo_id=0;
                $max_qty = 0;
                foreach ($original_fpos as $fpo) {

                  $qty_json = json_decode(json_encode($fpo->qty_json,true));
                    foreach($qty_json as $k => $v){

                      if(strcmp($k,$size_index) == 0 && intval($v) > $max_qty){
                        $max_qty = intval($v);
                        $max_fpo_id = $fpo->id;

                      }
                    }
                }
                ////////////////////////////////////////////////////

                $concat_arr[$size_index][] = $max_fpo_id . '^' . $cut_count_array[$size_index][$i];
              }
            }
          }
          //Scenario 1 - FPO Qantity is Greater than Cut Quantity
          else {
            for ($i = 0; $i < sizeof($cut_count_array[$size_index]); $i++) {
              if (array_key_exists($i, $fpo_count_array[$size_index])) {
                $concat_arr[$size_index][] = $fpo_count_array[$size_index][$i] . '^' . $cut_count_array[$size_index][$i];
                $last_item = $i;
              }
            }
          }

          $final_arr[$size_index] = array_count_values($concat_arr[$size_index]);
        }

      }


      //Generate the output in require format
      $transform_array = [];
      foreach ($qty_json as $key => $value) {
        if (array_key_exists($key, $final_arr)) {
          foreach ($final_arr[$key] as $str => $qty) {
            $transform_array[$str][$key] = $qty;
          }
        }
      }
      $line_no = 0;

      foreach ($transform_array as $str => $value) {
        $consumption = 0;
        $qty_total = 0;

        //AVG Consumption Calculation
        $fpo_id = explode("^", $str)[0];

        if (!($fpo_id == 0)) {

          //////////////////////////////  For Different Style  //////////////////////////////////////////
          $fabric =DB::table('style_fabrics')
                        ->select('style_fabrics.fabric')
                        ->where('style_fabrics.id', $style_fabric_id)
                        ->first();

          $style_fabic = DB::table('fpos')
                        ->select('style_fabrics.id')
                        ->join('socs', 'socs.id','=','fpos.soc_id')
                        ->join('style_fabrics', 'style_fabrics.style_id','=','socs.style_id')
                        ->where('fpos.id', $fpo_id)
                        ->where('style_fabrics.fabric', $fabric->fabric)
                        ->first();


          $obj_fpofabric = FpoFabric::select('avg_consumption')->where('style_fabric_id', $style_fabic->id)->where('fpo_id', $fpo_id)->first();

              foreach ($value as $key => $qty) {
                $qty_total = $qty_total + $qty;
              }

              if (isset($qty_total)) {
                if (!(is_null($obj_fpofabric->avg_consumption))) {
                  $consumption = $qty_total * $obj_fpofabric->avg_consumption;
                }
              }
        }

        //$value = Utilities::fillJsonMissingSizesValues(array_keys($qty_json), $value);
        $db_array[] = [
          'fpo_id' => explode("^", $str)[0],
          'cut_plan_id' => explode("^", $str)[1],
          'qty_json' => $value,
          'qty_json_order' => array_keys($value),
          'line_no' => ++$line_no,
          'consumption' => $consumption
        ];
      }
      // Log::info($db_array);
      // Log::info('-----db_array-----');
      // Log::info($db_array);
      // Log::info('-----------');
      // Log::info('-----fpo_count_array-----');
      // Log::info($fpo_count_array);
      // Log::info('-----------');
      // Log::info('-----concat_arr-----');
      // Log::info($concat_arr);
      // Log::info('-----------');

      // throw new \App\Exceptions\GeneralException("error");

      foreach ($db_array as $fpo_cut_plan) {
        FpoCutPlanRepository::createRec($fpo_cut_plan);
      }
      // DB::rollback();
      DB::commit();
      return response()->json(['status' => 'success'], 200);
    } catch (Exception $e) {
      DB::rollBack();
      throw new \App\Exceptions\GeneralException($e->getMessage());
    }
  }

  private function _checkQuantities($markers, $fpos)
  {
    $fpos_qty_json = array_reduce($fpos->all(), function ($carry, $item) {
      // $qtyjson =  Fpo::where('id', $item['id'])->first();
      // $item['qty_json']=$qtyjson->qty_json;
      foreach ($item['qty_json'] as $size => $qty) {
        if (array_key_exists($size, (array)$carry)) {
          $carry[$size] += $qty;
        } else {
          $carry[$size] = $qty;
        }
      }
      return $carry;
    }, []);

    $markers_qty_json = array_reduce($markers, function ($carry, $item) {
      // $qtyjson =  Fpo::where('id', $$item[id])->first();
      // $item['qty_json']=$qtyjson->qty_json;
      foreach ($item['qty_json'] as $size => $qty) {
        if (array_key_exists($size, (array)$carry)) {
          $carry[$size] += $qty;
        } else {
          $carry[$size] = $qty;
        }
      }
      return $carry;
    }, []);

    foreach ($fpos_qty_json as $size => $qty) {
		$marker_qty = 0;
		if(isset($markers_qty_json[$size])){
			$marker_qty = is_null($markers_qty_json[$size]) ? 0 : $markers_qty_json[$size];
		}

      if (((is_null($qty) ? 0 : $qty) == 0) && ($marker_qty != 0)) {
        throw new Exception("Markers cannot have quantity for '" . $size . "' since Fpo has zero quantity for the same size.");
      }
    }

  }

  private static function _finalizeFpos(CombineOrder $co)
  {
    $fpos = Fpo::where('combine_order_id', $co->id)->get();
    foreach ($fpos as $fpo) {
      FpoController::fsmActionClose($fpo);
    }
  }

  private static function _reopenFpos($co_id)
  {
    $fpos = Fpo::where('combine_order_id', $co_id)->get();
    foreach ($fpos as $fpo) {
      FpoController::fsmActionReopen($fpo);
    }
  }

  public function getFpoFabricAndOperation($fpo_id){

    $FpoFabric = DB::table('fpo_fabrics')
    ->select('style_fabrics.fabric')
    ->join('style_fabrics', 'style_fabrics.id', '=', 'fpo_fabrics.style_fabric_id')
    ->join('fpos', 'fpos.id', '=', 'fpo_fabrics.fpo_id')
    ->where('fpos.wfx_fpo_no', $fpo_id)
    ->first();

    if(is_null($FpoFabric)){
      return response()->json(["status" => "NoFabric", "message" => "NoFabric"], 200);
    }
    else{
      $Operation = DB::table('fpo_operations')
      ->select('routing_operations.id', 'routing_operations.operation_code')
      ->join('routing_operations', 'routing_operations.id', '=', 'fpo_operations.routing_operation_id')
      ->join('fpos', 'fpos.id', '=', 'fpo_operations.fpo_id')
      ->where('fpos.wfx_fpo_no', $fpo_id)
      ->get();

      if(is_null($Operation)){
        return response()->json(["status" => "NoOperation", "message" => "NoOperation"], 200);
      }
      else if(sizeof($Operation) == 0){
          return response()->json(["status" => "NoOperation", "message" => "NoOperation"], 200);
      }
      else if(sizeof($Operation) == 1){
          if(substr($Operation[0]->operation_code, 0 ,3) == "SUB"){
              return response()->json(["status" => "NoOperation", "message" => "NoOperation"], 200);
          }
          else if(substr($Operation[0]->operation_code, 0 ,2) == "CT"){
              return response()->json(["status" => "NoOperation", "message" => "NoOperation"], 200);
          }
          else{
              return response()->json(["status" => "AllFound", "message" => "AllFound"], 200);
          }
      }

      else{
          $haveSUB = false;

          foreach ($Operation as $op){
//              print_r(substr($op->operation_code, 0 ,3));
              if(substr($op->operation_code, 0 ,3) == "SUB"){
                  $haveSUB = true;
              }
          }

          if($haveSUB){
              return response()->json(["status" => "NoOperation", "message" => "NoOperation"], 200);
          }
          else{
              return response()->json(["status" => "AllFound", "message" => "AllFound"], 200);
          }
      }

    }

//  else if(sizeof($Operation) == 2){
//          $haveCT = false;
//          $haveSUB = false;
//          foreach ($Operation as $op){
//              if(substr($op->operation_code, 0 ,3) == "SUB"){
//                  $haveSUB = true;
//              }
//              if(substr($op->operation_code, 0 ,2) == "CT"){
//                  $haveCT = true;
//              }
//          }
//
//          if($haveSUB && $haveCT){
//              return response()->json(["status" => "NoOperation", "message" => "NoOperation"], 200);
//          }
//          else{
//              return response()->json(["status" => "AllFound", "message" => "AllFound"], 200);
//          }
//      }

    // $FpoFabric = FpoFabric::select(
    //   'style_fabrics.fabric'
    // )
    //   ->join('style_fabrics', 'style_fabrics.id', '=', 'fpo_fabrics.style_fabric_id')
    //   ->where('fpo_fabrics.fpo_id', $fpo_id)
    //   ->get();
    //   print_r($FpoFabric);
    // if($FpoFabric != null){
    //   print_r($FpoFabric);
    //  // return true;
    // }

    //return true;
  }

  public function getCombineOrderTotalQty($combine_order_id){

    /// get round method from 
    $round_method = DB::table('team_validation_status')
    ->select('cutting_tolerance')
    ->first();

    $method = 'round';
    if(!is_null($round_method)){
      $method = $round_method->cutting_tolerance;
    }


    $fpos = Fpo::where('combine_order_id', $combine_order_id)->get();
    $sum_total_qty = [];
    $sum_total_qty_with_tolerance = [];
    $total = [];

    foreach($fpos as $rec){
      $qty = $rec->qty_json;
     // $tolerance = 0;
     $tolerance_json = $rec->soc->tolerance_json;

      foreach($qty as $key => $value){
        $sum_total_qty[$key] = $value + (array_key_exists($key, $sum_total_qty) ? $sum_total_qty[$key] : 0);
        if(!is_null($tolerance_json)){
          $sum_total_qty_with_tolerance[$key] = ($value+$method($value*intval($tolerance_json[$key])/100)) + (array_key_exists($key, $sum_total_qty_with_tolerance) ? $sum_total_qty_with_tolerance[$key] : 0);
        }else{
          $sum_total_qty_with_tolerance[$key] = ($value) + (array_key_exists($key, $sum_total_qty_with_tolerance) ? $sum_total_qty_with_tolerance[$key] : 0);
        }
        
      }
    }
    $total['sum'] = $sum_total_qty;
    $total['sum_with_tolerancer'] = $sum_total_qty_with_tolerance;
    return $total;
  }
}
