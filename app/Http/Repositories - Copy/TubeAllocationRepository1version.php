<?php

namespace App\Http\Repositories;

use App\Bundle;
use Illuminate\Http\Request;
use App\CutPlan;
use App\CombineOrder;
use App\CutUpdate;
use App\Exceptions\ConcurrencyCheckFailedException;
use App\LaySheet;
use App\Fpo;
use App\Fppo;
use App\SOC;
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

class TubeAllocationRepository
{
    public function getSOCQtyData($soc,$style_id){
        
        $balance_qty = [];
        $model = SOC::where ('wfx_soc_no',$soc)
        ->get();  
        $round_method =self::getRoundMethod();
        
        if(!is_null($model[0]->tolerance_json)){
            $qty_with_tolerance=[];
            foreach($model as $rec){
                $qty_json = $rec->qty_json;
                $qty_with_tolerance = $qty_json;
                foreach($qty_json as $k => $v){
                    $qty_with_tolerance[$k] += round((floatval($v)/100)*$qty_json[$k]);
                }
            }

            $model[0]->qty_json = $qty_with_tolerance;
        }
        

        foreach($model as $rec){
            $qty_json = $rec->qty_json;
            foreach($qty_json as $key =>$val){
                $balance_qty[$key]=(array_key_exists($key, $balance_qty) ? $balance_qty[$key]+$val: $val);
            }
        }
        
        /////// Get Allocated Fpo Quantity
        $fpo_cut_plan = FpoCutPlan::select('fpo_cut_plans.*')
        ->join('fpos','fpos.id','=','fpo_cut_plans.fpo_id')
        ->join('socs','socs.id','=','fpos.soc_id')
        ->where('socs.wfx_soc_no',$soc)
        ->get();

        //Get Fpos
        $fpos = SOC::where ('wfx_soc_no',$soc)
        ->select('fpos.*')
        ->join('fpos', 'fpos.soc_id', '=', 'socs.id')
        ->orderby('fpos.delivery_date','ASC')
        ->get();

        foreach($fpo_cut_plan as $rec){
            $qty = $rec->qty_json;

            foreach($qty as $key => $val){
                $balance_qty[$key]=(array_key_exists($key, $balance_qty) ? $balance_qty[$key]-$val : -$val);
            }
        }
        $model[0]->balance_qty_json = $balance_qty;
        
        $index = 0;
        foreach($fpos as $record){
            $qty_json = $record->qty_json;
            $balance_qty = $qty_json;
            // foreach($qty_json as $key => $val){
                foreach($fpo_cut_plan as $rec){
                    $qty = $rec->qty_json;
                    
                    if($rec->fpo_id == $record->id){
                        foreach($qty as $key => $val){
                            $balance_qty[$key]=(array_key_exists($key, $balance_qty) ? $balance_qty[$key]-$val : -$val);
                        }
                    }
                }
            //}
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

        //Get Fpos
        $fpos = SOC::where ('wfx_soc_no',$soc_no)
        ->select('fpos.*')
        ->join('fpos', 'fpos.soc_id', '=', 'socs.id')
        ->orderby('fpos.delivery_date','ASC')
        ->get();

        /////// Get Allocated Fpo Quantity
        $fpo_cut_plan = FpoCutPlan::select('fpo_cut_plans.*')
        ->join('fpos','fpos.id','=','fpo_cut_plans.fpo_id')
        ->join('socs','socs.id','=','fpos.soc_id')
        ->where('socs.wfx_soc_no',$soc_no)
        ->get();

        // find unallocated fpo size wise quantity
        $count = 0;
        foreach($fpos as $rec){
            $fpo_qty_json = $rec->qty_json;
            $balance_qty_json = $fpo_qty_json;

            foreach($fpo_cut_plan as $row){               
                if($rec->id == $row->fpo_id){
                   // print_r($rec->id."|".$row->fpo_id."||");
                    $fpo_allocated_json = $row->qty_json;
                    foreach($fpo_allocated_json as $key => $val){
                        $balance_qty_json[$key]=(array_key_exists($key, $balance_qty_json) ? $balance_qty_json[$key]-$val : -$val);
                    }
                    
                }
            }
            $fpos[$count]->balance_qty_json = $balance_qty_json;
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

    public function updateTubeAllocation($request){
        try{
            DB::beginTransaction();
           

            $soc_id = $request->soc_id;
            $batch_no = $request->batch_no;
            $fpo_data = $request->fpo_data;
            $sum_fpo_qty_json = $request->sum_fpo_qty_json;
            $user = $request->user_id;

            $soc= Soc::findOrFail($soc_id);

            $qty_json = $soc->qty_json;
            $tolerance = is_null($soc->tolerance)? 0 :$soc->tolerance;
            $qty_json_with_tolerance = $qty_json;
            

            //// Get Qty_json With Tolerance
            if($tolerance > 0){
                foreach($qty_json as $key => $val){
                    $qty_json_with_tolerance[$key] = $qty_json_with_tolerance[$key]+round($qty_json_with_tolerance[$key]*($tolerance/100));
                }
            }
                        
            /////// Get Allocated Fpo Quantity and Fin Unutilized Qty Json
            
            $fpo_cut_plan = FpoCutPlan::select('fpo_cut_plans.*')
            ->join('fpos','fpos.id','=','fpo_cut_plans.fpo_id')
            ->where('fpos.soc_id',$soc_id)
            ->get();

            foreach($fpo_cut_plan as $rec){
                $qty = $rec->qty_json;

                foreach($qty as $key => $val){
                    $qty_json_with_tolerance[$key]=(array_key_exists($key, $qty_json_with_tolerance) ? $qty_json_with_tolerance[$key]-$val : -$val);
                }
            }
            $qty_json = $qty_json_with_tolerance;

            ////////// Validate Unutilized Quantity and Request Quantity
            foreach($sum_fpo_qty_json  as $key => $val){
                if($val > $qty_json[$key]){
                    throw new \App\Exceptions\GeneralException('Quantity Exceeds For Size '.$key.'');
                }
            }

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
                throw new \App\Exceptions\GeneralException('Ratio Plan Not Found'.$key.'');
            }

            //// Create Fppo
            foreach($fpo_data as $rec){
                
                $db_array[] = [
                    'fpo_id' => $rec['fpo_id'],
                    'cut_plan_id' => $cut_no,
                    'qty_json' => $rec['qty_json'],
                    'qty_json_order' => array_keys($rec['qty_json']),
                    'line_no' => 1,
                    'consumption' => 0.00,
                    'batch_no'=>$batch_no,
                    'user_id'=>$user

                ];
            }


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
       
        $statement = DB::select("SHOW TABLE STATUS LIKE 'fppos'");
        $nextId = $statement[0]->Auto_increment;
        
   
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

            $fpo_cut_plan = FpoCutPlan::select('fpo_cut_plans.qty_json','fpo_cut_plans.batch_no','fpo_cut_plans.id','fppos.fppo_no','fpos.wfx_fpo_no')
            ->join('fpos','fpos.id','=','fpo_cut_plans.fpo_id')
            ->join('fppos','fppos.id','=','fpo_cut_plans.fppo_id')
            ->join('socs','socs.id','=','fpos.soc_id')
            ->where('socs.wfx_soc_no',$request->soc)
            ->orderby('fpo_cut_plans.id',"ASC")
            ->get();

            return response()->json(
                [
                  'status' => 'success',
                  'data'=>$fpo_cut_plan         
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
                    Fpo::destroy($model->fppo_id);
                    
                }else{
                    throw new \App\Exceptions\GeneralException("Tube Allocation Can't Remove After Bundle Creation.");
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

    
}