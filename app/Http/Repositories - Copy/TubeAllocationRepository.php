<?php

namespace App\Http\Repositories;

use App\Bundle;
use Illuminate\Http\Request;
use App\CutPlan;
use App\SocExcess;
use App\ExcessReject;
use App\CombineOrder;
use App\CutUpdate;
use App\Exceptions\ConcurrencyCheckFailedException;
use App\LaySheet;
use App\Fpo;
use App\Fppo;
use App\SOC;
use App\SocRejection;
use App\User;
use PDF;
// use App\HashStore;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use App\Http\Resources\CutPlanWithParentsResource;
use Illuminate\Validation\Rule;
use Exception;
use App\Exceptions\GeneralException;
use App\FpoCutPlan;
use App\Http\Validators\CutPlanCreateValidator;
use App\Http\Validators\CutPlanUpdateValidator;
use Illuminate\Support\Facades\Log;
use App\ExcessBundle;
use App\Http\Controllers\QrCodeController;

class TubeAllocationRepository
{

    private $qr;

    public function __construct()
    {
        $this->qr = new QrCodeController();
    }
    public function getSOCQtyData($soc,$style_id){
        
        $balance_qty = [];
        $model = SOC::where ('wfx_soc_no',$soc)->get();  
        $round_method =self::getRoundMethod();
        
        $max_qty = self::getMaxSocBalance($model[0]->id);
        $model[0]->qty_json = $max_qty['max_total'];
        $model[0]->balance_qty_json = $max_qty['max_balance'];


        //Get Fpos depending style
        $fpos = SOC::where ('style_id',$style_id)
        ->where ('fpos.combine_order_id','>',0)
        ->select('fpos.*','socs.tolerance_json')
        ->join('fpos', 'fpos.soc_id', '=', 'socs.id')
        ->orderby('fpos.delivery_date','ASC')
        ->get();

        /////// Get Allocated Fpo Quantity for style fpos
        $fpo_cut_plan = FpoCutPlan::select('fpo_cut_plans.*')
            ->join('fpos','fpos.id','=','fpo_cut_plans.fpo_id')
            ->join('socs','socs.id','=','fpos.soc_id')
            ->where('socs.style_id',$style_id)
            ->get();
        
        $index = 0;

        foreach($fpos as $record){
            $max_qty = self::getMaxFpoBalance($record->id);
            $fpos[$index]->qty_json = $max_qty['max_total'];

            /// Remove zero quantity from balance json
            $balance_qty = $max_qty['max_balance'];
            foreach($fpos[$index]->qty_json as $k => $v){
                if($v ===0 || $v === "0"){
                    $balance_qty[$k]="";                    
                }
            }
           
            $fpos[$index]->balance_qty_json = $balance_qty;
            $index++;
        }


        $data['soc'] = $model;
        $data['fpo'] = $fpos;
        return response()->json(['status' => 'success', 'data' => $data], 200);
    }

    public function getRoundMethod(){
        $method = DB::table('parameters')
            ->select('value')
            ->where('name','=','ta_tolerance')
            ->first();
        return $method->value;
    }

    public function devideSocToFpo($request){
        
        $qty_json = $request->qty_json;
        $soc_no = $request->soc;
        $style_id = $request->style_id;
        $round_method =self::getRoundMethod();

        $soc = SOC::select('*')->where ('wfx_soc_no',$soc_no)->first();
        $max_qty = self::getMaxSocBalance($soc->id);
        $max_qty_json = $max_qty['max_balance'];

        foreach($qty_json as $key => $val){
            if($val > $max_qty_json[$key]){
                throw new \App\Exceptions\GeneralException("SOC Qty Exceeds In Size ".$key."");
                //$qty_json[$key] = $max_qty_json[$key];
            }
        }

        ///////////////////////////////////////////////////////////////////////////////

        //Get Fpos depending style
        $fpos = SOC::where ('style_id',$style_id)
        ->select('fpos.*','socs.tolerance_json')
        ->join('fpos', 'fpos.soc_id', '=', 'socs.id')
        ->orderby('fpos.delivery_date','ASC')
        ->get();

        // find unallocated fpo size wise quantity
        $count = 0;
        foreach($fpos as $rec){
            $max_qty = self::getMaxFpoBalance($rec->id);

            $fpos[$count]->qty_json =$max_qty['max_total'];
            $fpos[$count]->balance_qty_json = $max_qty['max_balance'];
            $count++;
        }

        //devide badge quantity for fpo
        $count = 0;
        foreach($fpos as $rec){
            $fpo_qty_json = $rec->balance_qty_json;
            foreach($fpo_qty_json as $key => $val){
                if($val > 0){
                    $found = false;
                    foreach($qty_json as $k=>$v){
                        
                        if($k === $key){
                            if($v > 0){
                                if($v <= $val){
                                    $fpo_qty_json [$k] = $v;
                                    $qty_json[$key] = 0;
                                    $found = true;
                                                                        
                                }else if($v > $val){
                                    $qty_json[$key] -= $val;
                                    $found = true;
                                }
                            }else{
                                $fpo_qty_json [$key] = "";
                            }
                        }
                        
                        else{

                        }
                    }
                    if(!$found){
                        $fpo_qty_json [$key] = "";
                    }
                }
                else{
                    $fpo_qty_json [$key] = "";
                }
            }
            $fpos[$count]->balance_qty_json = $fpo_qty_json;
            $count++;
        }

        /////////// Check Unallocated request soc quantity
        foreach($qty_json as $key => $val){
            if($val > 0){
               // return response()->json(['status' => 'error', 'message' => "Quantity Exceeds for Size ".$key.""], 200);
            }
        }

        return response()->json(['status' => 'success', 'data' => $fpos], 200);
    }


    function getMaxSocBalance($soc_id){
        $round_method =self::getRoundMethod();
        $soc = SOC::select('*')->where ('id',$soc_id)->first();
        $max_qty_json = $soc->qty_json;

        ////////////////////////get max qty with tolerance ////////////////////

        if(!is_null($soc->tolerance_json)){
            foreach($soc->tolerance_json as $key => $val){

                // if($round_method == "round"){                              
                //     $max_qty_json[$key] += round((floatval($val)/100)*intval($max_qty_json[$key]));
                // }else if($round_method === "floor"){
                //     $max_qty_json[$key] += floor((floatval($val)/100)*intval($max_qty_json[$key]));
                // }else if($round_method === "ceil"){                            
                //     $max_qty_json[$key] += ceil((floatval($val)/100)*intval($max_qty_json[$key]));
                // }
                
                ///  for Given tolerance

                if($round_method == "round"){                              
                    $max_qty_json[$key] = round((floatval($max_qty_json[$key]))*(100/(100-intval($val))));
                }else if($round_method === "floor"){
                    $max_qty_json[$key] = floor((floatval($max_qty_json[$key]))*(100/(100-intval($val))));
                }else if($round_method === "ceil"){                            
                    $max_qty_json[$key] = ceil((floatval($max_qty_json[$key]))*(100/(100-intval($val))));
                }

                //////////Hard code Tolerance

            }
        }else{
            foreach($max_qty_json as $key =>$val){
                if($round_method == "round"){                              
                    $max_qty_json[$key] = round((floatval($val))*(100/92));
                }else if($round_method === "floor"){
                    $max_qty_json[$key] = floor((floatval($val))*(100/92));
                }else if($round_method === "ceil"){                            
                    $max_qty_json[$key] += ceil((floatval($val))*(100/92));
                }
            }
        }

        //// Add reject quantity
        // $soc_reject = DB::table('qc_rejects')
        // ->select('qc_rejects.quantity','bundles.size')
        //     ->join('bundle_tickets','bundle_tickets.id','=','qc_rejects.bundle_ticket_id')
        //     ->join('bundles','bundles.id','=','bundle_tickets.bundle_id')
        //     ->join('job_card_bundles','job_card_bundles.bundle_id','=','bundles.id')
        //     ->join('job_cards','job_cards.id','=','job_card_bundles.job_card_id')
        //     ->join('fpos','fpos.id','=','job_cards.fpo_id')
        //     ->where('fpos.soc_id','=',$soc_id)
        //     ->get();

        // foreach($soc_reject as $records){
        //     $max_qty_json[$records->size] += intval($records->quantity);
        // }


        $max_qty['max_total'] = $max_qty_json;

        /////// Get Allocated Fpo Quantity depending soc
        $fpo_cut_plan = FpoCutPlan::select('fpo_cut_plans.*')
        ->where('fpo_cut_plans.allocated_soc_id',$soc->id)
        ->get();

        foreach($fpo_cut_plan as $rec){
            $qty = $rec->qty_json;
            foreach($qty as $key => $val){
                $max_qty_json[$key] -= $val;
            }
        }

        ////////////////////// decrease rejection
        $nit_reject = SocRejection::where('soc_id','=',$soc_id)->get();
        foreach($nit_reject as $records){
            $qty = $records->qty_json;
            foreach($qty as $key => $val){
                $max_qty_json[$key] -= $val;
            }
           
       }

        ////////////////////// decrease Excess
        $excess = SocExcess::where('soc_id','=',$soc_id)->get();
        foreach($excess as $records){
            $qty = $records->qty_json;
            foreach($qty as $key => $val){
                $max_qty_json[$key] -= $val;
            }
            
        }
        $max_qty['max_balance'] = $max_qty_json;
        return $max_qty;
    }

    function getMaxFpoBalance($fpo_id){
        
        $round_method =self::getRoundMethod();
        $rec = Fpo::find($fpo_id);
        $fpo_qty_json = $rec->qty_json;
        $balance_qty_json = $fpo_qty_json;

        // get original quantity with tolerance
        // if(!is_null($rec->soc->tolerance_json)){
        //     foreach($rec->soc->tolerance_json as $k => $v){
        //         if($round_method === "round"){
        //             $balance_qty_json[$k] += (round((floatval($v)/100)*$fpo_qty_json[$k])) > 0 ? round((floatval($v)/100)*$fpo_qty_json[$k]) : 0;
        //         }else if($round_method === "floor"){
        //             $balance_qty_json[$k] += (floor((floatval($v)/100)*$fpo_qty_json[$k])) > 0 ? floor((floatval($v)/100)*$fpo_qty_json[$k]) : 0;
        //         }else if($round_method === "ceil"){
        //             $balance_qty_json[$k] += (ceil((floatval($v)/100)*$fpo_qty_json[$k])) > 0 ? ceil((floatval($v)/100)*$fpo_qty_json[$k]) : 0;
        //         }
        //     }
        // }

        //// get fpo reject qty 
        $fpo_reject = DB::table('qc_rejects')
        ->select('qc_rejects.quantity','bundles.size')
            ->join('bundle_tickets','bundle_tickets.id','=','qc_rejects.bundle_ticket_id')
            ->join('bundles','bundles.id','=','bundle_tickets.bundle_id')
            ->join('fpo_cut_plans','fpo_cut_plans.fppo_id','=','bundles.fppo_id')
            //->join('job_card_bundles','job_card_bundles.bundle_id','=','bundles.id')
            //->join('job_cards','job_cards.id','=','job_card_bundles.job_card_id')
            ->where('fpo_cut_plans.fpo_id','=',$rec->id)
            ->get();

        foreach($fpo_reject as $records){
            $balance_qty_json[$records->size] += intval($records->quantity);
        }
        
        $fpos['max_total'] = $balance_qty_json;

    //////// Get and remove fpo allocated quantity  ///
       $fpo_cut_plan = FpoCutPlan::select('fpo_cut_plans.*')
       ->where('fpo_cut_plans.fpo_id',$fpo_id)
       ->get();

        foreach($fpo_cut_plan as $row){               
            if($rec->id == $row->fpo_id){
               
                $fpo_allocated_json = $row->qty_json;
                foreach($fpo_allocated_json as $key => $val){
                    $balance_qty_json[$key]=(array_key_exists($key, $balance_qty_json) ? $balance_qty_json[$key]-$val : -$val);
                }                    
            }
        }

        $fpos['max_balance'] = $balance_qty_json;
        return $fpos;
    }


    public function updateTubeAllocation($request){
        try{
            DB::beginTransaction();
           
            $round_method =self::getRoundMethod();
            $soc_id = $request->soc_id;
            $batch_no = $request->batch_no;
            $fpo_data = $request->fpo_data;
            $sum_fpo_qty_json = $request->sum_fpo_qty_json;
            $user = $request->user_id;
            $soc_qty_json = $request->soc_qty_json;

            $reject_data = $request->rejection_data;
            $reject_reason = $request->reject_reason;

            $soc= Soc::findOrFail($soc_id);
            $max_qty = self::getMaxSocBalance($soc_id);
            $qty_json = $max_qty['max_balance'];
            

        ////////////////////////validate allocated quantity ////////////////////

            foreach($soc_qty_json as $key => $val){
                $reject_qty = 0;
                if(isset($reject_data[$key]) && $reject_data[$key] > 0){
                    $reject_qty = $reject_data[$key];
                }
                if(($val+$reject_qty) > $qty_json[$key]){
                    throw new \App\Exceptions\GeneralException('Soc Quantity Exceeds For Size '.$key.'');
                }
            }

        //////////////////////////////////////////////////////////////////////////////////
        //                      validate Fpo Quantity                                   //
        /////////////////////////////////////////////////////////////////////////////////    
            foreach($fpo_data as $rec){
                
                $fpo= Fpo::findOrFail($rec['fpo_id']);
                $max_qty = self::getMaxFpoBalance($fpo->id);
                $fpo_qty_json = $max_qty['max_balance'];
                
                foreach($rec['qty_json'] as $key => $val){                   
                    if(!(intval($fpo_qty_json[$key]) >= intval($rec['qty_json'][$key]))){                        
                        throw new \App\Exceptions\GeneralException('Quantity Exceeds For Size '.$key.' in DOC '.$fpo->wfx_fpo_no.'');
                    }
                    $soc_qty_json[$key] = intval($soc_qty_json[$key])-intval($val);                    
                }                
            }    
           
            /////////////////////////////////////////////////////////////////////////////////////////////
            //// Get Auto Created Cut No
            $cut = CutPlan::select('cut_plans.id')
            ->join('fpos','fpos.combine_order_id','=','cut_plans.combine_order_id')
            ->where('fpos.soc_id',$soc_id)
            ->first();

            $cut_no = "";
            if(!is_null($cut)){
                $cut_no = $cut->id;
            }
            if($cut_no === ""){
                throw new \App\Exceptions\GeneralException('Ratio Plan Not Found');
            }

            ////////////////////////////////////// Create Fppo  //////////////////////////////
            //////////////////////////////////////////////////////////////////////////////////
            foreach($fpo_data as $rec){
                
                $db_array[] = [
                    'fpo_id' => $rec['fpo_id'],
                    'cut_plan_id' => $cut_no,
                    'qty_json' => $rec['qty_json'],
                    'qty_json_order' => array_keys($rec['qty_json']),
                    'line_no' => 1,
                    'consumption' => 0.00,
                    'batch_no'=>$batch_no,
                    'user_id'=>$user,
                    'allocated_soc_id'=>$soc_id

                ];
            }

            if(isset($db_array)){
                foreach ($db_array as $fpo_cut_plan) {
                    $fpo_cut_plan = FpoCutPlanRepository::createRec($fpo_cut_plan);
                    $fpo_cut_plan_id = $fpo_cut_plan->id;
                    $fppo = self::createFppo($fpo_cut_plan_id);

                    $cut_update_array = array(
                        'qty_json'=>$fpo_cut_plan->qty_json,
                        'qty_json_order'=>$fpo_cut_plan->qty_json_order,
                        'fppo_id'=>$fppo->id,
                        'cut_plan_id'=>$fpo_cut_plan->cut_plan_id,
                        'daily_shift_id'=>null
                    );
                    
                    $cut_update = CutUpdateRepository::createRec($cut_update_array);
                }
            }

            /////////////////////// Create soc Excess 
            $qty_json = [];
            foreach($soc_qty_json as $key => $val){
                if(intval($val) > 0){
                    $qty_json[$key] = $val;
                }
            }

            if(array_sum($qty_json) > 0){
                SocExcess::create(['soc_id'=>$soc_id, 'batch_no'=>$batch_no, 'qty_json'=>$qty_json]);

            }

            if(array_sum($reject_data) > 0){
                SocRejection::create(['soc_id'=>$soc_id, 'batch_no'=>$batch_no, 'qty_json'=>$reject_data,'reason'=>$reject_reason]);
            }
            
            DB::commit();
            return response()->json(
                [
                  'status' => 'success',
                  'data'=>$soc         
                ],
                200
            );

        }catch(Exception $e){
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

    public function createFppo($fpo_cut_plan_id)
    {
   
         $fpoCutPlan = FpoCutPlan::findOrFail($fpo_cut_plan_id);
         CombineOrder::where('id', $fpoCutPlan->cut_plan->combine_order->id)->update(['status' => 'Closed']);
        
        $fpo_cut_plans = FpoCutPlan::where('id', $fpo_cut_plan_id)->get();
   
        //sorting json
        foreach ($fpo_cut_plans as $key => $result) {
          if ((isset($result->qty_json)) && (isset($result->qty_json_order))) {
            $result->qty_json = Utilities::sortQtyJson($result->qty_json_order, $result->qty_json);
          }
        }
        
        $qty_json = [];
        foreach ($fpo_cut_plans as $key => $rec) {
          foreach ($rec['qty_json'] as $key => $value) {
            $qty_json[$key] = $value + (array_key_exists($key, $qty_json) ? $qty_json[$key] : 0);
          }
        }
       
        // $statement = DB::select("SHOW TABLE STATUS LIKE 'fppos'");
        // $nextId = $statement[0]->Auto_increment;

        $last_fppo = DB::table('fppos')
        ->select('id')
        ->orderby('id','DESC')
        ->first();
        $nextId = $last_fppo->id;
        $nextId = intval($nextId)+1;
   
        $fppo = FppoRepository::createRec([
          'fppo_no' => 'FPPO' . $nextId,
          'qty_json' => $qty_json,
          'qty_json_order' => array_keys($qty_json),
          'utilized' => true
        ]);
        
        FpoCutPlan::where('id', $fpo_cut_plan_id)->update(['fppo_id' => $fppo->id]);
        return $fppo;
    }

    public function getBatchBySoc($request){
        try{

            $fpo_cut_plan = FpoCutPlan::select('fpo_cut_plans.qty_json','fpo_cut_plans.batch_no','fpo_cut_plans.id','fppos.fppo_no','fpos.wfx_fpo_no','users.name','fpo_cut_plans.created_at')
             ->join('fpos','fpos.id','=','fpo_cut_plans.fpo_id')
             ->join('fppos','fppos.id','=','fpo_cut_plans.fppo_id')
            ->join('socs','socs.id','=','fpo_cut_plans.allocated_soc_id')
            ->join('users','users.id','=','fpo_cut_plans.user_id')
            ->where('socs.wfx_soc_no',$request->soc)
            ->orderby('fpo_cut_plans.id',"ASC")
            ->get();

            $soc_excess = SocExcess::select('soc_excesses.*')
            ->join('socs','socs.id','=','soc_excesses.soc_id')
            ->where('socs.wfx_soc_no',$request->soc)
            ->get();

            $soc_reject = SocRejection::select('soc_rejections.*')
            ->join('socs','socs.id','=','soc_rejections.soc_id')
            ->where('socs.wfx_soc_no',$request->soc)
            ->get();

            $data['fpo_cut_plan'] = $fpo_cut_plan;
            $data['soc_excess']=$soc_excess;
            $data['soc_reject']=$soc_reject;

            
			////////////////////////////////////////////////////////////////////
            //////////////////// GET FROM DOKU DATABASE   //////////////////////
            ////////////////////////////////////////////////////////////////////


            $servername = "SISRN11\DOKU_MSSQLSERVER";
            $username = "maheshs";
            $password = "12345";
            $dbname = "dokuplan_inqube";

            $connectionInfo = array('Database'=>'dokuplan_inqube','UID'=>'maheshs','PWD'=>'12345');
            $connection = sqlsrv_connect($servername, $connectionInfo);
			$doku_batch=[] ;

            if ($connection) {
                
                
                $sql = "SELECT dbo.KOrderPos.KundenOrderNr AS SOC, dbo.StrickBallen.Bemerkung01 AS BatchNo, dbo.PersAg.StufenNr, CONVERT(Date, dbo.PersAg.Datum) AS ScanDate
                FROM dbo.KOrderPos INNER JOIN
                 dbo.POrder ON dbo.KOrderPos.PosNr = dbo.POrder.PosNr AND dbo.KOrderPos.OrderId = dbo.POrder.OrderNr INNER JOIN
                 dbo.StrickBallen ON dbo.POrder.OrderId = dbo.StrickBallen.OrderId INNER JOIN
                 dbo.PersAg ON dbo.StrickBallen.BallenId = dbo.PersAg.BId
                WHERE dbo.KOrderPos.OrderId >= 3059 AND dbo.KOrderPos.KundenOrderNr IN ('".$request->soc."')
                GROUP BY dbo.KOrderPos.KundenOrderNr, dbo.StrickBallen.Bemerkung01, dbo.PersAg.StufenNr, CONVERT(Date, dbo.PersAg.Datum)
                HAVING (dbo.PersAg.StufenNr = '24') AND dbo.StrickBallen.Bemerkung01 <> ''";        
                $result = sqlsrv_query($connection,$sql);
				//echo $sql;
                if ($result == FALSE) {
                    //echo "RFail";
                }else{
                    $index = 0;
                    
                     while($obj = sqlsrv_fetch_array($result,SQLSRV_FETCH_ASSOC)){
						$doku_batch[$index] = $obj['BatchNo'];
						$index++;
                        
                     }
                    
                
                    //return $result;
                }
            }
            
            else{
                
               // return null;
                
                
            }
			
			$data['doku_batch']=$doku_batch;
        ///////////////////////////////////////////////////////////////////
        ///////////////////////////////////////////////////////////////////

            return response()->json(
                [
                  'status' => 'success',
                  'data'=>$data         
                ],
                200
            );

        }catch(Exception $e){
            return response()->json(
                [
                  'status' => 'error',
                  'message'=>$e->getMessage()         
                ],
                200
            );
        }
    }

    public function deleteTubeAllocation($request){
        try{
            DB::beginTransaction();
            $deleteRows = $request->delete_fpo_cut_plans;
            $deleteSocExcess = $request->delete_soc_excess;
            $deleteSocReject = $request->delete_soc_rejection;
            $delete_excess_bundle = $request->delete_excess_bundle;

            foreach($deleteRows as $key => $val){
                $model = FpoCutPlan::find($val);

                $fpoCutPlan=DB::table('fpo_cut_plans')->select('fpo_cut_plans.id')
                ->where('fpo_cut_plans.id','=',$val)
                ->join('cut_updates','cut_updates.fppo_id','=','fpo_cut_plans.fppo_id')
                ->join('bundle_cut_update','bundle_cut_update.cut_update_id','=','cut_updates.id')                
                ->get();

                if(is_null($fpoCutPlan) || sizeof($fpoCutPlan) === 0){
                    
                    FpoCutPlan::destroy($model->id);
                    CutUpdate::where('fppo_id','=',$model->fppo_id)->delete();
                    Fppo::destroy($model->fppo_id);
                    
                }else{
                    throw new \App\Exceptions\GeneralException("Tube Allocation Can't Remove After Bundle Creation.");
                }                
            }

            foreach($deleteSocExcess as $key => $val){
                $model = SocExcess::find($val);
                $excess_bundle = ExcessBundle::where('soc_excess_id','=',$val)->first();
                
                if(is_null($excess_bundle)){
                    SocExcess::destroy($model->id);
                }else{
                    throw new \App\Exceptions\GeneralException("Excess Bundle Already Created ");
                }
                
            }

            foreach($deleteSocReject as $key => $val){
                $model = SocRejection::find($val);
                SocRejection::destroy($model->id);
            }

            foreach($delete_excess_bundle as $key => $val){
                
                $model = ExcessBundle::find($val);

                if(is_null($model->location_id)){
                    ExcessBundle::destroy($model->id);
                }else{
                    throw new \App\Exceptions\GeneralException("Bundle Already GRN ".$model->id."");
                }
            }
            DB::commit();
            return response()->json(
                [
                'status' => 'success',
                'data'=>"success"         
                ],
                200
            );
        }catch(Exception $e){
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

    public function getUserList(){
        try{

            $users = User::get();

            return response()->json(
                [
                'status' => 'success',
                'data'=>$users         
                ],
                200
            );

        }catch(Exception $e){
            return response()->json(
                [
                    'status' => 'error',
                    'message' => $e->getMessage()
                ],
                400
            );
        }
    }

    public function getBatchByBundleType($request){
        try{
            $soc_no = $request->soc_no;
            $type = $request->type;
            $batch = [];

            if($type == 1){
                $batch = SocExcess::select('soc_excesses.*')
                ->join('socs','socs.id','=','soc_excesses.soc_id')
                ->where('socs.wfx_soc_no',$soc_no)
                ->get();
            }else if($type == 2){
                $batch = SocRejection::select('soc_rejections.*')
                ->join('socs','socs.id','=','soc_rejections.soc_id')
                ->where('socs.wfx_soc_no','=',$soc_no)
                ->get();
            }
            

            return response()->json(
                [
                'status' => 'success',
                'data'=>$batch         
                ],
                200
            );
        }catch(Exception $e){
            return response()->json(
                [
                    'status' => 'error',
                    'message' => $e->getMessage()
                ],
                400
            );
        }

    }

    public function getBatchAvailableQty($request){
        try{
            $batch_excess_id = $request->batch_excess_id;
            $excess = SocExcess::select('soc_excesses.*','socs.wfx_soc_no')
            ->join('socs','socs.id','=','soc_excesses.soc_id')
            ->where('soc_excesses.id',$batch_excess_id)
            ->get();
                      
            $avl = self :: getBatchUnutilizeQty($batch_excess_id);
            $excess[0]->qty_json = $avl;
            return response()->json(
                [
                'status' => 'success',
                'data'=>$excess         
                ],
                200
            );
        }catch(Exception $e){
            return response()->json(
                [
                    'status' => 'error',
                    'message' => $e->getMessage()
                ],
                400
            );
        }
    }

    public function getBatchUnutilizeQty($batch_excess_id){
        $data = [];
        $excess = SocExcess::select('soc_excesses.*','socs.wfx_soc_no')
        ->join('socs','socs.id','=','soc_excesses.soc_id')
        ->where('soc_excesses.id',$batch_excess_id)
        ->get();
        $data = $excess[0]->qty_json;

        ///  Get Allocated Bundle

        $excess_bundle = ExcessBundle::where('soc_excess_id','=',$batch_excess_id)->get();
        
        foreach($excess_bundle as $rec){
            $data[$rec->size] -= intval($rec->qty);
        }
        return $data;
    }

    public function createExcessBundle($request){
        try{
            DB::beginTransaction();

            $batch_excess_id = $request->batch_excess_id;
            $bundle_size = $request->bundle_size;

            $availableQty = self :: getBatchUnutilizeQty($batch_excess_id);
            foreach($availableQty as $key =>$val){
                if(intval($val) > 0){
                    $noOfIterations = intval($val / $bundle_size);
                    $remainder = fmod($val, $bundle_size);

                    for ($i = 1; $i <= $noOfIterations; $i++) {
                        $excess_bundle = ExcessBundle::create([
                            "size"=>$key,
                            "qty"=>$bundle_size,
                            "soc_excess_id"=>$batch_excess_id
                             
                        ]);
                        $id = $excess_bundle->id."E";
                        $excess_bundle->update(["bundle_id"=>$id]);
                    }
                    if(intval($remainder) > 0){
                        $excess_bundle = ExcessBundle::create([
                            "size"=>$key,
                            "qty"=>$remainder,
                            "soc_excess_id"=>$batch_excess_id
                            
                        ]);
                        $id = $excess_bundle->id."E";
                        $excess_bundle->update(["bundle_id"=>$id]);
                    }
                    
                }
            }

            DB::commit();
            return response()->json(
                [
                'status' => 'success',
                'data'=>"success"         
                ],
                200
            );
        }catch(Exception $e){
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

    public function getExcessBundleReport($request){

    
          $excess_bundle = ExcessBundle::select(
            'excess_bundles.*','socs.wfx_soc_no','socs.garment_color','socs.ColorName' ,'styles.style_code' ,'soc_excesses.batch_no'    
          ) 
          ->join('soc_excesses','soc_excesses.id','=','excess_bundles.soc_excess_id')
            ->join('socs', 'socs.id', '=', 'soc_excesses.soc_id')
            ->join('styles', 'styles.id', '=', 'socs.style_id')
            ->where('excess_bundles.soc_excess_id', $request->soc_excess_id)
            ->orderby('excess_bundles.id','ASC')
            ->get();
        
          $data = ['bundle'=>$excess_bundle];
        
        $pdf = PDF::loadView('print.excess_bundle_tag_report', $data);
        $pdf->setPaper('A4', 'portrait');
        return $pdf->stream('bundle_report' . date('Y_m_d_H_i_s') . '.pdf');
    }

    public function getExcessBundleByBatch($request){
        try{
            DB::beginTransaction();

            $batch_excess_id = $request->batch_excess_id;
            $data = ExcessBundle::where('soc_excess_id','=',$batch_excess_id)->get();

            DB::commit();
            return response()->json(
                [
                'status' => 'success',
                'data'=>$data         
                ],
                200
            );
        }catch(Exception $e){
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


    public function getYarnStickers($request)
    {
        $grn_no = $request->grnNo;
        $site = $request->site;

        $client = new \GuzzleHttp\Client();
        $res = $client->get('https://inqube.worldfashionexchange.com/wfxwebapi/api/CtrlWFXRollLocation/GETData', ['query' => ['GRNNumber' => ''.$grn_no .'', 'Site' => ''.$site.'']]);
        $response = ($res->getBody()->getContents());
        $decoded = json_decode($response, true);
        //return $decoded;
        $sending =[];
        foreach ($decoded['ResponseData'] as $rec){
            if($rec['Attribute16'] != ""){ 
                for($i = 0; $i < ceil($rec['Attribute16']); $i++){ 
                   array_push($sending, $rec);        
                }    
            }else{
                array_push($sending, $rec);    
            }
        }
        $pdf = $this->qr->yarnSticker($sending);
        
       // $pdf = $this->qr->yarnSticker($decoded['ResponseData']);
    
        return $pdf;
    }

    public function getRejectionBatch($request){
        try{
            DB::beginTransaction();

            $data = [];
            
            $soc_no = $request->soc_no;
            $excessBatch = ExcessBundle::select('soc_excesses.batch_no')
            ->join('soc_excesses', 'soc_excesses.id', '=', 'excess_bundles.soc_excess_id')
            ->join('socs', 'socs.id', '=', 'soc_excesses.soc_id')
            ->where('socs.wfx_soc_no','=',$soc_no)
            ->distinct('soc_excesses.batch_no')
            ->get();

            foreach($excessBatch as $rec){
                
                array_push($data,$rec->batch_no);
            }

            $rejectionBatch = SocRejection::select('soc_rejections.batch_no')
            ->join('socs', 'socs.id', '=', 'soc_rejections.soc_id')
            ->where('socs.wfx_soc_no','=',$soc_no)
            ->whereNotIn('soc_rejections.batch_no',$data)
            ->distinct()
            ->get();

            foreach($rejectionBatch as $rec){
                array_push($data,$rec->batch_no);
            }



            DB::commit();
            return response()->json(
                [
                'status' => 'success',
                'data'=>$data         
                ],
                200
            );
        }catch(Exception $e){
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

    public function getRejectionByBatch($request){
        try{
            DB::beginTransaction();

            $rejectionBatch = SocRejection::select('soc_rejections.id')
            ->join('socs', 'socs.id', '=', 'soc_rejections.soc_id')
            ->where('socs.wfx_soc_no','=',$request->soc_no)
            ->where('soc_rejections.batch_no',$request->batch)
            ->distinct()
            ->get();

            $responsedata['batch'] = $rejectionBatch;

            $excessBundle = ExcessBundle::select('excess_bundles.*')
            ->join('soc_excesses', 'soc_excesses.id', '=', 'excess_bundles.soc_excess_id')
            ->join('socs', 'socs.id', '=', 'soc_excesses.soc_id')
            ->where('socs.wfx_soc_no','=',$request->soc_no)
            ->where('soc_excesses.batch_no',$request->batch)
            ->get();

            $responsedata['excess'] = $excessBundle;

            DB::commit();
            return response()->json(
                [
                'status' => 'success',
                'data'=>$responsedata         
                ],
                200
            );
        }catch(Exception $e){
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

    public function getRejectionById($request){
        try{
            DB::beginTransaction();
            $data =[];
            $rejectionBatch = SocRejection::select('*')
            ->where('id','=',$request->id)
            ->get();

            $data['total'] =  $rejectionBatch[0]['qty_json'];
            $data['balance'] =  $rejectionBatch[0]['qty_json'];

            DB::commit();
            return response()->json(
                [
                'status' => 'success',
                'data'=>$data         
                ],
                200
            );
        }catch(Exception $e){
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

    public function getExcessBundleDetails($request){
        try{
                        
            $excess_bundle = ExcessBundle::select('size','qty')
            ->where('id','=',$request->id)
            ->first();
           
            return response()->json(
                [
                'status' => 'success',
                'data'=>$excess_bundle         
                ],
                200
            );
        }catch(Exception $e){
           
            return response()->json(
            [
                'status' => 'error',
                'message' => $e->getMessage()
            ],
            400
            );
        }
    }

    public function getSearchByDOC($request){
        try{
                        
            $fpos = Fpo::select('fpos.id','fpos.wfx_fpo_no')
            ->join('socs','socs.id','=','fpos.soc_id')
           // ->where('socs.buyer_id','=',$request->id)
            ->where('socs.customer_style_ref','=',$request->customer_style_ref)
            ->limit(20)
            ->get();
           
            return response()->json(
                [
                'status' => 'success',
                'data'=>$fpos         
                ],
                200
            );
        }catch(Exception $e){
           
            return response()->json(
            [
                'status' => 'error',
                'message' => $e->getMessage()
            ],
            400
            );
        }
    }

    public function getSearchResultsByDOC($request){
        try{
                        
            $fpos = Fpo::select('fpos.id','fpos.wfx_fpo_no')
            ->join('socs','socs.id','=','fpos.soc_id')
           // ->where('socs.buyer_id','=',$request->id)
            ->where('socs.customer_style_ref','=',$request->customer_style_ref)
            ->where('fpos.wfx_fpo_no','=',$request->wfx_fpo_no)
            ->limit(20)
            ->get();
           
            return response()->json(
                [
                'status' => 'success',
                'data'=>$fpos         
                ],
                200
            );
        }catch(Exception $e){
           
            return response()->json(
            [
                'status' => 'error',
                'message' => $e->getMessage()
            ],
            400
            );
        } 
    }

    public function saveExcessAllocation($request){
        try{
            DB::beginTransaction();

            $fpo_id = $request->fpo_id;
            $excess_bundle_id = $request->excess_bundle_id;
            $size = $request->size;
            $batch_no = $request->batch_no;
            $soc_no = $request->soc_no;
            $user = $request->user;

            $allo_qty = (($request->allo_qty) > 0) ? $request->allo_qty : 0;
            $new_bundle_qty = (($request->new_bundle_qty) > 0) ? $request->new_bundle_qty : 0;
            $reject_qty = (($request->reject_qty ) > 0) ? $request->reject_qty : 0;
            $bundle_qty = (($request->bundle_qty ) > 0) ? $request->bundle_qty : 0;

            $process_qty = intval($new_bundle_qty)+intval($reject_qty)+intval($allo_qty);

            if(intval($bundle_qty) !== $process_qty){
                throw new \App\Exceptions\GeneralException("Quantity Allocation Error");
            }
            
            $max_qty = self::getMaxFpoBalance($fpo_id);
            $balance_json = $max_qty['max_balance'];
            
            if(!isset($balance_json[$size]) || $balance_json[$size] < $allo_qty){
                throw new \App\Exceptions\GeneralException("Not Open DOC Quantity");
            }

            ///////////  Update Excess Bundle Qty  /////////////////////
            $exceeBundle=ExcessBundle::where('id','=',$excess_bundle_id)->update(['qty'=>$new_bundle_qty]);
                       
            /////////////////////////////////////////////////////////////////////////////////////////////
            //// Get Auto Created Cut No
            $cut = CutPlan::select('cut_plans.id')
            ->join('fpos','fpos.combine_order_id','=','cut_plans.combine_order_id')
            ->where('fpos.id',$fpo_id)
            ->first();

            $cut_no = "";
            if(!is_null($cut)){
                $cut_no = $cut->id;
            }
            if($cut_no === ""){
                throw new \App\Exceptions\GeneralException('Ratio Plan Not Found');
            }

            $soc = Soc::where('wfx_soc_no',$soc_no)->first();
            $qty_array = [$size=>$allo_qty];
            $qty_json = json_decode(json_encode($qty_array,true));
            $db_array[] = [
                'fpo_id' => $fpo_id,
                'cut_plan_id' => $cut_no,
                'qty_json' => $qty_json,
                'qty_json_order' => array_keys($qty_array),
                'line_no' => 1,
                'consumption' => 0.00,
                'batch_no'=>$batch_no,
                'user_id'=>$user,
                'allocated_soc_id'=>$soc->id

            ];
            
            //////////////  Update Fpo Cut Plan
            if(isset($db_array)){
                foreach ($db_array as $fpo_cut_plan) {
                    $fpo_cut_plan = FpoCutPlanRepository::createRec($fpo_cut_plan);
                    $fpo_cut_plan_id = $fpo_cut_plan->id;
                    $fppo = self::createFppo($fpo_cut_plan_id);

                    $cut_update_array = array(
                        'qty_json'=>$fpo_cut_plan->qty_json,
                        'qty_json_order'=>$fpo_cut_plan->qty_json_order,
                        'fppo_id'=>$fppo->id,
                        'cut_plan_id'=>$fpo_cut_plan->cut_plan_id,
                        'daily_shift_id'=>null
                    );
                    
                   $cut_update = CutUpdateRepository::createRec($cut_update_array);
                }
            }


            ///////////////////////////////   UPDATE REJECT DATA  //////////////////////
            $excessBundle = ExcessBundle::find($excess_bundle_id);

            $excessReject = ExcessReject::create([
                "size"=>$size,
                "qty"=>$reject_qty,
                "excess_bundle_id"=>$excessBundle->id,
                "soc_excess_id"=>$excessBundle->soc_excess_id
                
            ]);


            DB::commit();
            return response()->json(
                [
                'status' => 'success'       
                ],
                200
            );
        }catch(Exception $e){
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

    public function getActiveBatch(){
        // $fppo = FpoCutPlan::select('styles.style_code','socs.wfx_soc_no','fpos.wfx_fpo_no','fpo_cut_plans.batch_no','users.name','fppos.id','fppos.fppo_no', DB::raw('SUM(bundles.quantity) as totalReject'))
        //         ->join('fpos','fpos.id','=','fpo_cut_plans.fpo_id')
        //         ->join('socs','socs.id','=','fpo_cut_plans.allocated_soc_id')
        //         ->join('styles','styles.id','=','socs.style_id')
        //         ->join('fppos','fppos.id','=','fpo_cut_plans.fppo_id')
        //         ->join('users','users.id','=','fpo_cut_plans.user_id')
        //         ->join('bundles','bundles.fppo_id','=','fpo_cut_plans.fppo_id')
        //         ->groupByRaw('fppos.id')
        //         ->where('socs.WfxStatus',null)               
        //         ->get();
        
        $fppo = DB::select('select `styles`.`style_code`, `socs`.`wfx_soc_no`, `fpos`.`wfx_fpo_no`, `fpo_cut_plans`.`batch_no`, `users`.`name`, `fppos`.`id`, `fppos`.`fppo_no`,SUM(bundles.quantity) as bundleQty, count(bundles.quantity) as bundleCount from `fpo_cut_plans` 
        inner join `fpos` on `fpos`.`id` = `fpo_cut_plans`.`fpo_id` 
        inner join `socs` on `socs`.`id` = `fpo_cut_plans`.`allocated_soc_id` 
        inner join `styles` on `styles`.`id` = `socs`.`style_id` 
        inner join `fppos` on `fppos`.`id` = `fpo_cut_plans`.`fppo_id` 
        inner join `users` on `users`.`id` = `fpo_cut_plans`.`user_id` 
        inner join `bundles` on `bundles`.`fppo_id` = `fpo_cut_plans`.`fppo_id` 
        where `socs`.`WfxStatus` is null 
        group by fppos.id, socs.wfx_soc_no,styles.style_code,fpos.wfx_fpo_no,fpo_cut_plans.batch_no,users.name,fppos.id,fppos.fppo_no');

        return response()->json(
            [
            'status' => 'success',
            'data'=>$fppo         
            ],
            200
        );
    }
    
}