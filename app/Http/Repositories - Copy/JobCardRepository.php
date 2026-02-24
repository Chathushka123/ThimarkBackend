<?php

namespace App\Http\Repositories;

use App\Bundle;
use App\BundleTicket;
use App\DailyShiftTeam;
use App\Exceptions\ConcurrencyCheckFailedException;
use App\Exceptions\GeneralException;
use App\Fpo;
use App\Http\Resources\JobCardBundleResource;
use Illuminate\Http\Request;
use App\JobCard;
use App\DailyShift;
use App\Http\Resources\JobCardResource;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use App\Http\Resources\JobCardWithParentsResource;
use Illuminate\Validation\Rule;
use Exception;

use App\Http\Validators\JobCardCreateValidator;
use App\Http\Validators\JobCardUpdateValidator;
use App\JobCardBundle;
use App\TrimStore;
use App\Team;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Database\Eloquent\Builder;
use PDF;

class JobCardRepository
{
  #region ----- CORE BASE methods -----

  #region show
  public function show(JobCard $jobCard)
  {
    return response()->json(
      [
        'status' => 'success',
        'data' => new JobCardWithParentsResource($jobCard),
      ],
      200
    );
  }
  #endregion

  #region createRec
  public static function createRec(array $rec)
  {
    $validator = Validator::make(
      $rec,
      JobCardCreateValidator::getCreateRules()
    );
    if ($validator->fails()) {
      throw new Exception($validator->errors());
    }

    try {
      $rec['status'] = JobCard::getInitialStatus();
      $model = JobCard::create($rec);
    } catch (Exception $e) {
      throw new \App\Exceptions\GeneralException($e->getMessage());
    }
    return $model;
  }
  #endregion

  #region updateRec
  public static function updateRec($model_id, array $rec)
  {
    $model = JobCard::findOrFail($model_id);

    if (!$model->updated_at->eq(\Carbon\Carbon::parse($rec['updated_at']))) {
      $entity = (new \ReflectionClass($model))->getShortName();
      throw new ConcurrencyCheckFailedException($entity);
    }

//      if ($model->status == "Finalized" || $model->status == "Open"  || $model->status == "Issued") {
//          throw new Exception("Changes are not allowed. Job Card has progressed.");
//      }

    if ($model->status == "Finalized") {
      throw new Exception("Changes are not allowed. Job Card has progressed.");
    }

    Utilities::hydrate($model, $rec);
    $validator = Validator::make(
      $rec,
      JobCardUpdateValidator::getUpdateRules($model_id)
    );

    if ($validator->fails()) {
      throw new Exception($validator->errors());
    }
    try {
      $model->update($rec);
      // if (isset($rec['finalized'])) {
      //   if (($model->status == "Open") && ($rec['finalized'] == true)) {
      //     return self::fsmFinalize($model);
      //   }
      //   if (($model->status == "Finalized") && ($rec['finalized'] == false)) {
      //     return self::fsmUnfinalize($model);
      //   }
      // }
      // else {
      //   if ($model->status == "Finalized"){
      //     throw new Exception("Changes are not allowed. Job Card has progressed.");
      //   }
      // }
    } catch (Exception $e) {
      throw new \App\Exceptions\GeneralException($e->getMessage());
    }
    return $model;
  }
  #endregion

  #region createMultipleRecs
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
  #endregion

  #region updateMultipleRecs
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
  #endregion

  #region deleteRecs
  public static function deleteRecs(array $recs)
  {
    try {
      DB::beginTransaction();
      foreach ($recs as $jc_id) {
        $jc = JobCard::findOrFail($jc_id);
        if ($jc->status === "Issued") {
          throw new \App\Exceptions\GeneralException("Job Card is Issued and cannot be deleted.");
        } else {
          $tss = TrimStore::where('job_card_id', $jc_id)->first();
          if (!is_null($tss) && ($tss->trim_status !== "Pending")) {
            throw new \App\Exceptions\GeneralException("Trims have been created and Job Card cannot deleted.");
          } else {
            $jcbs = JobCardBundle::where('job_card_id', $jc->id)->whereHas('bundle_bins', function (Builder $query) {
              $query->whereNotNull('bundle_id');
            })->get();
            if (sizeof($jcbs) > 0) {
              throw new \App\Exceptions\GeneralException("Resize bundles have been created. Cannot delete.");
            }
          }
        }
        $jcbs = JobCardBundle::where('job_card_id', $jc_id)->get();
        if ($jcbs->count() > 0) {
          foreach ($jcbs as $jcb) {
            JobCardBundleRepository::deleteRecs([$jcb->id]);
          }
        }
        $trims = TrimStore::where('job_card_id', $jc_id)->get();
        if ($trims->count() > 0) {
          foreach ($trims as $tr) {
            TrimStoreRepository::deleteRecs([$tr->id]);
          }
        }
        JobCard::destroy($jc_id);
      }
      DB::commit();
      return response()->json([], 200);
    } catch (Exception $e) {
      DB::rollBack();
      throw $e;
    }
  }
  #endregion

  #endregion

  #region ----- CORE FSM methods -----

  #region fsmOpen
  public static function fsmOpen(JobCard $jobCard)
  {
    if ($jobCard->status != "") {
      throw new \App\Exceptions\GeneralException("Job Card cannot open");
    }

    $jobCard->update([
      'status' => 'Open'
    ]);
    return response()->json(["status" => "success"], 200);
  }
  #endregion

  #region fsmProgress
  public static function fsmProgress(JobCard $jobCard)
  {
    $nextStatuses = $jobCard->getNextStatuses($jobCard->status);

    if (!array_key_exists('progress', $nextStatuses)) {
      throw new \App\Exceptions\GeneralException("Job Card cannot set to Progress");
    }

    $jobCard->update([
      'status' => 'InProgress'
    ]);

    return response()->json(["status" => "success"], 200);
  }
  #endregion

  #region fsmFinalize
  public static function fsmFinalize(JobCard $jobCard)
  {
    $nextStatuses = $jobCard->getNextStatuses($jobCard->status);

    if (!array_key_exists('finalize', $nextStatuses)) {
      throw new \App\Exceptions\GeneralException("Job Card cannot finalize");
    }

    try {
      DB::beginTransaction();
      //Resize Bundles
      self::_handleBundleResizing($jobCard);

      $jobCard->update([
        'status' => 'Finalized'
      ]);

      if ($jobCard->trims_required) {
        $trim_rec = TrimStore::where('job_card_id', $jobCard->id)->get();
        if (!($trim_rec->count() > 0)) {
          TrimStoreRepository::createRec(['job_card_id' => $jobCard->id, 'trim_status' => TrimStore::getInitialStatus()]);
        }
      }

      ////// Insert Bunddle Ticket///////////////////////////
      DB::statement("SET SESSION sql_mode=(SELECT REPLACE(@@sql_mode,'ONLY_FULL_GROUP_BY',''))");
      $job_card_bundles = DB::table('job_cards')

      ->select('job_card_bundles.bundle_id','job_card_bundles.original_quantity','fpo_operations.id as fpo_operation_id','fpo_operations.operation')
      ->join('job_card_bundles', 'job_card_bundles.job_card_id', '=', 'job_cards.id')
      ->join('fpo_operations', 'fpo_operations.fpo_id', '=', 'job_cards.fpo_id')
      ->where('job_cards.id', $jobCard->id)
      ->groupBy('fpo_operations.operation','job_card_bundles.bundle_id')
      ->orderBy('fpo_operations.id','DESC')
	    
      ->get();



      foreach($job_card_bundles as $rec){
        $bundle_ticket = BundleTicket::where('bundle_tickets.bundle_id',$rec->bundle_id)
        ->where('fpo_operation_id',$rec->fpo_operation_id)
        ->first();

        if(is_null($bundle_ticket)){  
          for($i=0; $i<2; $i++){
            $direction = "IN";
            if($i == 1){
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
      }

      DB::commit();
      return response()->json(["status" => "success"], 200);
    } catch (Exception $e) {
      throw $e;
    }
  }
  #endregion

  #region fsmUnfinalize
  public static function fsmReopen(JobCard $jobCard)
  {
    $nextStatuses = $jobCard->getNextStatuses($jobCard->status);

    if (!array_key_exists('reopen', $nextStatuses)) {
      throw new \App\Exceptions\GeneralException("Job Card has been progressed, Cannot Re-Open");
    }

    try {
      DB::beginTransaction();

      $jobCard->update([
        'status' => 'Open'
      ]);

      if (TrimStore::where('job_card_id', $jobCard->id)->whereIn('trim_status', ['Processing', 'OnHold', 'Ready'])->exists()) {
        throw new \App\Exceptions\GeneralException("Trims in progress, cannot Re-Open the Job Card");
      } else {
        TrimStoreRepository::deleteRecs(TrimStore::where(['job_card_id' => $jobCard->id])->pluck('id')->toArray());
      }

      $bundle_tickets = BundleTicket::join('bundles', 'bundle_tickets.bundle_id', '=', 'bundles.id')
        ->join('job_card_bundles', 'job_card_bundles.bundle_id', '=', 'bundles.id')
        ->join('fpo_operations', 'fpo_operations.id', '=', 'bundle_tickets.fpo_operation_id')
        ->where('fpo_operations.operation', "!=", "KD")
        ->where('job_card_bundles.job_card_id', $jobCard->id)
        ->whereNotNull('bundle_tickets.scan_quantity')
        ->get();



      if ($bundle_tickets->count() > 0) {
        throw new \App\Exceptions\GeneralException("Scanning in progress, cannot Re-Open the Job Card");
      }

      ///////////////////////// Delete Bundle Ticket   ///////////////////////////////

      // $job_card_bundles = DB::table('job_card_bundles')
      // ->select('job_card_bundles.bundle_id')
      // ->where('job_card_bundles.job_card_id', $jobCard->id)
      // ->get();



      // foreach($job_card_bundles as $rec){
      //   DB::table('bundle_ticket_secondaries')->where('bundle_id', '=', $rec->bundle_id)->delete();
      //   DB::table('bundle_tickets')->where('bundle_id', '=', $rec->bundle_id)->delete();
      // }

      DB::commit();
      return response()->json(["status" => "success"], 200);
    } catch (Exception $e) {
      throw $e;
    }
  }
  #endregion

  #region fsmRefinalize
  public static function fsmRefinalize(JobCard $jobCard)
  {
    $nextStatuses = $jobCard->getNextStatuses($jobCard->status);

    if (!array_key_exists('refinalize', $nextStatuses)) {
      throw new \App\Exceptions\GeneralException("Job Card cannot finalize");
    }

    $jobCard->update([
      'status' => 'Finalized'
    ]);
    return response()->json(["status" => "success"], 200);
  }
  #endregion

  #region fsmIssue
  public static function fsmIssue(JobCard $jobCard, $action, $req)
  {
//      print_r($req->issued_at);
//      print_r($req->issued_by);
//      print_r($jobCard);
    $nextStatuses = $jobCard->getNextStatuses($jobCard->status);

    if (!array_key_exists('issue', $nextStatuses)) {
      throw new \App\Exceptions\GeneralException("Job Card cannot issue");
    }

    date_default_timezone_set("Asia/Calcutta");
    $dateTime = date("Y-m-d H:i:s");
    $shift = DailyShift::select('id')

    ->where('daily_shifts.start_date_time','<=' ,$dateTime)
    ->where('daily_shifts.end_date_time','>=' ,$dateTime)
    ->first();

    if(is_null($shift)){
      throw new \App\Exceptions\GeneralException('Shift Not Found');
    }

    $currentDate = self::getCurrentSlotDate($req->issued_at);
    if(is_null($currentDate)){
        $jobCard->update([
            'issued_at'=> $req->issued_at,
            'issued_by' => $req->issued_by,
            'status' => 'Issued',
            'daily_shift_id' => is_null($shift)? null : $shift->id
        ]);
    }
    else{
        $jobCard->update([
            'issued_at'=> $req->issued_at,
            'issued_by' => $req->issued_by,
            'status' => 'Issued',
            'current_date' => $currentDate,
            'daily_shift_id' => is_null($shift)? null : $shift->id
        ]);
    }



    return response()->json(
      ["status" => "success"],
      200
    );
  }
  #endregion

  #region fsmHold
  public static function fsmHold(JobCard $jobCard)
  {
    $nextStatuses = $jobCard->getNextStatuses($jobCard->status);

    if (!array_key_exists('hold', $nextStatuses)) {
      throw new \App\Exceptions\GeneralException("Job Card cannot hold");
    }

    $jobCard->update([
      'status' => 'Hold'
    ]);
    return response()->json(
      ["status" => "success"],
      200
    );
  }
  #endregion

  #region fsmHold
  public static function fsmUnhold(JobCard $jobCard)
  {
    $nextStatuses = $jobCard->getNextStatuses($jobCard->status);

    if (!array_key_exists('unhold', $nextStatuses)) {
      throw new \App\Exceptions\GeneralException("Job Card cannot finalize");
    }

    $jobCard->update([
      'status' => 'Finalized'
    ]);
    return response()->json(
      ["status" => "success"],
      200
    );
  }
  #endregion

  #region fsmReady
  public static function fsmReady(JobCard $jobCard)
  {
    $nextStatuses = $jobCard->getNextStatuses($jobCard->status);

    if (!array_key_exists('hold', $nextStatuses)) {
      throw new \App\Exceptions\GeneralException("Job Card cannot ready");
    }

    $jobCard->update([
      'status' => 'Ready'
    ]);
    return response()->json(
      ["status" => "success"],
      200
    );
  }
  #endregion

  #endregion

  #region ---- private methods ----
  private static function _handleBundleResizing(JobCard $jobCard)
  {
    $job_card_bundles = JobCardBundle::where('job_card_id', $jobCard->id)->get();

    foreach ($job_card_bundles as $jbundle) {

      if (!(is_null($jbundle->resized_quantity))) {
        $bundle = Bundle::find($jbundle->bundle_id);

        // Check whether this is not from previouse resize
        if ($jbundle->original_quantity != $jbundle->resized_quantity) {

          //First put the balance to bin
          BundleBinRepository::createRec([
            'created_date' => now(),
            'record_type' => 'jc reject',
            'size' => $bundle->size,
            'quantity' => abs($jbundle->original_quantity - $jbundle->resized_quantity),
            'utilized' => false,
            'qc_reject_id' => null,
            'job_card_bundle_id' => $jbundle->id,
            'created_by_id' => auth()->user()->id,
            'bundle_id' => $bundle->id
          ]);


          //update the bundle tickets
          $bndl_tickets = BundleTicket::where('bundle_id', $bundle->id)->get();
          foreach ($bndl_tickets as $bndl_ticket) {
            $bndl_ticket->original_quantity = abs($jbundle->resized_quantity);
            BundleTicketRepository::updateRec(
              $bndl_ticket->id,
              $bndl_ticket->toArray()
            );
          }
          Log::info($jbundle->resized_quantity);
          //update the Bundle quantity
          $bundle->quantity = $jbundle->resized_quantity;
          BundleRepository::updateRec($bundle->id, $bundle->toArray());

          //Finally update bundle record
          $jbundle->original_quantity = $jbundle->resized_quantity;
          $jbundle->resized_quantity = null;
          JobCardBundleRepository::updateRec($jbundle->id,  $jbundle->toArray());
        }
      }
    }
  }
  #endregion

  //bundle resize maintenance
  #region ----- Public methods -----
  #region createAndUpdateJobCard
  public static function createAndUpdateJobCard($request)
  {
    try {
      DB::beginTransaction();

      $model = null;
      $job_card_id = $request->job_card_id;

      $JobCardModle = JobCard::find($job_card_id);
      if (!is_null($JobCardModle) && $JobCardModle->status != 'Open') {
        throw new GeneralException("Job Cad has progressed. Cannot Update.");
      }

      $event = $request->event;
      $jobCardBundles = $request->job_card_bundles;

      $job_card_bundles_upd = $jobCardBundles["UPD"];
      $job_card_bundles_cre = $jobCardBundles["CRE"];
      $job_card_bundles_del = $jobCardBundles["DEL"];
      $rec[] = null;

      if ($event == "CRE") {
        $rec['fpo_id'] = $request->fpo_id;
        $rec['team_id'] = $request->team_id;
        $rec['trims_required'] = $request->trims_required;
        $rec['job_card_date'] = $request->job_card_date;
        $rec['packing_list_no'] = $request->packing_list_no;
        $rec['status'] = 'Open';

        $model = self::createRec($rec);
        $job_card_id = $model->id;
      } elseif (
        $event == "UPD"
      ) {
        $model = JobCard::find($job_card_id);
        $rec['trims_required'] = $request->trims_required;
        $rec['job_card_date'] = $request->job_card_date;
        $rec['updated_at'] = $request->updated_at;
        $rec['packing_list_no'] = $request->packing_list_no;
//        $rec['team_id'] = $request->team_id;
        self::updateRec($model->id, $rec);
      }

      if (isset($job_card_id)) {

        //create bundles
        foreach ($job_card_bundles_cre as $new_bundle) {
          $bundle = Bundle::find($new_bundle['bundle_id']);
          $new_bundle['job_card_id'] = $job_card_id;
          $new_bundle['original_quantity'] = $bundle->quantity;
          $new_bundle['line_no'] = 0;

          JobCardBundleRepository::createRec($new_bundle);
        }

        //update bundles
        foreach ($job_card_bundles_upd as $upd_bundle) {
          JobCardBundleRepository::updateRec($upd_bundle['job_card_bundle_id'], $upd_bundle);
        }

        //remove bundles
        JobCardBundleRepository::deleteRecs($job_card_bundles_del);
      } else {
        throw new Exception("Insufficient information about Job Card");
      }
      DB::commit();
      return response()->json(["status" => "success", "data" => $model], 200);
    } catch (Exception $e) {
      DB::rollBack();
      throw new \App\Exceptions\GeneralException($e->getMessage());
    }
  }
  #endregion

  #Fpo Job card Quantity

  public static function getFpoJobCardQty($fpo_id){
    $data = [];
    $fpo = Fpo::find($fpo_id);
    $data['total'] = $fpo->qty_json;
    $data['unallocated'] = [];
    $data['allocated']=[];

    $allocatedJobCardBundle = JobCardBundle::select('bundles.quantity','bundles.size')
      ->join('bundles', 'job_card_bundles.bundle_id', '=', 'bundles.id')
      ->join('job_cards', 'job_cards.id', '=', 'job_card_bundles.job_card_id')
      ->where('job_cards.fpo_id','=',$fpo_id)
      ->get();



      $fpoBundle = Bundle::select('quantity','bundles.size','bundles.id')
        ->join('fpo_cut_plans', 'fpo_cut_plans.fppo_id', '=', 'bundles.fppo_id')        
        ->where('fpo_cut_plans.fpo_id','=',$fpo_id)
        ->where('bundles.location_id','!=',null)
        ->distinct()
        ->get();

      foreach($fpoBundle as $rec){
        $qty = intval($rec->quantity);
        $size = $rec->size;

        if(array_key_exists($size, $data['unallocated'])){
          $data['unallocated'][$size] = intval($data['unallocated'][$size])+intval($qty);
        }else{
          $data['unallocated'][$size] = intval($qty);
        }
      }

      foreach($allocatedJobCardBundle as $rec){
        
        $qty = intval($rec->quantity);
        $size = $rec->size;
       
        if(array_key_exists($size, $data['allocated'])){
          $data['allocated'][$size] = intval($data['allocated'][$size])+intval($qty);
          $data['unallocated'][$size] = intval($data['unallocated'][$size])-intval($qty);
        }else{
          $data['allocated'][$size] = intval($qty);
          $data['unallocated'][$size] = intval($data['unallocated'][$size])-intval($qty);
        }
      }
  

    return $data;
  }

  #region getFullJobCard
  public static function getFullJobCard($job_card_id)
  {

    $job_card = JobCard::find($job_card_id);

    $fpo_id = $job_card->fpo_id;
    $fpo = Fpo::find($fpo_id);
    $job_card->fpo_no = $fpo->wfx_fpo_no;
    $job_card->soc_id = $fpo->soc->id;
    $job_card->soc_no = $fpo->soc->wfx_soc_no;

    $team = Team::find($job_card->team_id);
    $job_card->team_code = $team->code;

    $fpoJobcardQty = self::getFpoJobCardQty($fpo_id);

    $fpo_utilized_bundles = JobCardBundle::join('job_cards', 'job_card_bundles.job_card_id', '=', 'job_cards.id')
      ->where('job_cards.fpo_id', $fpo_id)
      ->pluck('job_card_bundles.bundle_id')->toArray();

    $unutilized_bundles = Bundle::select(
      DB::raw("NULL as job_card_bundle_id"),
      'fppos.fppo_no',
      'bundles.id as bundle_id',
      'bundles.size',
      'bundles.special_remarks',
      'bundles.fppo_id',
      'bundles.quantity as original_quantity',
      DB::raw("NULL as resized_quantity"),
      DB::raw("NULL as updated_at")
    )
      ->join('fppos', 'bundles.fppo_id', '=', 'fppos.id')
      ->join('fpo_cut_plans', 'fpo_cut_plans.fppo_id', '=', 'fppos.id')
      ->join('fpos', 'fpo_cut_plans.fpo_id', '=', 'fpos.id')
      ->join('locations', 'locations.id', '=', 'bundles.location_id')
      ->where('fpos.id', $fpo_id)
      ->where('bundles.location_id','!=' ,null)
	    ->where('bundles.location_id','!=',"")
      ->where('locations.site','!=','Move_To_Inspection')
      ->where('locations.site','!=','EA_Send')
      ->where('locations.site','!=','EA_Ready_To_Send')
      ->whereNotIn('bundles.id', $fpo_utilized_bundles)
      ->distinct()
      ->get();

      foreach($unutilized_bundles as $key =>$val){
        if(is_null($val['special_remarks'])){
          $fppo = $unutilized_bundles[$key]['fppo_id'];

          $remarks = DB::table('cut_plans')
          ->select('cut_plans.special_remark')
          ->join('fpo_cut_plans', 'fpo_cut_plans.cut_plan_id', '=', 'cut_plans.id')
          ->where('fpo_cut_plans.fppo_id', $fppo)
          ->first();

          $unutilized_bundles[$key]['special_remarks'] = $remarks->special_remark;
        }
      }

    $utilizied_bundles = JobCardBundle::select(
      'job_card_bundles.id as job_card_bundle_id',
      'fppos.fppo_no',
      'bundles.fppo_id',
      'bundles.id as bundle_id',
      'bundles.size',
      'bundles.special_remarks',
      'job_card_bundles.original_quantity',
      'job_card_bundles.resized_quantity',
      'job_card_bundles.updated_at'
    )
      ->join('bundles', 'job_card_bundles.bundle_id', '=', 'bundles.id')
      ->join('fppos', 'bundles.fppo_id', '=', 'fppos.id')
      ->where('job_card_bundles.job_card_id', $job_card_id)
      ->where('bundles.location_id','!=' ,null)
      ->distinct()
      ->get();

      foreach($utilizied_bundles as $key =>$val){
        if(is_null($val['special_remarks'])){
          $fppo = $utilizied_bundles[$key]['fppo_id'];

          $remarks = DB::table('cut_plans')
          ->select('cut_plans.special_remark')
          ->join('fpo_cut_plans', 'fpo_cut_plans.cut_plan_id', '=', 'cut_plans.id')
          ->where('fpo_cut_plans.fppo_id', $fppo)
          ->first();

          $utilizied_bundles[$key]['special_remarks'] = $remarks->special_remark;
        }
      }


    return ["JobCard" => $job_card,"qty_json"=>$fpo->qty_json, "fpoJobcardQty"=>$fpoJobcardQty,  "JobCardBundles" => $utilizied_bundles->concat($unutilized_bundles)];
  }
  #endregion

  #region changeJobCardStatus
  public static function changeJobCardStatus($job_card_id, $status)
  {
    $job_card = JobCard::find($job_card_id);
    switch ($status) {
      case 'Finalize':
        return self::fsmFinalize($job_card);
        break;
      case 'Reopen':
        return self::fsmReopen($job_card);
        break;
      default:
        null;
    }
  }
  #endregion

  #region getSupermarketJobCards
  public function getSupermarketJobCards()
  {
    $jobCardsInPool = JobCard::belongsToPool()->where('status', 'Finalized')->withCount('trim_store')->get()->map(function ($jobCard, $key) {
      if ($jobCard->trim_store_count > 0) {
        $jobCard->status = $jobCard->trim_store->trim_status;
      } else {
        $jobCard->status = "Ready";
      }

      $obj = [
        "id" => $jobCard->id,
        "description" => $jobCard->id,
        "imgIndex" => array_search($jobCard->status, TrimStore::getStates()),
        "status" => $jobCard->status,
        "updated_at" => $jobCard->updated_at
      ];
      return $obj;
    });

    $teams = Team::where('code', '<>', 'pool')->get();
    $teamJson = [];
    foreach ($teams as $team) {
      $jobCardsOther = JobCard::where('status', 'Finalized')->where('team_id', $team->id)->withCount('trim_store')->get()->map(function ($jobCard, $key) {
        if ($jobCard->trim_store_count > 0) {
          $jobCard->status = $jobCard->trim_store->trim_status;
        } else {
          $jobCard->status = "Ready";
        }

        $jobQty = DB::table('job_cards')
        ->join('job_card_bundles', 'job_card_bundles.job_card_id', '=', 'job_cards.id')
        ->select(  DB::raw('SUM(IFNULL(job_card_bundles.resized_quantity, job_card_bundles.original_quantity)) as total_qty '))
       // ->groupByRaw('bundles.size')
        ->where('job_card_bundles.job_card_id', $jobCard->id)
        ->first();

        $imgIndex = array_search($jobCard->status, TrimStore::getStates());

        if ($jobCard->status == "Hold") {
          $imgIndex = 0;
        }

        $obj = [
          "id" => $jobCard->id,
          "description" => $jobCard->id,
          "imgIndex" => $imgIndex,
          "status" => $jobCard->status,
          "updated_at" => $jobCard->updated_at,
          "Qty"=>$jobQty->total_qty,
          "Fpo"=>$jobCard->fpo->wfx_fpo_no,
          "Soc"=>$jobCard->fpo->soc->wfx_soc_no,
          "Color"=>$jobCard->fpo->soc->ColorName,
          "style"=>$jobCard->fpo->soc->style->style_code,
          "Date"=> date('Y-m-d', strtotime(str_replace('.', '/', $jobCard->job_card_date)))
        ];
        return $obj;
      });

      $teamJson[$team->id] = [
        "lineId" => $team->id,
        "name" => $team->description,
        "description" => "WIP - " .(new QueriesRepository())->getSuperMarketWip($team->id),
        "jobCards" => $jobCardsOther
      ];
    }

    return ([
      "jobCardPool" => [
        "jobCards" => $jobCardsInPool
      ],
      "lines" => $teamJson
    ]);
  }
  #endregion

  #region getProductionJobCards
  public function getProductionJobCards()
  {
    $teams = Team::where('code', '<>', 'pool')->get();
    //0->red->hold
    //1->yellow->progress
    //2->purple->ready
    //3->blue->issue
    $teamJson = [];
    foreach ($teams as $team) {
      $jobCardsOther = JobCard::whereIn('status', ['Issued', 'InProgress', 'Hold', 'Ready'])
        ->where('team_id', $team->id)
        ->get()->map(function ($jobCard, $key) {
          $imgIndexArray = ['Hold', 'InProgress', 'Ready', 'Issued'];
          $obj = [
            "id" => $jobCard->id,
            "description" => $jobCard->id,
            "imgIndex" => array_search($jobCard->status, $imgIndexArray),
            "status" => $jobCard->status,
            "updated_at" => $jobCard->updated_at
          ];
          return $obj;
        });
      $teamJson[$team->id] = [
        "lineId" => $team->id,
        "name" => $team->description,
        "description" => "WIP - " . (new QueriesRepository())->getProductionWip($team->id),
        "jobCards" => $jobCardsOther
      ];
    }

    return (["lines" => $teamJson]);
  }
  #endregion

  #region moveJobCard
  public function moveJobCard($jobCardId, $toTeamId, $updatedAt)
  {
    try {
      DB::beginTransaction();
      $rec = [];
      $rec['id'] =  $jobCardId;
      $rec['team_id'] = $toTeamId;
      $rec['updated_at'] = $updatedAt;

      // $daily_shift_team = DailyShiftTeam::where('team_id',  2)->where('current_date', now()->format('Y-m-d'))->get();
      // print_r($daily_shift_team);
      if (DailyShiftTeam::where(['team_id' => $toTeamId, 'current_date' => now()->format('Y-m-d')])->count() == 0) {

        throw new Exception("Target team is not valid for today.");
      }

      $model = JobCard::findOrFail($jobCardId);

      if (!$model->updated_at->eq(\Carbon\Carbon::parse($rec['updated_at']))) {
        $entity = (new \ReflectionClass($model))->getShortName();
        throw new ConcurrencyCheckFailedException($entity);
      }

      Utilities::hydrate($model, $rec);
      $model->update($rec);
      DB::commit();
      return $model;
    } catch (Exception $e) {
      DB::rollBack();
      throw new \App\Exceptions\GeneralException($e->getMessage());
    }
  }
  #endregion

  #endregion

  public function printTrimsReport($job_card_id){
    $job_card = JobCard::find($job_card_id);
    $header = [];
    $header['job_card_no'] = $job_card_id;
    $header['soc_no'] = $job_card->fpo->soc->wfx_soc_no;
    $header['fpo_no'] = $job_card->fpo->wfx_fpo_no;
    $header['team'] = $job_card->team->code;
    $header['team_id'] = $job_card->team->id;
    $header['color'] = $job_card->fpo->soc->ColorName;
    $header['style'] = $job_card->fpo->soc->style->style_code;
    $header['customer_style_ref'] = $job_card->fpo->soc->customer_style_ref;
    $header['issued_date'] = $job_card->issued_at;
   // $header['requested_time'] = Carbon::now()->toDateTimeString();

   $packingList = JobCard::select('packing_lists.destination','packing_lists.vpo')
   ->join('packing_lists', 'packing_lists.id', '=', 'job_cards.packing_list_no')
   ->where('job_cards.id', $job_card_id)
   ->first();

   $index = 0;
   $vpo = "";
   $destination = "";

   if(!is_null($packingList)){
      $vpo = $packingList->vpo;
      $length = strlen($vpo);
      $index = strlen($vpo);
      $destination = $packingList->destination;
      for($i = 0; $i < $length; $i++){
         
        if(substr($vpo,$i,1) == "/"){
          $index = $i;
        }
      }
    }


   $header['vpo'] = substr($vpo,0,$index);
   $header['destination'] = $destination;

    Log::info($header);

    $total = DB::table('job_card_bundles')
    ->select( DB::raw('SUM(IFNULL(job_card_bundles.resized_quantity, job_card_bundles.original_quantity)) as total_qty '))
    ->groupByRaw('job_card_bundles.job_card_id')
    ->where('job_card_bundles.job_card_id', $job_card_id)
    ->get();


    $header['total'] = $total[0]->total_qty;

    $details_rec = DB::table('bundles')
            ->join('job_card_bundles', 'job_card_bundles.bundle_id', '=', 'bundles.id')
            ->join('cut_updates', 'cut_updates.fppo_id', '=', 'bundles.fppo_id')
            ->join('fpo_cut_plans', 'fpo_cut_plans.fppo_id', '=', 'cut_updates.fppo_id')
            ->join('cut_plans', 'cut_plans.id', '=', 'cut_updates.cut_plan_id')
            ->join('locations', 'locations.id', '=', 'bundles.location_id')
            ->select('fpo_cut_plans.batch_no','locations.location_name','bundles.size','bundles.id','bundles.number_sequence', 'job_card_bundles.original_quantity as qty','cut_plans.special_remark','cut_plans.cut_no as cudId')
            ->orderBy('bundles.size','ASC')
            ->orderBy('bundles.id','ASC')
            ->where('job_card_bundles.job_card_id', $job_card_id)
            ->distinct('bundles.id')
            ->get();
    $size ="";
    $qty =0;
    $size_wise_qty = [];
    $index = 0;
    foreach($details_rec as $rec){
      if($size != "" && $size != $rec->size){
        $size_wise_qty[$index] = ["size"=>$size,"qty"=>$qty];
        $index++;
        $size = $rec->size;
        $qty = $rec->qty;
      }else{
        $size = $rec->size;
        $qty += intval($rec->qty);
      }
    }
    $size_wise_qty[$index] = ['size'=>$size,'qty'=>$qty];

    $header['sizewise'] = $size_wise_qty;
    $data = ['details_rec' => $details_rec, 'header' => [$header]];
    //return $data;
    $pdf = PDF::loadView('print.trimsreport', $data);
    $pdf->setPaper('A4', 'landscape');
    return $pdf->stream('trims_report_' . date('Y_m_d_H_i_s') . '.pdf');


  }

  public function getJobcards(){
    $jobCards = DB::table('job_cards')
    ->select('id')
    //->groupByRaw('job_cards.id')
    //s->where('job_card_bundles.job_card_id', $job_card_id)
    ->get();

    return $jobCards;

  }

  public function printBundleStickerReport($job_card_id,$fpo_id,$soc_id){

    $fpo_soc = DB::table('fpos')
    ->select('fpos.wfx_fpo_no','socs.*','styles.routing_id')
    ->join('socs', 'socs.id', '=', 'fpos.soc_id')
    ->join('styles', 'styles.id', '=', 'socs.style_id')
    ->where('fpos.wfx_fpo_no', $fpo_id)
    ->where('fpos.soc_id', $soc_id)
    ->get();

    $bundle = DB::table('job_card_bundles')
    ->select('bundles.id as id','bundles.size','bundles.quantity', 'bundles.number_sequence','routing_operations.operation_code','bundle_tickets.direction','bundle_tickets.id as ticket_id')
    ->join('bundles', 'bundles.id', '=', 'job_card_bundles.bundle_id')
    ->join('bundle_tickets', 'bundle_tickets.bundle_id', '=', 'bundles.id')
    ->join('fpo_operations', 'fpo_operations.id', '=', 'bundle_tickets.fpo_operation_id')
    ->join('routing_operations', 'routing_operations.id', '=', 'fpo_operations.routing_operation_id')
    ->orderBy('bundles.id','ASC')
    ->orderBy('routing_operations.wfx_seq','ASC')
    ->orderBy('ticket_id','ASC')
    ->orderBy('bundle_tickets.direction','ASC')
    ->where('job_card_bundles.job_card_id', $job_card_id)
    // ->where('routing_operations.routing_id', $fpo_soc[0]->routing_id)
    ->get();

    $data = ['fpo_soc' => $fpo_soc, 'bundle' => $bundle, 'job_card' => $job_card_id];

    $pdf = PDF::loadView('print.bundlereport', $data);
    return $pdf->stream('bundle_report_' . date('Y_m_d_H_i_s') . '.pdf');
  }

  public static function getCurrentSlotDate($date){
    $currentDate = [];

    $currentDate = DB::table('daily_shifts')
        ->select('daily_shifts.current_date as current_date')
        ->where('daily_shifts.start_date_time' , '<=' ,$date)
        ->where('daily_shifts.end_date_time' , '>=', $date)
        ->get();

        print_r("B");
    if(sizeof($currentDate) == 0){
//        print_r("Returning considering date");
        $considering_date = "";

        $date12 = substr($date , 0 , 11)."00:00:00";
        $date530 = substr($date , 0 , 11)."05:30:00";

        $gotTime = strtotime($date);
        $time12 = strtotime($date12);
        $time530 = strtotime($date530);

        if($time12 <= $gotTime && $gotTime <= $time530){
            $xx = strtotime('-1 day' , strtotime($date));
            $considering_date = "20".date('y-m-d',$xx);
        }
        else{
            $considering_date = substr($date , 0 , 10);
        }

        $recsForDate = DB::table('daily_shifts')
            ->select('daily_shifts.*')
            ->where('current_date', '=' , $considering_date)
            ->get();

        if(sizeof($recsForDate)>0){
            return $considering_date;
        }
        else{
            return null;
        }
    }
    else{
//        print_r("Returning current date");

        return $currentDate[0]->current_date;
    }
  }

  public function changeTeam($request){
      try{
          DB::beginTransaction();

          $model = JobCard::findOrFail($request->jobId);
          $rec[] = null;
          $rec['team_id'] = $request->teamId;

          if($model->status == "Open" || $model->status == "Finalized" || $model->status == "Issued" ){
              $model->update($rec);
              DB::commit();
              return response()->json(
                  [
                      'status' => 'success'
                  ],
                  200
              );
          }
          else{
              throw new \App\Exceptions\GeneralException("Job Card is In Progress. Cannot change the team.");
          }

      }catch (Exception $e) {
          DB::rollBack();
          throw $e;
      }
  }

}
