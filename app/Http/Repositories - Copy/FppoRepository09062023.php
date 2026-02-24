<?php

namespace App\Http\Repositories;

use App\Bundle;
use App\BundleTicket;
use App\CutUpdate;
use App\Exceptions\ConcurrencyCheckFailedException;
use App\Fpo;
use App\FpoCutPlan;
use App\Fppo;
// use App\HashStore;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use App\Http\Resources\FppoWithParentsResource;
use Illuminate\Validation\Rule;
use Exception;

use App\Http\Validators\FppoCreateValidator;
use App\Http\Validators\FppoUpdateValidator;
use Illuminate\Support\Facades\Log;
use PharIo\Manifest\BundlesElement;

class FppoRepository
{
  public function show(Fppo $oc)
  {
    return response()->json(
      [
        'status' => 'success',
        'data' => new FppoWithParentsResource($oc),
      ],
      200
    );
  }

  public static function createRec(array $rec)
  {
    $rec['qty_json'] = json_encode($rec['qty_json']);
    $validator = Validator::make(
      $rec,
      FppoCreateValidator::getCreateRules()
    );
    if ($validator->fails()) {
      throw new Exception($validator->errors());
    }
    // $rec['qty_json'] = json_decode($rec['qty_json']);
    $rec['qty_json'] = Utilities::json_numerize(json_decode($rec['qty_json'], true), "int");
    try {
      $rec['status'] = Fppo::getInitialStatus();
      $model = Fppo::create($rec);
    } catch (Exception $e) {
      throw new Exception(json_encode([$e->getMessage()]));
    }
    return $model;
  }

  public static function updateRec($model_id, array $rec)
  {
    
    // if ($master_id == null) {
    $model = Fppo::findOrFail($model_id);
    // } else {
    //   $model = Fppo::where($parent_key, $master_id)
    //     ->where('id', $model_id)
    //     ->firstOrFail();
    // }
    // return $model;

    // if ($model->status == "Closed") {
    //   throw new Exception(json_encode(["err" => ["FPPO has been Closed. No modifications are allowed."]]));
    // }
    if(array_key_exists('updated_at', $rec)){
      if (!$model->updated_at->eq(\Carbon\Carbon::parse($rec['updated_at']))) {
        $entity = (new \ReflectionClass($model))->getShortName();
        throw new ConcurrencyCheckFailedException($entity);
      }
    }
    Utilities::hydrate($model, $rec);
    if (array_key_exists('qty_json', $rec)) {
      $rec['qty_json'] = json_encode($rec['qty_json']);
    }

    
    $validator = Validator::make(
      $rec,
      FppoUpdateValidator::getUpdateRules($model_id)
    );
    if (array_key_exists('qty_json', $rec)) {
      // $rec['qty_json'] = json_decode($rec['qty_json']);
      $rec['qty_json'] = Utilities::json_numerize(json_decode($rec['qty_json'], true), "int");
    }
    if ($validator->fails()) {
      throw new Exception($validator->errors());
    }
    try {
     $result =  $model->update($rec);
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

    if (CutUpdate::whereIn('fppo_id', $recs)->exists()) {
      throw new \App\Exceptions\GeneralException('Fppo has progressed, Not allowed to delete');
    };

    if (Bundle::whereIn('fppo_id', $recs)->exists()) {
      throw new \App\Exceptions\GeneralException('Fppo has progressed, Not allowed to delete');
    };

    FpoCutPlan::whereIn('fppo_id', $recs)->update(['fppo_id' => null]);

    Fppo::destroy($recs);
  }

  public static function getSumOfCutUpdates($fppo_id)
  {
    $ret = [];
   // $recs = self::_getUnutilizedCutUpdates($fppo_id);

   $utilized_cut_update = CutUpdate::select('bundles.*')
   ->join('bundle_cut_update', 'bundle_cut_update.cut_update_id', '=', 'cut_updates.id')
    ->join('bundles', 'bundles.id', '=', 'bundle_cut_update.bundle_id')
   ->where('cut_updates.fppo_id', $fppo_id)
   ->distinct('bundle_cut_update.bundle_id')
   //->pluck('cut_updates.id')
   ->get();

   $recs=CutUpdate::where('fppo_id', $fppo_id)
   //->whereNotIn('id', $utilized_cut_update)
   ->get();
    //print_r($utilized_cut_update);
    foreach ($recs as $rec) {
      foreach ($rec['qty_json'] as $key => $value) {
        $ret[$key] = $value + (array_key_exists($key, $ret) ? $ret[$key] : 0);
      }      
    }

    foreach ($utilized_cut_update as $rec) {
      // foreach ($rec['qty_json'] as $key => $value) {
      //   $ret[$key] = $value + (array_key_exists($key, $ret) ? $ret[$key] : 0);
      // }      
      if(intval($rec->quantity) > 0){
        $ret[$rec->size] = (array_key_exists($rec->size, $ret) ? $ret[$rec->size] : 0) - $rec->quantity;
      }
    }

    return $ret;
  }

  private static function _getUnutilizedCutUpdates($fppo_id)
  {

    $utilized_cut_update = CutUpdate::join('bundle_cut_update', 'bundle_cut_update.cut_update_id', '=', 'cut_updates.id')
      ->where('fppo_id', $fppo_id)
      ->pluck('id')
      ->toArray();

    return CutUpdate::where('fppo_id', $fppo_id)
      //->whereNotIn('id', $utilized_cut_update)
      ->get();
  }

  public function createBundle(Request $request)
  {
    try {

      DB::beginTransaction();
      $bundles = [];
      $bundle = null;
      $cutUpdates = [];

      $soc = $request->input('soc');
      $fppo_id = $request->input('fppo_id');
      $isEqual = $request->input('is_equal', false);
      $bundleSize = $request->input('bundle_size');
      $different_size_json = $request->input('different_size_json');
      $combineLastTwo = $request->input('combine_last_two');
      $sumOfCutUpdates = $this->getSumOfCutUpdates($fppo_id);

      if (($isEqual) && ($bundleSize <= 0)) {
        throw new Exception("Bundle Size must be greater than zero.");
      }
      $sumOfCutUpdates_sum = 0;
      foreach ($sumOfCutUpdates as $key => $value) {
        $sumOfCutUpdates_sum += $value;
      }
      if ($sumOfCutUpdates_sum == 0) {
        throw new Exception("No available quantity to create bundles.");
      }

      $fpoCutPlan = FpoCutPlan::where('fppo_id', $fppo_id)->first();
      $fpo_operations = $fpoCutPlan->fpo->fpo_operations;

      foreach ($sumOfCutUpdates as $key => $qty) {
        $bundleSize = $request->input('bundle_size');
        if (!($isEqual)) {
          $bundleSize = $different_size_json[$key];
        }
        Log::info('--------$bundleSize--------');
        Log::info($bundleSize);
        if (($bundleSize > 0) && ($qty > 0)) {
          $bundleSize = ($bundleSize - $qty) > 0 ? $qty : $bundleSize;

          $noOfIterations = intval($qty / $bundleSize);
          $remainder = fmod($qty, $bundleSize);
          if ($combineLastTwo) {
            if ($remainder > 0) {
              $noOfIterations--;
              $quantity = $bundleSize + $remainder;
            }
          } else {
            $quantity = $remainder;
          }

          ////////////////////////   Get SOC MAX Sequence    ///////////////////
          $max = 1;
          $max_sequence = DB::table('socs')
            ->select('max_sequence')
            ->where('id', $soc)
            ->first();
          if(intval($max_sequence->max_sequence) > 0){
           $max = $max_sequence->max_sequence;
          }


          for ($i = 1; $i <= $noOfIterations; $i++) {
            $endSeq = $max+$bundleSize-1;
            if($endSeq > 9999){
              $endSeq - 9999;
            }
            $bundle = BundleRepository::createRec([
              'size' => $key,
              'quantity' => $bundleSize,
              'fppo_id' => $fppo_id,
              'number_sequence'=>$max." - ".$endSeq,
            ]);

            /////////////////////////////  Create Bundle Ticket   ///////////////////////////////////

            $job_card_bundles = DB::table('bundles')

            ->select('bundles.id as bundle_id','bundles.quantity as original_quantity','fpo_operations.id as fpo_operation_id','fpo_operations.operation')
            ->join('fpo_cut_plans', 'bundles.fppo_id', '=', 'fpo_cut_plans.fppo_id')
            ->join('fpo_operations', 'fpo_operations.fpo_id', '=', 'fpo_cut_plans.fpo_id')
            ->where('bundles.id', $bundle->id)
            ->distinct('fpo_operations.operation')
            ->get();

            
            foreach($job_card_bundles as $rec){
              for($j=0; $j<2; $j++){
                $direction = "IN";
                if($j == 1){
                  $direction = "OUT";
                }

                if($rec->operation == "CT" || $rec->operation == "KD"){


                    $id = DB::table('bundle_tickets')->insertGetId([
                        'bundle_id' => $rec->bundle_id,
                        'original_quantity' => $rec->original_quantity,
                        'scan_quantity' => $rec->original_quantity,
                        'fpo_operation_id' => $rec->fpo_operation_id,
                        'direction' => $direction,
                        'scan_date_time' => now(),
                        'created_at' => now(),
                        'updated_at' => now()
                    ]);

                    $secId = DB::table('bundle_ticket_secondaries')->insertGetId([
                        'bundle_id' => $rec->bundle_id,
                        'original_quantity' => $rec->original_quantity,
                        'scan_quantity' => $rec->original_quantity,
                        'bundle_ticket_id' => $id,
                        'scan_date_time' => now(),
                        'created_at' => now(),
                        'updated_at' => now()
                    ]);
                }
                else{
                  DB::insert('insert into bundle_tickets (bundle_id, original_quantity,fpo_operation_id,direction,created_at,updated_at) values (?, ?, ?, ?, ?, ?)',
                  [$rec->bundle_id, $rec->original_quantity,  $rec->fpo_operation_id,$direction,now(),now()]);
                }

              }
            }

            ///////////////////////////////////////////////////////////////
            $max = $max+$bundleSize;
            if($max > 9999){
              $max = $max-9999;
            }
            //add id to budle arrya for cut update processing  
            $bundles[$key][] = [$bundle->id => $bundle->quantity];

            //create bundle ticket for each FpoOperation - start
            // foreach ($fpo_operations as $fpo_operation) {
            //   BundleTicketRepository::createRec([
            //     'bundle_id' => $bundle->id,
            //     'original_quantity' => $bundle->quantity,
            //     'fpo_operation_id' => $fpo_operation->id
            //   ]);
            // }
          }
          //handle last bundle
          if ($remainder > 0) {
            $endSeq = $max+$quantity-1;
            if($endSeq > 9999){
              $endSeq - 9999;
            }
            $bundle = BundleRepository::createRec([
              'size' => $key,
              'quantity' => $quantity,
              'fppo_id' => $fppo_id,
              'number_sequence'=>$max." - ".$endSeq,
            ]);

                        /////////////////////////////  Create Bundle Ticket   ///////////////////////////////////

                        $job_card_bundles = DB::table('bundles')

                        ->select('bundles.id as bundle_id','bundles.quantity as original_quantity','fpo_operations.id as fpo_operation_id','fpo_operations.operation')
                        ->join('fpo_cut_plans', 'bundles.fppo_id', '=', 'fpo_cut_plans.fppo_id')
                        ->join('fpo_operations', 'fpo_operations.fpo_id', '=', 'fpo_cut_plans.fpo_id')
                        ->where('bundles.id', $bundle->id)
                        ->distinct('fpo_operations.operation')
                        ->get();
            
                        
                        foreach($job_card_bundles as $rec){
                          for($j=0; $j<2; $j++){
                            $direction = "IN";
                            if($j == 1){
                              $direction = "OUT";
                            }
            
                            if($rec->operation == "CT" || $rec->operation == "KD"){
            
            
                                $id = DB::table('bundle_tickets')->insertGetId([
                                    'bundle_id' => $rec->bundle_id,
                                    'original_quantity' => $rec->original_quantity,
                                    'scan_quantity' => $rec->original_quantity,
                                    'fpo_operation_id' => $rec->fpo_operation_id,
                                    'direction' => $direction,
                                    'scan_date_time' => now(),
                                    'created_at' => now(),
                                    'updated_at' => now()
                                ]);
            
                                $secId = DB::table('bundle_ticket_secondaries')->insertGetId([
                                    'bundle_id' => $rec->bundle_id,
                                    'original_quantity' => $rec->original_quantity,
                                    'scan_quantity' => $rec->original_quantity,
                                    'bundle_ticket_id' => $id,
                                    'scan_date_time' => now(),
                                    'created_at' => now(),
                                    'updated_at' => now()
                                ]);
                            }
                            else{
                              DB::insert('insert into bundle_tickets (bundle_id, original_quantity,fpo_operation_id,direction,created_at,updated_at) values (?, ?, ?, ?, ?, ?)',
                              [$rec->bundle_id, $rec->original_quantity,  $rec->fpo_operation_id,$direction,now(),now()]);
                            }
            
                          }
                        }
            
             ///////////////////////////////////////////////////////////////
                        
            $max = $max+$quantity;
            if($max > 9999){
              $max = $max-9999;
            }
            if($max != 1){
              if($max > 9999){
               $max = $max - 9999;
              }

            
            //add id to budle arrya for cut update processing 
            $bundles[$key][] = [$bundle->id => $bundle->quantity];

            //create bundle ticket for each FpoOperation - start
            // foreach ($fpo_operations as $fpo_operation) {
            //   BundleTicketRepository::createRec([
            //     'bundle_id' => $bundle->id,
            //     'original_quantity' => $bundle->quantity,
            //     'fpo_operation_id' => $fpo_operation->id
            //   ]);
            // }
          }
        }

        ///////////////////////  Update SOC MAx Sequence   ///////////////////////
        DB::table('socs')
        ->where('id', $soc)
        ->update(['max_sequence' => $max]);
       }
      }
      //----- Bundle Creation Completed ------------------//
      
      $unutilized_cut_update = self::_getUnutilizedCutUpdates($fppo_id);

      foreach ($sumOfCutUpdates as $key => $qty) {
        //avoid 0 quantity sizes
        if ($qty != 0) {
          $bundle_id_array = [];
          $cut_update_id_array = [];
          $combined_array = [];
          $start_index = 0;
          $total_length_of_array = 0;

          if (array_key_exists($key, $bundles)) {
            foreach ($bundles[$key] as $bundle) {
              $bundle_id = array_key_first($bundle);
              $bundle_qty = $bundle[$bundle_id];
              $bundle_id_array = array_merge($bundle_id_array, array_fill($start_index, $bundle_qty, $bundle_id));
              $start_index = $bundle_qty - 1;
              $total_length_of_array += $bundle_qty;
            }
            $start_index = 0;
            
            foreach ($unutilized_cut_update as $cutrec) {
              $cut_update_id = $cutrec->id;
              $cut_update_qty = $cutrec->qty_json[$key];
              $cut_update_id_array = array_merge($cut_update_id_array, array_fill($start_index, $cut_update_qty, $cut_update_id));
              $start_index = $cut_update_qty - 1;
            }
            
            foreach ($bundle_id_array as $index => $value) {
              $combined_array[] = $bundle_id_array[$index] . '!' . $cut_update_id_array[$index];
            }

            $combined_array = array_count_values($combined_array);

            $bundle_cut_updates = [];
            
            foreach ($combined_array as $key => $value) {
              $bundle_cut_updates[] = [
                "bundle_id" => explode('!', $key)[0],
                "cut_update_id" => explode('!', $key)[1],
                "quantity" => $value
              ];
            }
            
            DB::table('bundle_cut_update')->insert($bundle_cut_updates);
          }
        }
      }

      $sumOfCutUpdates = $this->getSumOfCutUpdates($fppo_id);
      foreach($sumOfCutUpdates as $k=>$v){
        if($v < 0){
          throw new Exception("Network Error Occured");
        }
        
      }
      DB::commit();
      return response()->json(["status" => "success"], 200);
    } catch (Exception $e) {
      DB::rollBack();
      throw new \App\Exceptions\GeneralException($e->getMessage());
    }
  }

  public static function createBundleTicketByBundle($bundle_id){

  }

  public function createBundleTickets($fppo_id)
  {

    //get bundles
    try {
      $bundles = Bundle::where('fppo_id', $fppo_id)->get();

      if(!($bundles->count() > 0 )){
        throw new Exception ("Please generate bundles, before trying to print tickets");
      }

      $fpoCutPlan = FpoCutPlan::where('fppo_id', $fppo_id)->first();     

      $fpo_operations = $fpoCutPlan->fpo->fpo_operations;

      if (!($fpo_operations->count()>0)) {
        throw new Exception("Fpo Operations not found, please verify with integration logs");
      }
      
      foreach ($bundles as $bundle) {
        foreach ($fpo_operations as $fpo_operation) {
          Log::info($fpo_operation->routing_operation->in);

          if (($fpo_operation->routing_operation->in == 1) && ($fpo_operation->print_bundle == 1)) {
            $BundleTicket = BundleTicket::where('bundle_id', $bundle->id)
              ->where('fpo_operation_id', $fpo_operation->id)
              ->where('direction', 'IN')
              ->get();
            
            if (!($BundleTicket->count() > 0)) {
              BundleTicketRepository::createRec([
                'bundle_id' => $bundle->id,
                'original_quantity' => $bundle->quantity,
                'fpo_operation_id' => $fpo_operation->id,
                'direction' => 'IN'
              ]);
            }
          }

          if (($fpo_operation->routing_operation->out == 1) && ($fpo_operation->print_bundle == 1)) {
            $BundleTicket = BundleTicket::where('bundle_id', $bundle->id)
              ->where('fpo_operation_id', $fpo_operation->id)
              ->where('direction', 'OUT')
              ->get();
            if (!($BundleTicket->count() > 0)) {
              BundleTicketRepository::createRec([
                'bundle_id' => $bundle->id,
                'original_quantity' => $bundle->quantity,
                'fpo_operation_id' => $fpo_operation->id,
                'direction' => 'OUT'
              ]);
            }
          }
        }
      }
      $created_tickets = Bundle::select(
        'bundle_tickets.id',
        'routing_operations.operation_code',
        'bundle_tickets.direction',
        'bundles.size',
        'bundle_tickets.original_quantity as quantity'
      )
        ->join('bundle_tickets', 'bundle_tickets.bundle_id', '=', 'bundles.id')
        ->join('fpo_operations', 'bundle_tickets.fpo_operation_id', '=', 'fpo_operations.id')
        ->join('routing_operations', 'fpo_operations.routing_operation_id', '=', 'routing_operations.id')
        ->where('bundles.fppo_id', $fppo_id)
        ->distinct()
        ->get();

      DB::commit();
      return $created_tickets;
    } catch (Exception $e) {
      DB::rollBack();
      throw new \App\Exceptions\GeneralException($e->getMessage());
    }
  }
  
  public function createManualBundle($request){
    try {

      DB::beginTransaction();
      $bundles=[];
      $fppo_id = $request->input('fppo_id');
      $soc = $request->input('soc');
      $bundleSize = json_decode(json_encode($request->input('bundle_size'),true));
      $totalSize = json_decode(json_encode($request->input('total'),true));
      $sumOfCutUpdates = $this->getSumOfCutUpdates($fppo_id);

   

      if(sizeof($request->input('bundle_size')) > sizeof($request->input('total'))){
        throw new \App\Exceptions\GeneralException('Number Of Total Size Need to be grater than to the No of bundle size');
      }

      foreach($bundleSize as $key=>$value){
        $found = false;
        foreach($sumOfCutUpdates as $key1=>$value1){
          if($key == $key1 && $key != 'Total'){
            $found = true;
            if( intval($value) > intval($value1)){

              throw new \App\Exceptions\GeneralException('Not Available Qty For Size '.$key.'');
            }
          }
        }
        if(!$found && $key != 'Total'){
          throw new \App\Exceptions\GeneralException('Not Available Qty For Size '.$key.'');
        }
      }

      foreach($totalSize as $key=>$value){
        $found = false;
        foreach($sumOfCutUpdates as $key1=>$value1){
          if($key == $key1 && $key != 'Total'){
            $found = true;
            if( intval($value) > intval($value1)){

              throw new \App\Exceptions\GeneralException('Not Available Qty For Size '.$key.'');
            }
          }
        }
        if(!$found && $key != 'Total'){
          throw new \App\Exceptions\GeneralException('Not Available Qty For Size '.$key.'');
        }
      }


      foreach($totalSize as $key=>$value){
        $found = false;
        foreach($bundleSize as $key1=>$value1){
          if($key == $key1){
            $found = true;
            if(!(intval($value)%intval($value1) == 0)){
              throw new \App\Exceptions\GeneralException('Cannot Devide Total Qty From Bundle Size For Size '.$key.'');
            }          
          }
        }
        if (!$found && $key != 'Total'){
          throw new \App\Exceptions\GeneralException('Mismatch '.$key.'');
        }
       }

      //////////////////  Get Max Sequence in SOC Table   ///////////////////
       $max = 1;
       $max_sequence = DB::table('socs')
       ->select('max_sequence')
       ->where('id', $soc)
       //->orderby('carton_packing_list.id')
       ->first();
       if(intval($max_sequence->max_sequence) > 0){
        $max = $max_sequence->max_sequence;
       }

       foreach($totalSize as $key=>$value){
        foreach($bundleSize as $key1=>$value1){
          if($key == $key1 && (intval($value)%intval($value1) == 0)){
            for($i=0; $i< (intval($value)/intval($value1)); $i++){
              $endSeq = $max+$value1-1;
              if($endSeq > 9999){
                $endSeq - 9999;
              }
              $bundle = BundleRepository::createRec([
                'size' => $key,
                'quantity' => $value1,
                'fppo_id' => $fppo_id,
                'number_sequence'=>$max." - ".$endSeq,
              ]);
              $max = $max+$value1;
              if($max > 9999){
                $max = $max-9999;
              }
              $bundles[$key][] = [$bundle->id => $bundle->quantity];
            }
          }
        }
       } 

       if($max != 1){
         if($max > 9999){
          $max = $max - 9999;
         }
        DB::table('socs')
        ->where('id', $soc)
        ->update(['max_sequence' => $max]);
       }


       ////   Bundle Creation Done /////////////

       $unutilized_cut_update = self::_getUnutilizedCutUpdates($fppo_id);

       foreach ($sumOfCutUpdates as $key => $qty) {
         //avoid 0 quantity sizes
         if ($qty != 0) {
           $bundle_id_array = [];
           $cut_update_id_array = [];
           $combined_array = [];
           $start_index = 0;
           $total_length_of_array = 0;
 
           if (array_key_exists($key, $bundles)) {
             foreach ($bundles[$key] as $bundle) {
               $bundle_id = array_key_first($bundle);
               $bundle_qty = $bundle[$bundle_id];
               $bundle_id_array = array_merge($bundle_id_array, array_fill($start_index, $bundle_qty, $bundle_id));
               $start_index = $bundle_qty - 1;
               $total_length_of_array += $bundle_qty;
             }
             $start_index = 0;
             
             foreach ($unutilized_cut_update as $cutrec) {
               $cut_update_id = $cutrec->id;
               $cut_update_qty = (array_key_exists($key,$cutrec->qty_json)) ? $cutrec->qty_json[$key] : 0;
               $cut_update_id_array = array_merge($cut_update_id_array, array_fill($start_index, $cut_update_qty, $cut_update_id));
               $start_index = $cut_update_qty - 1;
             }
             
             foreach ($bundle_id_array as $index => $value) {
               $combined_array[] = $bundle_id_array[$index] . '!' . $cut_update_id_array[$index];
             }
 
             $combined_array = array_count_values($combined_array);
 
             $bundle_cut_updates = [];
             
             foreach ($combined_array as $key => $value) {
               $bundle_cut_updates[] = [
                 "bundle_id" => explode('!', $key)[0],
                 "cut_update_id" => explode('!', $key)[1],
                 "quantity" => $value
               ];
             }
             
             DB::table('bundle_cut_update')->insert($bundle_cut_updates);
           }
         }
       }

      // $isEqual = $request->input('is_equal', false);
     // $different_size_json = $request->input('different_size_json');
     // $combineLastTwo = $request->input('combine_last_two');
     // 

     $sumOfCutUpdates = $this->getSumOfCutUpdates($fppo_id);


      DB::commit();
      return response()->json(["status" => "success"], 200);
    //  return $created_tickets;
    } catch (Exception $e) {
      DB::rollBack();
      throw new \App\Exceptions\GeneralException($e->getMessage());
    }
  }
}
