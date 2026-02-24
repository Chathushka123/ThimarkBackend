<?php

namespace App\Http\Repositories;

use App\Bundle;
use App\BundleMovement;
use App\ExcessBundle;
use Illuminate\Http\Request;
use App\CutPlan;
use App\CutUpdate;
use App\Exceptions\ConcurrencyCheckFailedException;
use App\LaySheet;
use App\Fppo;
use PDF;
// use App\HashStore;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use App\Http\Resources\CutPlanWithParentsResource;
use Illuminate\Validation\Rule;
use Exception;
use App\Exceptions\GeneralException;
use App\FpoCutPlan;
use App\DailyShift;
use App\Http\Validators\CutPlanCreateValidator;
use App\Http\Validators\CutPlanUpdateValidator;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;

class CutPlanRepository
{
  public function show(CutPlan $cutPlan)
  {
    return response()->json(
      [
        'status' => 'success',
        'data' => new CutPlanWithParentsResource($cutPlan),
      ],
      200
    );
  }

  public static function createRec(array $rec)
  {

    $rec['ratio_json'] = json_encode($rec['ratio_json']);
    $rec['value_json'] = json_encode($rec['value_json']);
    $validator = Validator::make(
      $rec,
      CutPlanCreateValidator::getCreateRules()
    );

    if ($validator->fails()) {
      throw new Exception($validator->errors());
    }
    $rec['ratio_json'] = json_decode($rec['ratio_json']);
    $rec['value_json'] = json_decode($rec['value_json']);
    try {

      $model = CutPlan::create($rec);
    } catch (Exception $e) {
      throw new \App\Exceptions\GeneralException($e->getMessage());
    }

    return $model;
  }

  public static function updateRec($model_id, array $rec)
  {
    // if ($master_id == null) {
    $model = CutPlan::findOrFail($model_id);
    // } else {
    //   $model = CutPlan::where($parent_key, $master_id)
    //     ->where('id', $model_id)
    //     ->firstOrFail();
    // }
    // return $model;
    if (!$model->updated_at->eq(\Carbon\Carbon::parse($rec['updated_at']))) {
      $entity = (new \ReflectionClass($model))->getShortName();
      throw new ConcurrencyCheckFailedException($entity);
    }
    $rec['ratio_json'] = json_encode($rec['ratio_json']);
    $rec['value_json'] = json_encode($rec['value_json']);
    Utilities::hydrate($model, $rec);
    $validator = Validator::make(
      $rec,
      CutPlanUpdateValidator::getUpdateRules($model_id)
    );
    if ($validator->fails()) {
      throw new Exception($validator->errors());
    }
    $rec['ratio_json'] = json_decode($rec['ratio_json']);
    $rec['value_json'] = json_decode($rec['value_json']);
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
    Log::info($recs);
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
    CutPlan::destroy($recs);
  }

  public static function getCutPlan($combine_order_id, $style_fabric_id)
  {

    $results = CutPlan::where('combine_order_id', $combine_order_id)->where('style_fabric_id', $style_fabric_id)->get();
    //sorting json
    foreach ($results  as $key => $result) {
      if ((isset($result->value_json)) && (isset($result->qty_json_order))) {
        $result->value_json = Utilities::sortQtyJson($result->qty_json_order, $result->value_json);
      }
    }

    return $results;
  }

  public static function deleteCutPlansByCombineOrder($combine_order_id, $style_fabric_id)
  {
    try {

      DB::beginTransaction();

      $cut_plans = CutPlan::where('combine_order_id', $combine_order_id)
        ->where('style_fabric_id', $style_fabric_id)
        ->pluck('id')->toArray();

      if (CutUpdate::whereIn('cut_plan_id', $cut_plans)->exists()) {
        throw new \App\Exceptions\GeneralException('Cut Plans has been progressed, Not allowed to delete');
      };

      if (FpoCutPlan::whereIn('cut_plan_id', $cut_plans)->whereNotNull('fppo_id')->exists()) {
        throw new \App\Exceptions\GeneralException('Cut Plans has been progressed, Not allowed to delete');
      };

      foreach ($cut_plans as $id) {
        FpoCutPlanRepository::deleteRecs(
          FpoCutPlan::where('cut_plan_id', $id)->pluck('id')->toArray()
        );
        
      }
      CutPlan::destroy($cut_plans);

      DB::commit();
      return response()->json(
        [
          'status' => 'success'         
        ],
        200
      );
    } catch (Exception $e) {
      DB::rollBack();
      return response()->json(
        [
          'status' => 'error',
          'message' => $e->getMessage()
        ],
        400
      );
    }
  }

  public function getCutLaySheet($cut_id){


     
    $data = [];
    $data = ['ID' => $cut_id];

    $cut = DB::table('cut_plans')
    
    ->select('cut_plans.id','cut_plans.cut_no','cut_plans.max_plies','cut_plans.marker_name','cut_plans.ratio_json as value_json','cut_plans.acc_width','cut_plans.yrds','cut_plans.inch','fpo_cut_plans.line_no','socs.wfx_soc_no','socs.garment_color','socs.customer_style_ref','fpos.wfx_fpo_no','styles.style_code','buyers.name','style_fabrics.fabric')
    ->join('fpo_cut_plans', 'fpo_cut_plans.cut_plan_id', '=', 'cut_plans.id')
    ->join('fpos', 'fpos.id', '=', 'fpo_cut_plans.fpo_id')
    ->join('socs', 'socs.id', '=', 'fpos.soc_id')
    ->join('styles', 'styles.id', '=', 'socs.style_id')
    ->join('buyers', 'buyers.id', '=', 'socs.buyer_id')
    ->join('style_fabrics', 'style_fabrics.id', '=', 'cut_plans.style_fabric_id')
    ->where('cut_plans.id', $cut_id)
    ->get();

    $fppo = DB::table('cut_plans')
    
    ->select('fppos.fppo_no')
    ->join('fpo_cut_plans', 'fpo_cut_plans.cut_plan_id', '=', 'cut_plans.id')
    ->join('fppos', 'fppos.id', '=', 'fpo_cut_plans.fppo_id')
    ->where('cut_plans.id', $cut_id)
    ->get();

    $consumption = DB::table('cut_plans')
    ->select('fpo_fabrics.*')
    ->join('fpo_cut_plans', 'fpo_cut_plans.cut_plan_id', '=', 'cut_plans.id')
    ->join("fpo_fabrics",function($join){
      $join->on("fpo_fabrics.style_fabric_id","=","cut_plans.style_fabric_id")
          ->on("fpo_fabrics.fpo_id","=","fpo_cut_plans.fpo_id");
    })
    ->where('cut_plans.id', $cut_id)
    ->get();


    

    // $fpo_soc = DB::table('carton_packing_list')
    // ->select('carton_packing_list.*','cartons.carton_type')
    // ->join('cartons', 'cartons.id', '=', 'carton_packing_list.carton_id')
    // ->where('carton_packing_list.packing_list_id', $packing_list_id)
    // ->orderby('carton_packing_list.id')
    // ->get();

    // $carton = DB::table('carton_packing_list')
    // ->select('carton_packing_list.*')
    // ->where('carton_packing_list.packing_list_id', $packing_list_id)
    // ->orderby('id')
    // ->get();
    $data = ['ID' => $cut_id,'cut'=>$cut,'consumption'=>$consumption,'fppo'=>$fppo];
    //return $data;
    $pdf = PDF::loadView('print.cut_lay_sheet_report', $data);
    return $pdf->stream('trims_report_' . date('Y_m_d_H_i_s') . '.pdf');


  }

  public function saveSpecialRemarks($cut_no,$special_remark){
    try {

      DB::beginTransaction();

      $model= DB::table('cut_plans')
      ->where('id', $cut_no)
      ->update(['special_remark' => $special_remark]);

      
      DB::commit();
      return response()->json(['status' => 'success', 'data' => $model], 200);
    } catch (Exception $e) {
      DB::rollBack();
      throw new \App\Exceptions\GeneralException($e->getMessage());
    }
  }

  public function saveFpoTolerance($request){
    $fpo = $request->fpo;

    try {

      DB::beginTransaction();

      foreach($fpo as $key=>$val){    
        $cut_plan= DB::table('fpos')
        ->select('fpos.id')
        ->join('fpo_cut_plans', 'fpo_cut_plans.fpo_id', '=', 'fpos.id')
        ->where('fpos.wfx_fpo_no', $val['fpo'])
        ->get();
        
        if(count($cut_plan) > 0){
          throw new \App\Exceptions\GeneralException("Ratio Plan Already Exists, Can't Update tolerance for Fpo '".$val['fpo']."'");
        }
        else{
          $model= DB::table('fpos')
          ->where('wfx_fpo_no', $val['fpo'])
          ->update(['tolerance' => $val['tolerance']]);
        }
        
      }
      
      DB::commit();
      return response()->json(['status' => 'success', 'data' => $model], 200);
    } catch (Exception $e) {
      DB::rollBack();
      throw new \App\Exceptions\GeneralException($e->getMessage());
    }
  }

  public function getSpecialRemarks($cut_no){
    $remark = DB::table('cut_plans')
    ->select('special_remark')
    ->where('cut_plans.id', $cut_no)
    ->first();

    return response()->json(['status' => 'success', 'data' => $remark], 200);
  }

  public function getBundleTagReport($cut_no,$type){

    $cut_plans = CutPlan::distinct('cut_updates->bundle_cut_updates->bundle_id')->findOrFail($cut_no);
    
    $cut_update =array();
    foreach($cut_plans->cut_updates as $rec){
      //foreach($rec as $key=>$val){
        array_push($cut_update,$rec->id);
     // }
      
    }

    if($type == 'bundleID'){
      $bundle = Bundle::select(
        'bundles.*','socs.wfx_soc_no','fpos.wfx_fpo_no','socs.garment_color','fppos.fppo_no'      
      ) 
        ->join('bundle_cut_update', 'bundle_cut_update.bundle_id', '=', 'bundles.id')
        ->join('cut_updates', 'cut_updates.id', '=', 'bundle_cut_update.cut_update_id')
        ->join('fpo_cut_plans', 'fpo_cut_plans.fppo_id', '=', 'cut_updates.fppo_id')
        ->join('fppos', 'fppos.id', '=', 'cut_updates.fppo_id')
        ->join('fpos', 'fpos.id', '=', 'fpo_cut_plans.fpo_id')
        ->join('socs', 'socs.id', '=', 'fpos.soc_id')
        ->distinct('bundle_cut_update.bundle_id')
        ->whereIn('bundle_cut_update.cut_update_id', $cut_update)
        ->where('fpo_cut_plans.cut_plan_id', $cut_plans->id)
        ->orderby('bundles.id','ASC')
        ->get();
    }
    else if($type == 'size'){
      $bundle = Bundle::select(
        'bundles.*','socs.wfx_soc_no','fpos.wfx_fpo_no','socs.garment_color','fppos.fppo_no'      
      ) 
        ->join('bundle_cut_update', 'bundle_cut_update.bundle_id', '=', 'bundles.id')
        ->join('cut_updates', 'cut_updates.id', '=', 'bundle_cut_update.cut_update_id')
        ->join('fpo_cut_plans', 'fpo_cut_plans.fppo_id', '=', 'cut_updates.fppo_id')
        ->join('fppos', 'fppos.id', '=', 'cut_updates.fppo_id')
        ->join('fpos', 'fpos.id', '=', 'fpo_cut_plans.fpo_id')
        ->join('socs', 'socs.id', '=', 'fpos.soc_id')
        ->distinct('bundle_cut_update.bundle_id')
        ->whereIn('bundle_cut_update.cut_update_id', $cut_update)
        ->where('fpo_cut_plans.cut_plan_id', $cut_plans->id)
        ->orderby('bundles.size','ASC')
        ->orderby('bundles.id','ASC')
        ->get();
    }


    $data = ['cut_plans' => $cut_plans, 'bundle'=>$bundle];
    
    $pdf = PDF::loadView('print.bundle_tag_report', $data);
    $pdf->setPaper('A4', 'portrait');
    return $pdf->stream('bundle_tag_report' . date('Y_m_d_H_i_s') . '.pdf');
  }

  public function getBundleReport($fppo){

    $cut_no = DB::table('fpo_cut_plans')
    ->select('fpo_cut_plans.cut_plan_id','fpo_cut_plans.batch_no')
    ->join('cut_plans', 'cut_plans.id', '=', 'fpo_cut_plans.cut_plan_id')
    ->where('fppo_id',$fppo)
    ->first();

    $cut_plans = CutPlan::distinct('cut_updates->bundle_cut_updates->bundle_id')->findOrFail($cut_no->cut_plan_id);

      $bundle = Bundle::select(
        'bundles.*','socs.wfx_soc_no','fpos.wfx_fpo_no','socs.garment_color','socs.ColorName','fppos.fppo_no','fpos.order_type','users.email','fpo_cut_plans.batch_no'      
      ) 
        ->join('fpo_cut_plans', 'fpo_cut_plans.fppo_id', '=', 'bundles.fppo_id')
        ->join('fppos', 'fppos.id', '=', 'fpo_cut_plans.fppo_id')
        ->join('fpos', 'fpos.id', '=', 'fpo_cut_plans.fpo_id')
        ->join('socs', 'socs.id', '=', 'fpos.soc_id')
        ->join('users','users.id','=','fpo_cut_plans.user_id')
        ->distinct('bundle_cut_update.bundle_id')
        // ->where('fpo_cut_plans.fppo_id', $fppo)
        ->where('fpo_cut_plans.batch_no', $cut_no->batch_no)
        ->orderby('bundles.id','ASC')
        ->get();
    
      $data = ['cut_plans' => $cut_plans, 'bundle'=>$bundle];
    
    $pdf = PDF::loadView('print.bundle_tag_report', $data);
    $pdf->setPaper('A4', 'portrait');
    return $pdf->stream('bundle_report' . date('Y_m_d_H_i_s') . '.pdf');
  }

  public function updateBundleLocation($request){
    try {

    DB::beginTransaction();
    
    $bundle_id = $request->id;
    $length = strlen($request->id);
    if(substr($bundle_id,$length-1,1) == "E"){
      $bundle_id = substr($bundle_id,0,$length-1);
      $model = self::updateExcessBundleLocation($bundle_id,$request);
    }else{
      $bundle_id =intval($bundle_id);
      date_default_timezone_set("Asia/Calcutta"); 
      $dateTime = date("Y-m-d H:i:s");
    
      $shift = DailyShift::select('daily_shifts.id')
      ->join('shift_details', 'shift_details.id', '=', 'daily_shifts.shift_detail_id')
      ->join('shifts', 'shift_details.shift_id', '=', 'shifts.id')
        ->where('daily_shifts.start_date_time','<=' ,$dateTime)
        ->where('daily_shifts.end_date_time','>=' ,$dateTime)
        ->where('shifts.name','!=' ,"SHIFT-GENERAL")
        ->first();

      if(is_null($shift)){
        throw new \App\Exceptions\GeneralException("Shift Not Found");
      }

      $location = DB::table('locations')->where('id',$request->location)->get();
	  //print_r($location);
      if(is_null($location) || sizeof($location)===0 || $location[0]->site == "EA_Send"){
        throw new \App\Exceptions\GeneralException("Invalid Location");
      }


      $bundle = Bundle::where('id',$bundle_id)->get();

      if(is_null($bundle) || sizeof($bundle)===0){
        throw new \App\Exceptions\GeneralException("Invalid Bundle ID");
      }

      //////////////  Validate Bundle Location  /////////////////////
      $bundle_location = DB::table('bundles')
      ->select('locations.site')
      ->join('locations', 'locations.id', '=', 'bundles.location_id')
      ->where('locations.id',$bundle[0]->location_id)
      ->first();


      if(!is_null($bundle_location) && $bundle_location->site == "EA_Send"){
        throw new \App\Exceptions\GeneralException("Emb Bundle Can't be Scan");
      }

      /////////////////////////////////////////////////////////////////

      if($bundle[0]->location_id != null && $request->change_location == 0){
        throw new \App\Exceptions\GeneralException("Bundle Already Scan");
      }

      if($bundle[0]->first_grn_date != null){
        $model = Bundle::where('id',$bundle_id)->update(['location_id'=>$request->location,'daily_shift_id'=>$shift->id]);
      }else{
        
        $model = Bundle::where('id',$bundle_id)->update(['location_id'=>$request->location,'daily_shift_id'=>$shift->id,'first_grn_date'=>now('Asia/Kolkata')]);
      }

      //////////////////////// UPDATE Bundle Movement LOG /////////////////////////////////////////
      BundleMovement::create(['bundle_id'=>$bundle_id,'location_id'=>$request->location,'scan_by'=>auth()->user()->id,'scan_date_time'=>now()]);

  }
    
    DB::commit();
    return response()->json(['status' => 'success', 'data' => $model], 200);
    } catch (Exception $e) {
      DB::rollBack();
      throw new \App\Exceptions\GeneralException($e->getMessage());
    }
  }

  public function updateExcessBundleLocation($bundle_id,$request){
    $bundle_id =intval($bundle_id);
    date_default_timezone_set("Asia/Calcutta"); 
    $dateTime = date("Y-m-d H:i:s");
  
    $shift = DailyShift::select('id')
      
      ->where('daily_shifts.start_date_time','<=' ,$dateTime)
      ->where('daily_shifts.end_date_time','>=' ,$dateTime)
      ->first();

    if(is_null($shift)){
      throw new \App\Exceptions\GeneralException("Shift Not Found");
    }

    $location = DB::table('locations')->where('id',$request->location)->get();
    if(is_null($location) || sizeof($location)===0){
      throw new \App\Exceptions\GeneralException("Invalid Location");
    }
    $bundle = ExcessBundle::where('id',$bundle_id)->get();
    if(is_null($bundle) || sizeof($bundle)===0){
      throw new \App\Exceptions\GeneralException("Invalid Bundle ID");
    }

    $location = DB::table('locations')->where('id',$request->location)->get();
    if(($bundle[0]->location_id != null && $request->change_location == 0) || $location[0]->site == "EA_Send" ){
      throw new \App\Exceptions\GeneralException("Bundle Already Scan");
    }

    //////////////////////////////  Validate Bundle Location ////////////////////////////

    $bundle_location = DB::table('locations')
    ->select('locations.site')
    ->where('locations.id',$bundle[0]->location_id)
    ->first();
	
	//return $$bundle_location;
    if(!is_null($bundle_location) && $bundle_location->site == "EA_Send"){
      throw new \App\Exceptions\GeneralException("Emb Bundle Can't be Scan");
    }

    //////////////////////////////////////////////////////////////////////////////////

    if($bundle[0]->first_grn_date != null){
      $model = ExcessBundle::where('id',$bundle_id)->update(['location_id'=>$request->location,'daily_shift_id'=>$shift->id]);
    }else{
      
      $model = ExcessBundle::where('id',$bundle_id)->update(['location_id'=>$request->location,'daily_shift_id'=>$shift->id,'first_grn_date'=>now('Asia/Kolkata')]);
    }

    BundleMovement::create(['bundle_id'=>$bundle_id."E",'location_id'=>$request->location,'scan_by'=>auth()->user()->id,'scan_date_time'=>now()]);
    return $model;
  }

  public function getDailyTransfer($request){
    $location = $request->location;
    if($location != "NaN"){
      

      $daily_transfer = DB::table('bundles')
      ->select('bundles.id','bundles.updated_at','bundles.size','locations.location_name')
      ->join('locations', 'locations.id', '=', 'bundles.location_id')
      ->join('daily_shifts', 'daily_shifts.id', '=', 'bundles.daily_shift_id')
      ->where('daily_shifts.current_date',date('Y-m-d'))
      ->where('bundles.location_id',$location)
      ->orderby('bundles.updated_at','DESC')
      ->limit(10)
      ->get();

      $daily_excess_transfer = DB::table('excess_bundles')
      ->select('excess_bundles.bundle_id as id','excess_bundles.updated_at','excess_bundles.size','locations.location_name')
      ->join('locations', 'locations.id', '=', 'excess_bundles.location_id')
      ->join('daily_shifts', 'daily_shifts.id', '=', 'excess_bundles.daily_shift_id')
      ->where('daily_shifts.current_date',date('Y-m-d'))
      ->where('excess_bundles.location_id',$location)
      ->orderby('excess_bundles.updated_at','DESC')
      ->limit(10)
      ->get();

      $data = self::get_sorted_array($daily_transfer,$daily_excess_transfer);
      return response()->json(['status' => 'success', 'data' => $data], 200);
    }else{
      $daily_transfer = DB::table('bundles')
      ->select('bundles.id','bundles.updated_at','bundles.size','locations.location_name')
      ->join('locations', 'locations.id', '=', 'bundles.location_id')
      ->join('daily_shifts', 'daily_shifts.id', '=', 'bundles.daily_shift_id')
      ->where('daily_shifts.current_date',date('Y-m-d'))
      ->orderby('bundles.updated_at','DESC')
      ->limit(10)
      ->get();

      $daily_excess_transfer = DB::table('excess_bundles')
      ->select('excess_bundles.bundle_id as id','excess_bundles.updated_at','excess_bundles.size','locations.location_name')
      ->join('locations', 'locations.id', '=', 'excess_bundles.location_id')
      ->join('daily_shifts', 'daily_shifts.id', '=', 'excess_bundles.daily_shift_id')
      ->where('daily_shifts.current_date',date('Y-m-d'))
      ->orderby('excess_bundles.updated_at','DESC')
      ->limit(10)
      ->get();

      $data = self::get_sorted_array($daily_transfer,$daily_excess_transfer);

      return response()->json(['status' => 'success', 'data' => $data], 200);
    }
    
  }


  public function get_sorted_array($rec1,$rec2){
    $data =[];

    $index =0;
    foreach($rec1 as $rec){
      $data[$index] = $rec;
      $index++;
    }

    foreach($rec2 as $rec){
      $data[$index] = $rec;
      $index++;
    }

    $sorted_data =[];
    $index = 0;
    foreach($data as $key => $val){
      $rec = $val;
     
      if(count($sorted_data) ==0){
        $sorted_data[$index] = $rec;
        $index++;
      }else{
        for($i =0; $i < count($sorted_data); $i++){
          if($sorted_data[$i]->updated_at < $rec->updated_at ){
            $temp = $sorted_data[$i];
            $sorted_data[$i] = $rec;
            $rec = $temp;
            
          }
        }
        $sorted_data[$index] = $rec;
        $index++;
      }
    }
    

    return $sorted_data;
  }

  public function getBundleReportByMultipleFppo($request){
    $cut_no = DB::table('fpo_cut_plans')
    ->select('fpo_cut_plans.cut_plan_id')
    ->join('cut_plans', 'cut_plans.id', '=', 'fpo_cut_plans.cut_plan_id')
    ->whereIn('fppo_id',$request->fppo_list)
    ->get()
    ->toArray();
    //return $cut_no;
    $cut_plans = CutPlan::distinct('cut_updates->bundle_cut_updates->bundle_id')->findOrFail($cut_no[0]->cut_plan_id);

      $bundle = Bundle::select(
        'bundles.*','socs.wfx_soc_no','fpos.wfx_fpo_no','socs.garment_color','socs.ColorName','fppos.fppo_no','fpos.order_type','users.email','fpo_cut_plans.batch_no'      
      ) 
        ->join('fpo_cut_plans', 'fpo_cut_plans.fppo_id', '=', 'bundles.fppo_id')
        ->join('fppos', 'fppos.id', '=', 'fpo_cut_plans.fppo_id')
        ->join('fpos', 'fpos.id', '=', 'fpo_cut_plans.fpo_id')
        ->join('socs', 'socs.id', '=', 'fpos.soc_id')
        ->join('users','users.id','=','fpo_cut_plans.user_id')
        ->distinct('bundle_cut_update.bundle_id')
        ->whereIn('fpo_cut_plans.fppo_id',$request->fppo_list)
        ->orderby('bundles.id','ASC')
        ->get();
    
      $data = ['cut_plans' => $cut_plans, 'bundle'=>$bundle];
    
    $pdf = PDF::loadView('print.bundle_tag_report', $data);
    $pdf->setPaper('A4', 'portrait');
    return $pdf->stream('bundle_report' . date('Y_m_d_H_i_s') . '.pdf');
  }


}
