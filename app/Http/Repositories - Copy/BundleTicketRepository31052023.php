<?php

namespace App\Http\Repositories;

use App\Bundle;
use App\BundleBin;
use App\BundleTicket;
use App\BundleTicketSecondary;
use App\Console\Commands\GenerateStubs;
use App\CutUpdate;
use App\DailyScanningSlot;
use App\EditBundleLog;
use App\Fpo;
use App\FpoCutPlan;
use App\Fppo;
use App\DailyShiftTeam;
use App\DailyTeamSlotTarget;
use App\Exceptions\GeneralException;
use App\FpoOperation;
use App\Http\Repositories\QcRejectRepository;
use App\Http\Resources\BundleTicketResource;
// use App\HashStore;
use App\JobCardBundle;
use App\PackingListDetail;
use App\PackingListSoc;
use App\QcRejectReason;
use App\Soc;
use Illuminate\Support\Facades\Validator;
use App\Http\Resources\BundleTicketWithParentsResource;
use Exception;
use App\Http\Repositories\BundleRepository;

use App\Http\Validators\BundleTicketCreateValidator;
use App\Http\Validators\BundleTicketUpdateValidator;
use App\JobCard;
use App\QcRecoverable;
use App\QcReject;
use App\Routing;
use App\RoutingOperation;
use Carbon\Carbon;
use Doctrine\DBAL\Types\JsonType;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use PHPUnit\Util\Json;

class BundleTicketRepository
{
    public function show(BundleTicket $bundleTicket)
    {
        return response()->json(
            [
                'status' => 'success',
                'data' => new BundleTicketWithParentsResource($bundleTicket),
            ],
            200
        );
    }

    public static function createRec(array $rec)
    {
        $validator = Validator::make(
            $rec,
            BundleTicketCreateValidator::getCreateRules()
        );
        if ($validator->fails()) {
            throw new Exception($validator->errors());
        }
        try {
            $model = BundleTicket::create($rec);
        } catch (Exception $e) {
            throw new \App\Exceptions\GeneralException($e->getMessage());
        }
        return $model;
    }

    public static function updateRec($model_id, array $rec)
    {
        $model = BundleTicket::findOrFail($model_id);

        Utilities::hydrate($model, $rec);
        $validator = Validator::make(
            $rec,
            BundleTicketUpdateValidator::getUpdateRules($model_id)
        );
        if ($validator->fails()) {
            throw new Exception($validator->errors());
        }

        if ($rec['scan_quantity'] > $model->original_quantity) {
            throw new Exception('Scanned quanity exceeds bundle quantity');
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
        BundleTicket::destroy($recs);
    }

    public function bubbleSort($arr)
    {
//        print_r($arr);
        try {
            $n = sizeof($arr);
            for ($i = 0; $i < $n; $i++) {
                for ($j = 0; $j < $n - $i - 1; $j++) {
                    if ($arr[$j]->fpo_operation->routing_operation->wfx_seq > $arr[$j + 1]->fpo_operation->routing_operation->wfx_seq) {
                        $t = $arr[$j];
                        $arr[$j] = $arr[$j + 1];
                        $arr[$j + 1] = $t;
                    }
                }
            }
            return $arr;
        }
        catch (Exception $e){
            throw new Exception("Problem in sorting");
        }
    }

    public function scanByScanningSlotOld($bundle_ticket_id, $daily_scanning_slot_id, $daily_shift_team_id,$packing_list_id,$scan_date_time, $user_id)
    {
        try {
            DB::beginTransaction();



            if (!(isset($daily_scanning_slot_id))) {
                throw new Exception('Scanning Slot Information is not Received');
            }

            if (!(isset($daily_shift_team_id))) {
                throw new Exception('Daily Team Information is not Received');
            }

            $bundle_ticket = BundleTicket::find($bundle_ticket_id);

            if($bundle_ticket->fpo_operation->operation == "PK" && $bundle_ticket->direction == "OUT"){
                throw new Exception('Packing out tickets are not allowed to scan here');
            }

            if (!isset($bundle_ticket)) {
                throw new Exception("Bunldle Ticket does not exist.");
            }
            if ($bundle_ticket->direction == "OUT" && $bundle_ticket->fpo_operation->operation == "PK") {
                //throw new Exception("Packing Out Barcode Ticket Not Allow to Scan");
                throw new Exception("Scan Error");
            }

            // $bundle_details = DB::table('job_card_bundles')
            //                     ->select('')
            //                     ->join('fpos', 'fpos.id','=','fpo_operations.fpo_id')
            //                     ->join('fpo_operations', 'fpo_operations.id','=','bundle_tickets.fpo_operation_id')
            //                     ->join('bundles', 'bundles.id','=','bundle_tickets.bundle_id')
            //                     ->join('fpos', 'fpos.id','=','fpo_operations.fpo_id')
            //                     ->join('daily_shift_teams', 'daily_shift_teams.id','=','bundle_ticket_secondaries.daily_shift_team_id')
            //                     ->join('daily_scanning_slots', 'daily_scanning_slots.id','=','bundle_ticket_secondaries.daily_scanning_slot_id')
            //                     ->where('job_card_bundles.bundle_id', $daily_shift_team)
            //                     ->where('bundle_ticket_secondaries.daily_scanning_slot_id', $daily_scanning_slot)
            //                     ->orderby('bundle_ticket_secondaries.scan_date_time','DESC')
            //                     ->get();

            $Jobcard_rout = JobCardBundle::where('bundle_id',$bundle_ticket->bundle_id)->first();
            $rout = $Jobcard_rout->job_card->fpo->soc->style->routing_id;


            ///////////////////////   validate Previous Operation   ///////////////////
            $all_tickets = BundleTicket::where('bundle_id',$bundle_ticket->bundle_id)->get();
            $op_seq = $bundle_ticket->fpo_operation->routing_operation->wfx_seq;
            foreach($all_tickets as $rec){
                $seq = $rec->fpo_operation->routing_operation->wfx_seq;

                if($rec->fpo_operation->routing_operation->routing_id == $rout){
                    if(is_null($rec->scan_quantity) && $op_seq > $seq){
                        $op =  $rec->fpo_operation->routing_operation->operation_code." - ".$rec->direction;
                        if(substr($op,0,1) !== "C" && substr($op,0,1) !== "E"){
                            throw new Exception("Previous Operation Not Scanned ".$op."");
                        }

                    }
                }

            }

            $this->__validateScan($daily_scanning_slot_id, $daily_shift_team_id);
            $this->_validateScan($bundle_ticket, $daily_shift_team_id);

            if (($bundle_ticket->scan_quantity >= 0) && (!is_null($bundle_ticket->scan_quantity))) {
                if ($bundle_ticket->original_quantity != $bundle_ticket->scan_quantity) {
                    $qr = QcRecoverable::where('bundle_ticket_id', $bundle_ticket_id)->first();
                    if (!is_null($qr)) {
                        $rec_qty = is_null($qr->recovered_quantity) ? 0 : $qr->recovered_quantity;
                        if (($rec_qty != $qr->recoverable_quantity) && ($qr->recoverable_quantity != 0)) {

                            $bundle_ticket->update([
                                'scan_quantity' => $bundle_ticket->scan_quantity + $qr->recoverable_quantity,
                                'packing_list_id'=>$packing_list_id,
                                'updated_at' => now('Asia/Kolkata'),
                                "scan_date_time" => now('Asia/Kolkata'),
                                'updated_by' => $user_id
                            ]);

                            $qr->update([
                                'updated_at' => $qr->updated_at,
                                'recovered_quantity' => $qr->recoverable_quantity,
                                'recoverable_quantity' => 0
                            ]);
                            $bt_secondary = BundleTicketSecondary::insert([
                                'bundle_ticket_id' => $bundle_ticket->id,
                                'daily_scanning_slot_id' => $daily_scanning_slot_id,
                                'daily_shift_team_id' => $daily_shift_team_id,
                                'packing_list_id' => $packing_list_id,
                                'bundle_id' => $bundle_ticket->bundle_id,
                                'original_quantity' => $bundle_ticket->original_quantity,
                                "scan_quantity" => $bundle_ticket->original_quantity,
                                "scan_date_time" => now('Asia/Kolkata'),
                                'created_by' => $user_id,
                                'updated_by' => $user_id,
                                'updated_at' => now('Asia/Kolkata'),
                                "created_at" => now('Asia/Kolkata')
                            ]);
                        } else {
                            throw new Exception("Bundle Ticket already scanned");
                        }
                    } else {
                        throw new Exception("No Recoverable Bundles to scan.");
                    }
                } else {
                    throw new Exception("Bundle Ticket already scanned");
                }
            } else {
                $bundle_ticket->update([
                    'scan_quantity' => $bundle_ticket->original_quantity,
                    'scan_date_time' => now('Asia/Kolkata'),
                    'daily_scanning_slot_id' => $daily_scanning_slot_id,
                    'daily_shift_team_id' => $daily_shift_team_id,
                    'packing_list_id'=>$packing_list_id,
                    'updated_by' => $user_id,
                    'updated_at' => now('Asia/Kolkata')
                ]);

                $bt_secondary = BundleTicketSecondary::insert([
                    'bundle_ticket_id' => $bundle_ticket->id,
                    'daily_scanning_slot_id' => $daily_scanning_slot_id,
                    'daily_shift_team_id' => $daily_shift_team_id,
                    'packing_list_id' => $packing_list_id,
                    'bundle_id' => $bundle_ticket->bundle_id,
                    'original_quantity' => $bundle_ticket->original_quantity,
                    "scan_quantity" => $bundle_ticket->original_quantity,
                    "scan_date_time" => now('Asia/Kolkata'),
                    'created_by' => $user_id,
                    'updated_by' => $user_id,
                    'updated_at' => now('Asia/Kolkata'),
                    "created_at" => now('Asia/Kolkata')
                ]);

                $this->_handleJobCardStatus($bundle_ticket);
            }

            /////////////////////////////  Update Bundle By Packing List  ////////////////
            DB::table('bundle_tickets')
                ->where('bundle_id', $bundle_ticket->bundle_id)
                ->update(["packing_list_id"=>$packing_list_id, "updated_by"=>$user_id, 'updated_at' => now('Asia/Kolkata')]);
            /////////////////////////////////////////////////////////////////////////////

            $this->_calculateTargetInformationSetup($bundle_ticket, 'ADD');

            DB::commit();
            return response()->json(["status" => "success"], 200);
        } catch (Exception $e) {
            DB::rollBack();
            throw new \App\Exceptions\GeneralException($e->getMessage());
        }
    }


    public function scanByScanningSlot($bundle_ticket_id, $daily_scanning_slot_id, $daily_shift_team_id,$packing_list_id,$scan_date_time, $user_id)
    {
        try {
            DB::beginTransaction();


            $dailyShiftTeam = DB::table('daily_shift_teams')
                ->select('*')
                ->where('id', '=', $daily_shift_team_id)
                ->get();

//            $nowDate = new \DateTime("now");
            $nowDateYMD = $dailyShiftTeam[0]->current_date;// $nowDate->format("Y-m-d");
            $receivedDate = new \DateTime($scan_date_time);
            $receivedDateYMD = $receivedDate->format("Y-m-d");

            if($nowDateYMD > $receivedDateYMD){
                throw new Exception("Scanning is not allowed for backdates.");
            }

            $bundle_ticket = BundleTicket::find($bundle_ticket_id);

            $teamValidationStatus = $this->getTeamValidationInfo($bundle_ticket_id, $daily_shift_team_id);

            if(!$teamValidationStatus){
                throw new Exception("The entered team does not match with the team of the Job Card.");
            }

            if (!(isset($daily_scanning_slot_id))) {
                throw new Exception('Scanning Slot Information is not Received');
            }

            if (!(isset($daily_shift_team_id))) {
                throw new Exception('Daily Team Information is not Received');
            }

            $nnn = $bundle_ticket->scan_quantity;
            if($bundle_ticket->fpo_operation->operation == "PK" && $bundle_ticket->direction == "OUT"){
                throw new Exception('Packing out tickets are not allowed to scan here');
            }
            if (!isset($bundle_ticket)) {
                throw new Exception("Bundle Ticket does not exist.");
            }

            ///////////////////////   validate Previous Operation   ///////////////////
            $all_tickets = BundleTicket::where('bundle_id',$bundle_ticket->bundle_id)->get();

            $xxx = $this->bubbleSort($all_tickets);
            $previousOp = $this->findPreviousOp($bundle_ticket_id);

            $n = sizeof($xxx);
            for ($i = 0; $i < $n; $i++){

                if($xxx[$i]->scan_quantity == null && $xxx[$i]->id === $bundle_ticket->id) {
                    if ($xxx[$i]->direction == "OUT" && substr($xxx[$i]->fpo_operation->routing_operation->operation_code, 0, 2) == "SW") {
                        throw new Exception("SW - OUT tickets are not allowed to scan.");
                    }
                }
            }

            if($previousOp === null){
                throw new Exception("Unable to find the scan quantity of previous operation. Please contact a system administrator");
            }

            $qcRejectsPrev = QcReject::where('bundle_ticket_id' , $previousOp->id);
            $totalRejPrev = 0;

            $qcRejCurrent = DB::table('qc_rejects')
                ->where('bundle_ticket_id', '=', $bundle_ticket->id)
                ->get();
            $totalRej = 0;

            foreach ($qcRejCurrent as $q){
                $totalRej += $q->quantity;
            }


            foreach ($qcRejectsPrev as $q){
                $totalRejPrev += $q->quantity;
            }

            if(($previousOp->scan_quantity) <= 0){
                throw new Exception("Scanning Quantity cannot be zero (0)");
            }

            $Jobcard_rout = JobCardBundle::where('bundle_id',$bundle_ticket->bundle_id)->first();
            $rout = $Jobcard_rout->job_card->fpo->soc->style->routing_id;
            $op_seq = $bundle_ticket->fpo_operation->routing_operation->wfx_seq;
            foreach($all_tickets as $rec){
                $seq = $rec->fpo_operation->routing_operation->wfx_seq;
                if($rec->fpo_operation->routing_operation->routing_id == $rout) {
                    if (is_null($rec->scan_quantity) && $op_seq > $seq) {
                        $op = $rec->fpo_operation->routing_operation->operation_code . " - " . $rec->direction;

                        if (substr($op, 0, 1) === "C" || substr($op, 0, 1) === "E") {

                        } else if (substr($op, 0, 2) === "SW" && $rec->direction === "OUT") {
                            $swOutOfBundle = $rec;
                        } else {
                            throw new Exception("Previous Operation Not Scanned " . $op . "");
                        }
                    }
                }
            }

            $this->__validateScan($daily_scanning_slot_id, $daily_shift_team_id);
            $this->_validateScan($bundle_ticket, $daily_shift_team_id);

            if (($bundle_ticket->scan_quantity >= 0) && (!is_null($bundle_ticket->scan_quantity))) { // && ($bundle_ticket->scan_quantity != $totalRej)
                if ($bundle_ticket->original_quantity != $bundle_ticket->scan_quantity) {
                    $qr = QcRecoverable::where('bundle_ticket_id', $bundle_ticket_id)->first();
                    if (!is_null($qr)) {
                        $rec_qty = is_null($qr->recovered_quantity) ? 0 : $qr->recovered_quantity;
                        if (($rec_qty != $qr->recoverable_quantity) && ($qr->recoverable_quantity != 0)) {

                            $bundle_ticket->update([
                                'scan_quantity' => $previousOp->scan_quantity  + $qr->recoverable_quantity -$totalRej,
                                'packing_list_id'=>$packing_list_id,
                                'updated_by' => $user_id,
                                'updated_at' => now('Asia/Kolkata')
                            ]);

                            $qr->update([
                                'updated_at' => $qr->updated_at,
                                'recovered_quantity' => $qr->recoverable_quantity,
                                'recoverable_quantity' => 0
                            ]);

                            $bt_secondary = BundleTicketSecondary::insert([
                                'bundle_ticket_id' => $bundle_ticket->id,
                                'daily_scanning_slot_id' => $daily_scanning_slot_id,
                                'daily_shift_team_id' => $daily_shift_team_id,
                                'packing_list_id' => $packing_list_id,
                                'bundle_id' => $bundle_ticket->bundle_id,
                                'original_quantity' => $bundle_ticket->original_quantity,
                                "scan_quantity" => $previousOp->scan_quantity + $qr->recoverable_quantity -$totalRej,
                                "scan_date_time" => now('Asia/Kolkata'),
                                'created_by' => $user_id,
                                'updated_by' => $user_id,
                                'created_at' => now('Asia/Kolkata'),
                                'updated_at' => now('Asia/Kolkata')
                            ]);
                        } else {
                            throw new Exception("Bundle Ticket already scanned");
                        }
                    } else {
//                        throw new Exception("This id is partially scanned. Please use the Change Quantity option.");
                    }
                } else {
                    throw new Exception("Bundle Ticket already scanned");
                }

                $bt_secondary = BundleTicketSecondary::insert([
                    'bundle_ticket_id' => $bundle_ticket->id,
                    'daily_scanning_slot_id' => $daily_scanning_slot_id,
                    'daily_shift_team_id' => $daily_shift_team_id,
                    'packing_list_id' => $packing_list_id,
                    'bundle_id' => $bundle_ticket->bundle_id,
                    'original_quantity' => $bundle_ticket->original_quantity,
                    "scan_quantity" => $previousOp->scan_quantity - $nnn -$totalRej,
                    "scan_date_time" => now('Asia/Kolkata'),
                    'created_by' => $user_id,
                    'updated_by' => $user_id,
                    'created_at' => now('Asia/Kolkata'),
                    'updated_at' => now('Asia/Kolkata')
                ]);

                $bundle_ticket->update([
                    'scan_quantity' => $previousOp->scan_quantity -$totalRej,
                    'scan_date_time' => now('Asia/Kolkata'),
                    'daily_scanning_slot_id' => $daily_scanning_slot_id,
                    'daily_shift_team_id' => $daily_shift_team_id,
                    'packing_list_id'=>$packing_list_id,
                    'updated_by' => $user_id,
                    'updated_at' => now('Asia/Kolkata')
                ]);

            } else {
                $bundle_ticket->update([
                    'scan_quantity' => $previousOp->scan_quantity -$totalRej,
                    'scan_date_time' => now('Asia/Kolkata'),
                    'daily_scanning_slot_id' => $daily_scanning_slot_id,
                    'daily_shift_team_id' => $daily_shift_team_id,
                    'packing_list_id'=>$packing_list_id,
                    'updated_by' => $user_id,
                    'updated_at' => now('Asia/Kolkata')
                ]);

                $bt_secondary = BundleTicketSecondary::insert([
                    'bundle_ticket_id' => $bundle_ticket->id,
                    'daily_scanning_slot_id' => $daily_scanning_slot_id,
                    'daily_shift_team_id' => $daily_shift_team_id,
                    'packing_list_id' => $packing_list_id,
                    'bundle_id' => $bundle_ticket->bundle_id,
                    'original_quantity' => $bundle_ticket->original_quantity,
                    "scan_quantity" => $previousOp->scan_quantity -$totalRej,
                    "scan_date_time" => now('Asia/Kolkata'),
                    'created_by' => $user_id,
                    'updated_by' => $user_id,
                    'created_at' => now('Asia/Kolkata'),
                    'updated_at' => now('Asia/Kolkata')
                ]);

                $this->_handleJobCardStatus($bundle_ticket);
            }

            /////////////////////////////  Update Bundle By Packing List  ////////////////
            DB::table('bundle_tickets')
                ->where('bundle_id', $bundle_ticket->bundle_id)
                ->update(["packing_list_id"=>$packing_list_id, "updated_by"=>$user_id, 'updated_at' => now('Asia/Kolkata')]);
            /////////////////////////////////////////////////////////////////////////////

            $this->_calculateTargetInformationSetup($bundle_ticket, 'ADD');

            DB::commit();
            return response()->json(["status" => "success"], 200);
        } catch (Exception $e) {
            DB::rollBack();
            throw new \App\Exceptions\GeneralException($e->getMessage());
        }
    }

    public function scanByScanningSlotOPD($bundle_ticket_id, $daily_scanning_slot_id, $daily_shift_team_id,$packing_list_id,$scan_date_time, $user_id,$operation,$direction,$location)
    {
        try {
            DB::beginTransaction();
			$bbbbb = 0;
            $bundle_ticket = DB::table('bundle_tickets')
            ->select('bundle_tickets.*')
            ->join('fpo_operations', 'fpo_operations.id', '=' , 'bundle_tickets.fpo_operation_id')
            ->where('fpo_operations.operation', '=' , $operation)
            ->where('bundle_tickets.direction' , '=', $direction)
            ->where('bundle_tickets.bundle_id', '=', $bundle_ticket_id)
            ->first();

            $dailyShiftTeam = DB::table('daily_shift_teams')
            ->select('*')
            ->where('id', '=', $daily_shift_team_id)
            ->get();
            
            

            if(strtoupper($operation) == "FG" && strtoupper($direction) == "IN"){
                $ranala_location = DB::table('locations')->select('id')
                ->where('location_name','=','Transit')
                ->first();

                DB::table('bundles')->where('id','=',$bundle_ticket_id)->update(['location_id'=>$ranala_location->id]);
               
            }else if(strtoupper($operation) == "FG" && strtoupper($direction) == "OUT"){
                $ranala_location = DB::table('locations')->select('id')
                ->where('location_name','=','Ranala')
                ->first();

                DB::table('bundles')->where('id','=',$bundle_ticket_id)->update(['location_id'=>$ranala_location->id]);
               
            }else if(strtoupper($operation) == "SW" && strtoupper($direction) == "IN"){
                $ranala_location = DB::table('locations')->select('id')
                ->where('location_name','=','Sewing')
                ->first();

                DB::table('bundles')->where('id','=',$bundle_ticket_id)->update(['location_id'=>$ranala_location->id]);
               
            }else if(strtoupper($operation) == "PK" && strtoupper($direction) == "IN"){
                $ranala_location = DB::table('locations')->select('id')
                ->where('location_name','=','Packing')
                ->first();

                DB::table('bundles')->where('id','=',$bundle_ticket_id)->update(['location_id'=>$location,'daily_shift_id'=>$dailyShiftTeam[0]->daily_shift_id]);
               
            }else if(strtoupper($operation) == "EA" && strtoupper($direction) == "IN"){
                
                $locationObj = DB::table('locations')->select('*')
                ->where('id','=',$location)
                ->first();

                if(is_null($locationObj)){
                    throw new Exception("Please Enter Valid Location");
                }else{
                    if($locationObj->site == "EA_Send"){
                        DB::table('bundles')->where('id','=',$bundle_ticket_id)->update(['location_id'=>$location,'daily_shift_id'=>$dailyShiftTeam[0]->daily_shift_id]);
                    }else{
                        throw new Exception("Target Site Validation Fail");
                    }
                }

                
               
            }else if(strtoupper($operation) == "EA" && strtoupper($direction) == "OUT"){
                $locationObj = DB::table('locations')->select('*')
                ->where('id','=',$location)
                ->first();

                if(is_null($locationObj)){
                    throw new Exception("Please Enter Valid Location");
                }else{
                    if($locationObj->site == "EA_Send" ||  $locationObj->site == "EA_Ready_To_Send" ||  $locationObj->site == "Move_To_Inspection"){
                        throw new Exception("Target Site Validation Fail");
                        
                    }else{
                        DB::table('bundles')->where('id','=',$bundle_ticket_id)->update(['location_id'=>$location]);  
                    }
                }              
            }

            
            $bbbbb = $bundle_ticket_id;
            $bundle_ticket_id = $bundle_ticket->id;


            
//            $nowDate = new \DateTime("now");
            $nowDateYMD = $dailyShiftTeam[0]->current_date;// $nowDate->format("Y-m-d");
            $receivedDate = new \DateTime($scan_date_time);
            $receivedDateYMD = $receivedDate->format("Y-m-d");
            
            if($nowDateYMD > $receivedDateYMD){
                throw new Exception("Scanning is not allowed for backdates.");
            }
            
            $bundle_ticket = BundleTicket::find($bundle_ticket_id);
            
            $teamValidationStatus =true;
            if(strtoupper($operation) != "EA"){
                
                $teamValidationStatus = $this->getTeamValidationInfo($bundle_ticket_id, $daily_shift_team_id);
                
            }
            if(!$teamValidationStatus && !($operation == "FG")){
                throw new Exception("The entered team does not match with the team of the Job Card.");
            }

            if (!(isset($daily_scanning_slot_id))) {
                throw new Exception('Scanning Slot Information is not Received');
            }

            if (!(isset($daily_shift_team_id))) {
                throw new Exception('Daily Team Information is not Received');
            }

            $nnn = $bundle_ticket->scan_quantity;
            if($bundle_ticket->fpo_operation->operation == "PK" && $bundle_ticket->direction == "OUT"){
                throw new Exception('Packing out tickets are not allowed to scan here');
            }
            if (!isset($bundle_ticket)) {
                throw new Exception("Bundle Ticket does not exist.");
            }
            
            ///////////////////////   validate Previous Operation   ///////////////////
            $all_tickets = BundleTicket::where('bundle_id',$bundle_ticket->bundle_id)->get();

            $xxx = $this->bubbleSort($all_tickets);
            $previousOp = $this->findPreviousOp($bundle_ticket_id);

            $n = sizeof($xxx);
            for ($i = 0; $i < $n; $i++){

                if($xxx[$i]->scan_quantity == null && $xxx[$i]->id === $bundle_ticket->id) {
                    if ($xxx[$i]->direction == "OUT" && substr($xxx[$i]->fpo_operation->routing_operation->operation_code, 0, 2) == "SW") {
                        throw new Exception("SW - OUT tickets are not allowed to scan.");
                    }
                }
            }

            if($previousOp === null){
                throw new Exception("Unable to find the scan quantity of previous operation. Please contact a system administrator");
            }

            $qcRejectsPrev = QcReject::where('bundle_ticket_id' , $previousOp->id);
            $totalRejPrev = 0;

            $qcRejCurrent = DB::table('qc_rejects')
                ->where('bundle_ticket_id', '=', $bundle_ticket->id)
                ->get();
            $totalRej = 0;

            foreach ($qcRejCurrent as $q){
                $totalRej += $q->quantity;
            }


            foreach ($qcRejectsPrev as $q){
                $totalRejPrev += $q->quantity;
            }

            if(($previousOp->scan_quantity) <= 0){
                throw new Exception("Scanning Quantity cannot be zero (0)");
            }
            
            // $Jobcard_rout = JobCardBundle::where('bundle_id',$bundle_ticket->bundle_id)->first();
            // $rout = $Jobcard_rout->job_card->fpo->soc->style->routing_id;
            
            $style_route = DB::table('bundles')
			->select('styles.routing_id')
            ->join('fpo_cut_plans','fpo_cut_plans.fppo_id','=','bundles.fppo_id')
            ->join('fpos','fpos.id','=','fpo_cut_plans.fpo_id')
            ->join('socs','socs.id','=','fpos.soc_id')
            ->join('styles','styles.id','=','socs.style_id')
			->where('bundles.id', '=', $bbbbb)
            ->first();

            $rout  = $style_route ->routing_id;
			//print_r($rout);
			//return $rout;
            
            $op_seq = $bundle_ticket->fpo_operation->routing_operation->wfx_seq;
            foreach($all_tickets as $rec){
                $seq = $rec->fpo_operation->routing_operation->wfx_seq;
				//print_r($rec->fpo_operation->routing_operation->routing_id);
                if($rec->fpo_operation->routing_operation->routing_id == $rout) {
                    if (is_null($rec->scan_quantity) && $op_seq > $seq) {
                        $op = $rec->fpo_operation->routing_operation->operation_code . " - " . $rec->direction;

                        if (substr($op, 0, 1) === "C" || substr($op, 0, 1) === "E") {

                        } else if (substr($op, 0, 2) === "SW" && $rec->direction === "OUT") {
                            $swOutOfBundle = $rec;
                        } else {
                            throw new Exception("Previous Operation Not Scanned " . $op . "");
                        }
                    }
                }else{
                    throw new Exception("Different Route Code ");
                }
                
            }
            
            $this->__validateScan($daily_scanning_slot_id, $daily_shift_team_id);
            $this->_validateScan($bundle_ticket, $daily_shift_team_id);
            
            if (($bundle_ticket->scan_quantity >= 0) && (!is_null($bundle_ticket->scan_quantity))) { // && ($bundle_ticket->scan_quantity != $totalRej)
                if ($bundle_ticket->original_quantity != $bundle_ticket->scan_quantity) {
                    $qr = QcRecoverable::where('bundle_ticket_id', $bundle_ticket_id)->first();
                    if (!is_null($qr)) {
                        $rec_qty = is_null($qr->recovered_quantity) ? 0 : $qr->recovered_quantity;
                        if (($rec_qty != $qr->recoverable_quantity) && ($qr->recoverable_quantity != 0)) {

                            $bundle_ticket->update([
                                'scan_quantity' => $previousOp->scan_quantity  + $qr->recoverable_quantity -$totalRej,
                                'packing_list_id'=>$packing_list_id,
                                'updated_by' => $user_id,
                                'updated_at' => now('Asia/Kolkata')
                            ]);

                            $qr->update([
                                'updated_at' => $qr->updated_at,
                                'recovered_quantity' => $qr->recoverable_quantity,
                                'recoverable_quantity' => 0
                            ]);

                            $bt_secondary = BundleTicketSecondary::insert([
                                'bundle_ticket_id' => $bundle_ticket->id,
                                'daily_scanning_slot_id' => $daily_scanning_slot_id,
                                'daily_shift_team_id' => $daily_shift_team_id,
                                'packing_list_id' => $packing_list_id,
                                'bundle_id' => $bundle_ticket->bundle_id,
                                'original_quantity' => $bundle_ticket->original_quantity,
                                "scan_quantity" => $previousOp->scan_quantity + $qr->recoverable_quantity -$totalRej,
                                "scan_date_time" => now('Asia/Kolkata'),
                                'created_by' => $user_id,
                                'updated_by' => $user_id,
                                'created_at' => now('Asia/Kolkata'),
                                'updated_at' => now('Asia/Kolkata')
                            ]);
                        } else {
                            throw new Exception("Bundle Ticket already scanned");
                        }
                    } else {
//                        throw new Exception("This id is partially scanned. Please use the Change Quantity option.");
                    }
                } else {
                    throw new Exception("Bundle Ticket already scanned");
                }
                
                $bt_secondary = BundleTicketSecondary::insert([
                    'bundle_ticket_id' => $bundle_ticket->id,
                    'daily_scanning_slot_id' => $daily_scanning_slot_id,
                    'daily_shift_team_id' => $daily_shift_team_id,
                    'packing_list_id' => $packing_list_id,
                    'bundle_id' => $bundle_ticket->bundle_id,
                    'original_quantity' => $bundle_ticket->original_quantity,
                    "scan_quantity" => $previousOp->scan_quantity - $nnn -$totalRej,
                    "scan_date_time" => now('Asia/Kolkata'),
                    'created_by' => $user_id,
                    'updated_by' => $user_id,
                    'created_at' => now('Asia/Kolkata'),
                    'updated_at' => now('Asia/Kolkata')
                ]);

                $bundle_ticket->update([
                    'scan_quantity' => $previousOp->scan_quantity -$totalRej,
                    'scan_date_time' => now('Asia/Kolkata'),
                    'daily_scanning_slot_id' => $daily_scanning_slot_id,
                    'daily_shift_team_id' => $daily_shift_team_id,
                    'packing_list_id'=>$packing_list_id,
                    'updated_by' => $user_id,
                    'updated_at' => now('Asia/Kolkata')
                ]);

            } else {
                
                $bundle_ticket->update([
                    'scan_quantity' => $previousOp->scan_quantity -$totalRej,
                    'scan_date_time' => now('Asia/Kolkata'),
                    'daily_scanning_slot_id' => $daily_scanning_slot_id,
                    'daily_shift_team_id' => $daily_shift_team_id,
                    'packing_list_id'=>$packing_list_id,
                    'updated_by' => $user_id,
                    'updated_at' => now('Asia/Kolkata')
                ]);
                
                $bt_secondary = BundleTicketSecondary::insert([
                    'bundle_ticket_id' => $bundle_ticket->id,
                    'daily_scanning_slot_id' => $daily_scanning_slot_id,
                    'daily_shift_team_id' => $daily_shift_team_id,
                    'packing_list_id' => $packing_list_id,
                    'bundle_id' => $bundle_ticket->bundle_id,
                    'original_quantity' => $bundle_ticket->original_quantity,
                    "scan_quantity" => $previousOp->scan_quantity -$totalRej,
                    "scan_date_time" => now('Asia/Kolkata'),
                    'created_by' => $user_id,
                    'updated_by' => $user_id,
                    'created_at' => now('Asia/Kolkata'),
                    'updated_at' => now('Asia/Kolkata')
                ]);

                if(strtoupper($operation) != "EA"){
                    $this->_handleJobCardStatus($bundle_ticket);
                }
                
            }

            /////////////////////////////  Update Bundle By Packing List  ////////////////
            DB::table('bundle_tickets')
                ->where('bundle_id', $bundle_ticket->bundle_id)
                ->update(["packing_list_id"=>$packing_list_id, "updated_by"=>$user_id, 'updated_at' => now('Asia/Kolkata')]);
            /////////////////////////////////////////////////////////////////////////////

            $this->_calculateTargetInformationSetup($bundle_ticket, 'ADD');

            DB::commit();
            return response()->json(["status" => "success"], 200);
        } catch (Exception $e) {
            DB::rollBack();
            throw new \App\Exceptions\GeneralException($e->getMessage());
        }
    }

    private function _calculateTargetInformationSetup($bundle_ticket, $operation, $qty = null)
    {
        $op = $bundle_ticket->fpo_operation->routing_operation->operation_code . "-" . $bundle_ticket->direction;

        if ($op == "SW100001-OUT") {

            $first_slot = DailyTeamSlotTarget::where(['daily_shift_team_id' => $bundle_ticket->daily_shift_team_id, 'seq_no' => 1])->first();
            if (is_null($first_slot) && ($bundle_ticket->daily_scanning_slot->seq_no != 1)) {
                throw new \App\Exceptions\GeneralException("Bundle Tickets belonging to first slot has not been scanned. Cannot proceed.");
            }

            $daily_team_slot_target = DailyTeamSlotTarget::where([
                'daily_shift_team_id' => $bundle_ticket->daily_shift_team_id,
                'daily_scanning_slot_id' => $bundle_ticket->daily_scanning_slot_id
            ])->first();

            switch ($operation) {
                case 'ADD':
                    $new_actual = $bundle_ticket->scan_quantity + (is_null($daily_team_slot_target->actual) ? 0 : $daily_team_slot_target->actual);
                    break;
                case 'MOD':
                    $new_actual = $daily_team_slot_target->actual - ($bundle_ticket->scan_quantity - $qty);
                    break;
                case 'DEL':
                    $new_actual = $daily_team_slot_target->actual - ($bundle_ticket->scan_quantity);
                    break;
                default:
                    # code...
                    break;
            }
            if($new_actual < 0){
                $new_actual = 0;
            }
            DailyTeamSlotTargetRepository::updateRec($daily_team_slot_target->id, ['actual' => ($new_actual == 0 ? null : $new_actual),'actual_smv'=>$bundle_ticket->fpo_operation->routing_operation->smv]);

            $dts_targets = DailyTeamSlotTarget::where('daily_shift_team_id', $daily_team_slot_target->daily_shift_team_id)
                ->where('seq_no', '>', $daily_team_slot_target->seq_no)->get();

            $no_of_slots = sizeof($dts_targets);

            // if ($no_of_slots > 0) {
            //   $balance =  $daily_team_slot_target->revised - $new_actual;
            //   $quotient = ceil($balance / $no_of_slots);
            //   $remainder = $balance - ($quotient * $no_of_slots);
            //   $run = 1;
            //   foreach ($dts_targets as $dts_target) {
            //     if ($run == $no_of_slots) {
            //       if ($dts_target->planned != $dts_target->forecast) {
            //         $revised = $dts_target->forecast + $quotient + $remainder;
            //       } else {
            //         $revised = $dts_target->revised + $quotient + $remainder;
            //       }
            //     } else {
            //       if ($dts_target->planned != $dts_target->forecast) {
            //         $revised = $dts_target->forecast + $quotient;
            //       } else {
            //         $revised = $dts_target->+ + $quotient;
            //       }
            //     }
            //     DailyTeamSlotTargetRepository::updateRec($dts_target->id, ['revised' => $revised]);
            //     $run++;
            //   }
            // }



            /*
            $daily_team_slot_target = DailyTeamSlotTarget::where([
              'daily_shift_team_id' => $bundle_ticket->daily_shift_team_id,
              'daily_scanning_slot_id' => $bundle_ticket->daily_scanning_slot_id
            ])->first();

            switch ($operation) {
              case 'ADD':
                $new_actual = $bundle_ticket->scan_quantity + (is_null($daily_team_slot_target->actual) ? 0 : $daily_team_slot_target->actual);
                break;
              case 'MOD':
                $new_actual = $daily_team_slot_target->actual - ($bundle_ticket->scan_quantity - $qty);
                break;
              case 'DEL':
                $new_actual = $daily_team_slot_target->actual - ($bundle_ticket->scan_quantity);
                break;
              default:
                # code...
                break;
            }

            DailyTeamSlotTargetRepository::updateRec($daily_team_slot_target->id, ['actual' => ($new_actual == 0 ? null : $new_actual)]);

            $dtst = DailyTeamSlotTarget::where('daily_shift_team_id', $daily_team_slot_target->daily_shift_team_id)
              ->where('seq_no', '>', $daily_team_slot_target->seq_no)->get();

            $base = $daily_team_slot_target->revised;

            $no_of_slots = sizeof($dtst);
            if ($no_of_slots > 0) {
              // $balance = $base - $new_actual;
              $balance = $new_actual - $daily_team_slot_target->actual;

              $quotient = ceil($balance / $no_of_slots);
              $remainder = $balance - ($quotient * $no_of_slots);
              for ($x = 0; $x < $no_of_slots; $x++) {

                $base = $dtst[$x]->revised;
                // $balance = $base - $new_actual;
                // $quotient = ceil($balance / $no_of_slots);
                // $remainder = $balance - ($quotient * $no_of_slots);

                if ($balance == 0) {
                  $dtst[$x]->revised = $dtst[$x]->forecast;
                } else {
                  if ($new_actual == 0) {
                    $dtst[$x]->revised = $base;
                  } else {
                    if ($x == ($no_of_slots - 1)) {
                      // if last slot
                      $dtst[$x]->revised = $base + $quotient + $remainder;
                    } else {
                      $dtst[$x]->revised = $base + $quotient;
                    }
                  }
                }
                DailyTeamSlotTargetRepository::updateRec($dtst[$x]->id, ['revised' => $dtst[$x]->revised]);
              }
            }
            */
        }
    }

    private function _handleJobCardStatus($bundle_ticket)
    {
        $job_card = JobCard::select('job_cards.*','fpo_operations.operation')
            ->join('job_card_bundles', 'job_card_bundles.job_card_id', '=', 'job_cards.id')
            ->join('bundles', 'job_card_bundles.bundle_id', '=', 'bundles.id')
            ->join('bundle_tickets', 'bundle_tickets.bundle_id', '=', 'bundles.id')
            ->join('fpo_operations', 'fpo_operations.id', '=', 'bundle_tickets.fpo_operation_id')
            ->where('bundle_tickets.id', $bundle_ticket->id)
            ->first();

        $op = $job_card->operation;

        $jj = JobCard::find($job_card->id);

        if (!(is_null($job_card)) && $op != "FG") {
            if (!(($job_card->status == 'Issued') || ($job_card->status == 'InProgress'))) {
                throw new Exception('Connect Job Card No - ' . $job_card->id . ' is not issued to Production. Scanning is not allowed');
            }

            $op_n_dir = $bundle_ticket->fpo_operation->routing_operation->operation_code . "-" . $bundle_ticket->direction;

            if (($job_card->status == 'Issued') && ($op_n_dir == 'SW100001-IN' || $op_n_dir == 'SP100001-IN')) {
                JobCardRepository::fsmProgress($jj);
            }
            if (($job_card->status == 'InProgress') && ($op_n_dir == 'PK100001-IN')) {
                $flag = false;
                foreach ($jj->job_card_bundles as $job_card_bundle) {
                    $job_card_bundle->load('bundle.bundle_tickets');
                    foreach ($job_card_bundle->bundle->bundle_tickets as $bt) {
                        $op_n_dir_2 = $bt->fpo_operation->routing_operation->operation_code . "-" . $bt->direction;
                        if (($op_n_dir_2 == 'PK100001-IN') && (is_null($bt->scan_quantity) || ($bt->scan_quantity == 0))) {
                            $flag = true;
                        }
                    }
                }
                if (!$flag) {
                    JobCardRepository::fsmReady($jj);
                }
            }
        }
    }

    public function _validateScan($bundle_ticket, $daily_shift_team_id)
    {
        
        function __throwException($operation_code, $direction)
        {
            throw new Exception(
                "Scan is not allowed. Complete the " .
                $operation_code .
                " - " .
                $direction .
                " Scan  to proceed"
            );
        }

        function __getOppositeDirection($dir)
        {
            switch ($dir) {
                case 'IN':
                    return 'OUT';
                case 'OUT':
                    return 'IN';
                default:
                    throw new GeneralException("Invalid routing operation direction.");
                    break;
            }
        }

        function __getPrevRoutingOperation($current_bt)
        {
            return RoutingOperation::where('routing_id', $current_bt->fpo_operation->routing_operation->routing_id)
                ->where('id', '<>', $current_bt->fpo_operation->routing_operation->id)
                ->where('shop_floor_seq', $current_bt->fpo_operation->routing_operation->shop_floor_seq - 1)
                ->first();
        }

        function __getPrevBundleTicket($bundle_ticket, $prev_routing_op)
        {
            $prev_fpo_ops = FpoOperation::where('routing_operation_id', $prev_routing_op->id)->pluck('id')->toArray();
            return BundleTicket::where(['bundle_id' => $bundle_ticket->bundle_id, 'direction' => __getOppositeDirection($bundle_ticket->direction)])
                ->whereIn('fpo_operation_id', $prev_fpo_ops)
                ->first();
        }

        function __isParentOperation($bundle_ticket)
        {
            $parent_op_id = $bundle_ticket->fpo_operation->routing_operation->parent_operation_id;
            return (is_null($parent_op_id) ? true : false);
        }

        function __getParentBundleTicket($bundle_ticket, $direction)
        {
            $parent_ro = RoutingOperation::where('routing_id', $bundle_ticket->fpo_operation->routing_operation->routing_id)
                ->where('id', $bundle_ticket->fpo_operation->routing_operation->parent_operation_id)
                ->first();
            $parent_fpo_ops = FpoOperation::where('routing_operation_id', $parent_ro->id)->pluck('id')->toArray();
            return BundleTicket::where(['bundle_id' => $bundle_ticket->bundle_id, 'direction' => $direction])
                ->whereIn('fpo_operation_id', $parent_fpo_ops)
                ->first();
        }

        function __isChildOpOutScanned($bundle_ticket, $child_ro_id)
        {

            $child_fpo_ops = FpoOperation::where('routing_operation_id', $child_ro_id)->pluck('id')->toArray();
            $child_bts = BundleTicket::where(['bundle_id' => $bundle_ticket->bundle_id, 'direction' => 'OUT'])
                ->whereIn('fpo_operation_id', $child_fpo_ops)
                ->get();
            foreach ($child_bts as $child_bt) {
                if (!($child_bt->scan_quantity > 0)) {
                    __throwException($child_bt->fpo_operation->routing_operation->operation_code, $child_bt->direction);
                }
            }
        }

        //validate teams
        $job_card = JobCard::join('job_card_bundles', 'job_card_bundles.job_card_id', '=', 'job_cards.id')
            ->join('bundles', 'job_card_bundles.bundle_id', '=', 'bundles.id')
            ->join('bundle_tickets', 'bundle_tickets.bundle_id', '=', 'bundles.id')
            ->where('bundle_tickets.id', $bundle_ticket->id)
            ->first();

        if (!(is_null($job_card))) {
            $job_card_team = $job_card->team_id;
            $scan_team = DailyShiftTeam::find($daily_shift_team_id)->team_id;

            if ($job_card_team != $scan_team) {

                //throw new Exception("Bundle is not assigned to the team");
            }
        } else {
            //throw new Exception("This Bundle is not connected to a Job Card");
        }

        /**
         * IN scan: first level
         * get current BundleTicket bundle_id, fpo_operation_id
         * find previous fpo_operation (where seq - 1)
         * find previous operation's bundle ticket id related to above bundle and direction = OUT
         * if above ticket has no scanned_qty => raise err (IN has nor been sacnned)
         */
        if ($bundle_ticket->direction == 'IN') {
            
            if (__isParentOperation($bundle_ticket)) {
                $prev_routing_op = __getPrevRoutingOperation($bundle_ticket);
                if (!is_null($prev_routing_op)) {
                    $prev_bt = __getPrevBundleTicket($bundle_ticket, $prev_routing_op);
                    
                    if (!is_null($prev_bt)) {
                        if (!($prev_bt->scan_quantity > 0)) {
                            __throwException($prev_bt->fpo_operation->routing_operation->operation_code, $prev_bt->direction);
                        }
                    }
                }
            } else {
                $parent_bt = __getParentBundleTicket($bundle_ticket, 'IN');
                if (($parent_bt->fpo_operation->routing_operation->in == 1) && ($parent_bt->direction == 'IN')) {
                    if (($parent_bt->scan_quantity == 0) || (is_null($parent_bt->scan_quantity))) {
                        __throwException($parent_bt->fpo_operation->routing_operation->operation_code, $parent_bt->direction);
                    }
                }
            }
        }

        if ($bundle_ticket->direction == 'OUT') {
            $child_ops = RoutingOperation::where(['parent_operation_id' => $bundle_ticket->fpo_operation->routing_operation->id])->pluck('id')->toArray();
            if (sizeof($child_ops) > 0) {
                foreach ($child_ops as $child_op) {
                    __isChildOpOutScanned($bundle_ticket, $child_op);
                }
            } else {
                $related_bt = BundleTicket::where(['fpo_operation_id' => $bundle_ticket->fpo_operation_id, 'bundle_id' => $bundle_ticket->bundle_id])
                    ->whereNotIn('id', [$bundle_ticket->id])
                    ->first();
                if (is_null($related_bt)) {
                    $prev_routing_op = __getPrevRoutingOperation($bundle_ticket);
                    if (!is_null($prev_routing_op)) {
                        $prev_bt = __getPrevBundleTicket($bundle_ticket, $prev_routing_op);
                        if (!is_null($prev_bt)) {
                            if (!($prev_bt->scan_quantity > 0)) {
                                __throwException($prev_bt->fpo_operation->routing_operation->operation_code, $prev_bt->direction);
                            }
                        }
                    }
                } else {
                    if (!($related_bt->scan_quantity > 0)) {
                        __throwException($related_bt->fpo_operation->routing_operation->operation_code, $related_bt->direction);
                    }
                }
            }
        }

        /**
         * IN scan: child level
         * check whether child bundle_ticket
         * get current BundleTicket bundle_id, fpo_operation_id
         * if fpo_ope_id has a parent then current one is a child ticket
         * check whether parent has IN scanned if not raise error
         */

        /**
         * OUT scan: (no child)
         * get current BundleTicket bundle_id, fpo_operation_id and direction != 'OUT' and has bundle then fetch
         * if above ticket has no scanned_qty => raise err (IN has nor been sacnned)
         */

        /**
         * OUT scan: (has child)
         * check recursivley whether any child has not in OUT. then raise err
         * get current BundleTicket bundle_id, fpo_operation_id and direction != 'OUT' and fetch
         * if above ticket has no scanned_qty => raise err (IN has nor been sacnned)
         */

        /**
         * OUT scan: child level
         * check whether child bundle_ticket
         * get current BundleTicket bundle_id, fpo_operation_id
         * if fpo_ope_id has a parent then current one is a child ticket
         */
    }

    public function updateScannedQuantity($bundle_ticket_id, $qty, $user_id)
    {
//      print_r($user_id);
        try {
            DB::beginTransaction();
            $bundle_ticket = BundleTicket::findOrFail($bundle_ticket_id);
            $slot_no = DailyTeamSlotTarget::where([
                'daily_scanning_slot_id' => $bundle_ticket->daily_scanning_slot_id,
                'daily_shift_team_id' => $bundle_ticket->daily_shift_team_id
            ])->first()->seq_no;
            $dtst = DailyTeamSlotTarget::where([
                'daily_scanning_slot_id' => $bundle_ticket->daily_scanning_slot_id,
                'daily_shift_team_id' => $bundle_ticket->daily_shift_team_id
            ])
                ->where('seq_no', '>', $slot_no)
                ->whereNotNull('actual')
                ->orderBy('seq_no', 'DESC')
                ->first();

            $this->__validateScan($bundle_ticket->daily_scanning_slot_id, $bundle_ticket->daily_shift_team_id);

            if (is_null($dtst)) {
                $this->_calculateTargetInformationSetup($bundle_ticket, 'MOD', $qty);
            }
            BundleTicketRepository::updateRec($bundle_ticket_id, ['scan_quantity' => $qty, 'updated_by' => $user_id]);
//        $bundle_ticket->update([
//            'updated_by' => "XX",
//            'scan_quantity' => $qty
//        ]);
            DB::commit();
            return response()->json(["status" => "success"], 200);
        } catch (Exception $e) {
            DB::rollBack();
            throw new \App\Exceptions\GeneralException($e->getMessage());
        }
    }

    private function __updateTargetInformationSetup(BundleTicket $bundle_ticket)
    {
        // $slot_no = $bundle_ticket->daily_scanning_slot->seq_no;
        $slot_no = DailyTeamSlotTarget::where([
            'daily_scanning_slot_id' => $bundle_ticket->daily_scanning_slot_id,
            'daily_shift_team_id' => $bundle_ticket->daily_shift_team_id
        ])->first()->seq_no;
        // $dss = DailyScanningSlot::where('daily_shift_id', $bundle_ticket->daily_scanning_slot->daily_shift_id)
        //   ->where('seq_no', '>', $slot_no)
        //   ->whereNotNull('actual')
        //   ->orderBy('seq_no', 'DESC')
        //   ->first();
        $dtst = DailyTeamSlotTarget::where([
            'daily_scanning_slot_id' => $bundle_ticket->daily_scanning_slot_id,
            'daily_shift_team_id' => $bundle_ticket->daily_shift_team_id
        ])
            ->where('seq_no', '>', $slot_no)
            ->whereNotNull('actual')
            ->orderBy('seq_no', 'DESC')
            ->first();

        if (is_null($dtst)) {
            $this->_calculateTargetInformationSetup($bundle_ticket, 'DEL');
        }
    }

    public function unscan($bundle_ticket_id, $username)
    {
        try {
            DB::beginTransaction();
            $bundle_ticketSec = BundleTicketSecondary::findOrFail($bundle_ticket_id);
            $bundle_ticket = BundleTicket::findOrFail($bundle_ticketSec->bundle_ticket_id);
            $this->__validateScan($bundle_ticket->daily_scanning_slot_id, $bundle_ticket->daily_shift_team_id);

            if($bundle_ticket->usedToWFX == "Y") {
                throw new Exception("Cannot be deleted hence data has been uploaded to WFX");
            }

            $job_card = JobCard::select('job_cards.*')
                ->join('job_card_bundles', 'job_card_bundles.job_card_id', '=', 'job_cards.id')
                ->join('bundles', 'job_card_bundles.bundle_id', '=', 'bundles.id')
                ->join('bundle_tickets', 'bundle_tickets.bundle_id', '=', 'bundles.id')
                ->where('bundle_tickets.id', $bundle_ticket->id)
                ->first();

            $jj = JobCard::find($job_card->id);

            ///////////////////////   validate Previous Operation   ///////////////////
            $all_tickets = BundleTicket::where('bundle_id',$bundle_ticket->bundle_id)->get();
            $op_seq = $bundle_ticket->fpo_operation->routing_operation->wfx_seq;

            foreach($all_tickets as $rec){
                $seq = $rec->fpo_operation->routing_operation->wfx_seq;

                if(($rec->scan_quantity) > 0 && $op_seq < $seq){
                    $op =  $rec->fpo_operation->routing_operation->operation_code." - ".$rec->direction;
                    //if(substr($op,0,1) !== "C" && substr($op,0,1) !== "E"){
                    throw new Exception("Next Operation already Scanned ".$op."");
                    // }

                }
                else if(($rec->scan_quantity) > 0 && $op_seq === $seq && $bundle_ticket->direction === "IN" && $rec->direction === "OUT"){
                    $op =  $rec->fpo_operation->routing_operation->operation_code." - ".$rec->direction;
                    // if(substr($op,0,1) !== "C" && substr($op,0,1) !== "E"){
                    throw new Exception("Next Operation already Scanned ".$op."");
                    // }

                }

            }

            if(substr($bundle_ticket->fpo_operation->routing_operation->operation_code, 0 , 2) == "SW" && $bundle_ticket->direction == "OUT"){
                throw new Exception("Please find and delete the PK-IN ticket of this ticket.");
            }

            if(substr($bundle_ticket->fpo_operation->routing_operation->operation_code, 0 , 1) == "P" && $bundle_ticket->direction == "IN"){
                foreach ($all_tickets as $rec) {
                    $op = $rec->fpo_operation->routing_operation->operation_code . " - " . $rec->direction;
                    if (substr($op, 0, 2) === "SW" && $rec->direction === "OUT") {
                        $swOutSec = BundleTicketSecondary::where([['bundle_ticket_id', '=', $rec->id]])->get();
//                        return $swOutSec;
                        if(sizeof($swOutSec) > 1){
                            foreach ($swOutSec as $s){
                                if($s->scan_quantity == $bundle_ticketSec->scan_quantity){
                                    $deleteSwSec = BundleTicketSecondary::where([['id', '=', $s->id]])->pluck('id')->toArray();
                                    $newQty = $rec->scan_quantity - $s->scan_quantity;
                                    $rec->update([
                                        'scan_quantity' => $newQty,
                                        'updated_by'=>$username,
                                        'updated_at' => now('Asia/Kolkata'),
                                    ]);

                                    BundleTicketSecondaryRepository::deleteRecs($deleteSwSec);
                                    break;
                                }
                            }
                        }
                        else{
                            $deleteSwSec = BundleTicketSecondary::where([['id', '=', $swOutSec[0]->id]])->pluck('id')->toArray();
                            BundleTicketSecondaryRepository::deleteRecs($deleteSwSec);
                            $rec->update([
                                'scan_quantity' => null,
                                'scan_date_time' => null,
                                'daily_scanning_slot_id' => null,
                                'daily_shift_team_id' => null,
                                'updated_by' => $username,
                                'updated_at' => now('Asia/Kolkata'),
                            ]);

                        }
                    }
                }
            }


            $current = BundleTicketSecondary::where([['bundle_ticket_id', '=', $bundle_ticket->id]])->get();
            if(sizeof($current) > 1){
                foreach ($current as $s){
                    if($s->scan_quantity == $bundle_ticketSec->scan_quantity){
                        $deleteSwSec = BundleTicketSecondary::where([['id', '=', $s->id]])->pluck('id')->toArray();
                        $newQty = $bundle_ticket->scan_quantity - $s->scan_quantity;
                        $bundle_ticket->update([
                            'scan_quantity' => $newQty,
                            'updated_by'=>$username,
                            'updated_at' => now('Asia/Kolkata')
                        ]);

                        BundleTicketSecondaryRepository::deleteRecs($deleteSwSec);
                        break;
                    }
                }
            }
            else{
                $deleteSwSec = BundleTicketSecondary::where([['id', '=', $current[0]->id]])->pluck('id')->toArray();
                BundleTicketSecondaryRepository::deleteRecs($deleteSwSec);
                $bundle_ticket->update([
                    'scan_quantity' => null,
                    'scan_date_time' => null,
                    'daily_scanning_slot_id' => null,
                    'daily_shift_team_id' => null,
                    'updated_by' => $username,
                    'updated_at' => now('Asia/Kolkata'),
                ]);

            }

            if(($job_card->status == 'Ready') && substr($bundle_ticket->fpo_operation->routing_operation->operation_code, 0 , 1) == "P" && $bundle_ticket->direction == "IN"){
//                JobCardRepository::fsmProgress($jj);
                $jj->update([
                    'status' => 'InProgress'
                ]);
            }

            if (($job_card->status == 'InProgress')  && substr($bundle_ticket->fpo_operation->routing_operation->operation_code, 0 , 1) == "S" && $bundle_ticket->direction == "IN") {
                $flag = false;
                foreach ($jj->job_card_bundles as $job_card_bundle) {
                    $job_card_bundle->load('bundle.bundle_tickets');
                    foreach ($job_card_bundle->bundle->bundle_tickets as $bt) {
                        $op_n_dir_2 = $bt->fpo_operation->routing_operation->operation_code . "-" . $bt->direction;
                        if ((($op_n_dir_2 == 'SW100001-IN')) && (!is_null($bt->scan_quantity) || ($bt->scan_quantity != 0))) {
                            $flag = true;
                        }
                    }
                }
                if (!$flag) {
                    $jj->update([
                        'status' => 'Issued'
                    ]);
                }
            }

            DB::commit();
//            $this->_handleJobCardStatus($bundle_ticket);
            return response()->json(["status" => "success"], 200);
        } catch (Exception $e) {
            DB::rollBack();
            throw new \App\Exceptions\GeneralException($e->getMessage());
        }
    }

    public function deleteFromEditBundleOLD($bundle_ticket_id, $username, $reason)
    {
        try {
            DB::beginTransaction();
            $bundle_ticketSec = BundleTicketSecondary::findOrFail($bundle_ticket_id);
            $bundle_ticket = BundleTicket::findOrFail($bundle_ticketSec->bundle_ticket_id);
//            $this->__validateScan($bundle_ticket->daily_scanning_slot_id, $bundle_ticket->daily_shift_team_id);

//            if($bundle_ticket->usedToWFX == "Y") {
//                throw new Exception("Cannot be deleted hence data has been uploaded to WFX");
//            }

            $job_card = JobCard::select('job_cards.*')
                ->join('job_card_bundles', 'job_card_bundles.job_card_id', '=', 'job_cards.id')
                ->join('bundles', 'job_card_bundles.bundle_id', '=', 'bundles.id')
                ->join('bundle_tickets', 'bundle_tickets.bundle_id', '=', 'bundles.id')
                ->where('bundle_tickets.id', $bundle_ticket->id)
                ->first();

            $jj = JobCard::find($job_card->id);

            ///////////////////////   validate Previous Operation   ///////////////////
            $all_tickets = BundleTicket::where('bundle_id',$bundle_ticket->bundle_id)->get();
            $op_seq = $bundle_ticket->fpo_operation->routing_operation->wfx_seq;

            foreach($all_tickets as $rec){
                $seq = $rec->fpo_operation->routing_operation->wfx_seq;

                if(($rec->scan_quantity) > 0 && $op_seq < $seq){
                    $op =  $rec->fpo_operation->routing_operation->operation_code." - ".$rec->direction;
                    //if(substr($op,0,1) !== "C" && substr($op,0,1) !== "E"){
                    throw new Exception("Next Operation already Scanned ".$op."");
                    // }

                }
                else if(($rec->scan_quantity) > 0 && $op_seq === $seq && $bundle_ticket->direction === "IN" && $rec->direction === "OUT"){
                    $op =  $rec->fpo_operation->routing_operation->operation_code." - ".$rec->direction;
                    // if(substr($op,0,1) !== "C" && substr($op,0,1) !== "E"){
                    throw new Exception("Next Operation already Scanned ".$op."");
                    // }

                }

            }

            if(substr($bundle_ticket->fpo_operation->routing_operation->operation_code, 0 , 2) == "SW" && $bundle_ticket->direction == "OUT"){
                throw new Exception("Please find and delete the PK-IN ticket of this ticket.");
            }

            if(substr($bundle_ticket->fpo_operation->routing_operation->operation_code, 0 , 2) == "PK" && $bundle_ticket->direction == "OUT"){
//                throw new Exception("PK OUT tickets are not allowed to delete");
            }

            if(substr($bundle_ticket->fpo_operation->routing_operation->operation_code, 0 , 1) == "P" && $bundle_ticket->direction == "IN"){
                foreach ($all_tickets as $rec) {
                    $op = $rec->fpo_operation->routing_operation->operation_code . " - " . $rec->direction;
                    if (substr($op, 0, 2) === "SW" && $rec->direction === "OUT") {
                        $swOutSec = BundleTicketSecondary::where([['bundle_ticket_id', '=', $rec->id]])->get();
//                        return $swOutSec;
                        if(sizeof($swOutSec) > 1){
                            foreach ($swOutSec as $s){
                                if($s->scan_quantity == $bundle_ticketSec->scan_quantity){
                                    $deleteSwSec = BundleTicketSecondary::where([['id', '=', $s->id]])->pluck('id')->toArray();
                                    $newQty = $rec->scan_quantity - $s->scan_quantity;
                                    $rec->update([
                                        'scan_quantity' => $newQty,
                                        'updated_by'=>$username,
                                        'updated_at' => now('Asia/Kolkata'),
                                    ]);

                                    BundleTicketSecondaryRepository::deleteRecs($deleteSwSec);
                                    break;
                                }
                            }
                        }
                        else{
                            $deleteSwSec = BundleTicketSecondary::where([['id', '=', $swOutSec[0]->id]])->pluck('id')->toArray();
                            BundleTicketSecondaryRepository::deleteRecs($deleteSwSec);
                            $rec->update([
                                'scan_quantity' => null,
                                'scan_date_time' => null,
                                'daily_scanning_slot_id' => null,
                                'daily_shift_team_id' => null,
                                'updated_by' => $username,
                                'updated_at' => now('Asia/Kolkata'),
                            ]);

                        }
                    }
                }
            }


            $current = BundleTicketSecondary::where([['bundle_ticket_id', '=', $bundle_ticket->id]])->get();
            if(sizeof($current) > 1){
                foreach ($current as $s){
                    if($s->scan_quantity == $bundle_ticketSec->scan_quantity){
                        $deleteSwSec = BundleTicketSecondary::where([['id', '=', $s->id]])->pluck('id')->toArray();
                        $newQty = $bundle_ticket->scan_quantity - $s->scan_quantity;
                        $bundle_ticket->update([
                            'scan_quantity' => $newQty,
                            'updated_by'=>$username,
                            'updated_at' => now('Asia/Kolkata')
                        ]);

                        BundleTicketSecondaryRepository::deleteRecs($deleteSwSec);
                        break;
                    }
                }
            }
            else{
                $deleteSwSec = BundleTicketSecondary::where([['id', '=', $current[0]->id]])->pluck('id')->toArray();
                BundleTicketSecondaryRepository::deleteRecs($deleteSwSec);
                $bundle_ticket->update([
                    'scan_quantity' => null,
                    'scan_date_time' => null,
                    'daily_scanning_slot_id' => null,
                    'daily_shift_team_id' => null,
                    'updated_by' => $username,
                    'updated_at' => now('Asia/Kolkata'),
                ]);

            }

            if(($job_card->status == 'Ready') && substr($bundle_ticket->fpo_operation->routing_operation->operation_code, 0 , 1) == "P" && $bundle_ticket->direction == "IN"){
//                JobCardRepository::fsmProgress($jj);
                $jj->update([
                    'status' => 'InProgress'
                ]);
            }

            if (($job_card->status == 'InProgress')  && substr($bundle_ticket->fpo_operation->routing_operation->operation_code, 0 , 1) == "S" && $bundle_ticket->direction == "IN") {
                $flag = false;
                foreach ($jj->job_card_bundles as $job_card_bundle) {
                    $job_card_bundle->load('bundle.bundle_tickets');
                    foreach ($job_card_bundle->bundle->bundle_tickets as $bt) {
                        $op_n_dir_2 = $bt->fpo_operation->routing_operation->operation_code . "-" . $bt->direction;
                        if ((($op_n_dir_2 == 'SW100001-IN')) && (!is_null($bt->scan_quantity) || ($bt->scan_quantity != 0))) {
                            $flag = true;
                        }
                    }
                }
                if (!$flag) {
                    $jj->update([
                        'status' => 'Issued'
                    ]);
                }
            }

            $bt_secondary = EditBundleLog::insert([
                'bundle_ticket_id' => $bundle_ticket->id,
                'bundle_ticket_secondary_id' => $bundle_ticket_id,
                "created_at" => now('Asia/Kolkata'),
                "created_by" => $username,
                "reason" => $reason
            ]);

            DB::commit();
//            $this->_handleJobCardStatus($bundle_ticket);
            return response()->json(["status" => "success"], 200);
        } catch (Exception $e) {
            DB::rollBack();
            throw new \App\Exceptions\GeneralException($e->getMessage());
        }
    }

    public function deleteFromEditBundle($bundle_ticket_id, $username, $reason)
    {
        try {
            DB::beginTransaction();
            $bundle_ticketSec = BundleTicketSecondary::findOrFail($bundle_ticket_id);
            $bundle_ticket = BundleTicket::findOrFail($bundle_ticketSec->bundle_ticket_id);
//            $this->__validateScan($bundle_ticket->daily_scanning_slot_id, $bundle_ticket->daily_shift_team_id);

//            if($bundle_ticket->usedToWFX == "Y") {
//                throw new Exception("Cannot be deleted hence data has been uploaded to WFX");
//            }
            

            ///////////////////////   validate Previous Operation   ///////////////////
            $all_tickets = BundleTicket::where('bundle_id',$bundle_ticket->bundle_id)->get();
            $op_seq = $bundle_ticket->fpo_operation->routing_operation->wfx_seq;

            foreach($all_tickets as $rec){
                $seq = $rec->fpo_operation->routing_operation->wfx_seq;

                if(($rec->scan_quantity) > 0 && $op_seq < $seq){
                    $op =  $rec->fpo_operation->routing_operation->operation_code." - ".$rec->direction;
                    //if(substr($op,0,1) !== "C" && substr($op,0,1) !== "E"){
                    throw new Exception("Next Operation already Scanned ".$op."");
                    // }

                }
                else if(($rec->scan_quantity) > 0 && $op_seq === $seq && $bundle_ticket->direction === "IN" && $rec->direction === "OUT"){
                    $op =  $rec->fpo_operation->routing_operation->operation_code." - ".$rec->direction;
                    // if(substr($op,0,1) !== "C" && substr($op,0,1) !== "E"){
                    throw new Exception("Next Operation already Scanned ".$op."");
                    // }

                }

            }

            if(substr($bundle_ticket->fpo_operation->routing_operation->operation_code, 0 , 2) == "SW" && $bundle_ticket->direction == "OUT"){
                throw new Exception("Please find and delete the PK-IN ticket of this ticket.");
            }

            if(substr($bundle_ticket->fpo_operation->routing_operation->operation_code, 0 , 2) == "PK" && $bundle_ticket->direction == "OUT"){
//                throw new Exception("PK OUT tickets are not allowed to delete");
            }

            if(substr($bundle_ticket->fpo_operation->routing_operation->operation_code, 0 , 1) == "P" && $bundle_ticket->direction == "IN"){
                foreach ($all_tickets as $rec) {
                    $op = $rec->fpo_operation->routing_operation->operation_code . " - " . $rec->direction;
                    if (substr($op, 0, 2) === "SW" && $rec->direction === "OUT") {
                        $swOutSec = BundleTicketSecondary::where([['bundle_ticket_id', '=', $rec->id]])->get();
//                        return $swOutSec;
                        if(sizeof($swOutSec) > 1){
                            foreach ($swOutSec as $s){
                                if($s->scan_quantity == $bundle_ticketSec->scan_quantity){
                                    $deleteSwSec = BundleTicketSecondary::where([['id', '=', $s->id]])->pluck('id')->toArray();
                                    $newQty = $rec->scan_quantity - $s->scan_quantity;
                                    $rec->update([
                                        'scan_quantity' => $newQty,
                                        'updated_by'=>$username,
                                        'updated_at' => now('Asia/Kolkata'),
                                    ]);

                                    BundleTicketSecondaryRepository::deleteRecs($deleteSwSec);
                                    break;
                                }
                            }
                        }
                        else{
                            $deleteSwSec = BundleTicketSecondary::where([['id', '=', $swOutSec[0]->id]])->pluck('id')->toArray();
                            BundleTicketSecondaryRepository::deleteRecs($deleteSwSec);
                            $rec->update([
                                'scan_quantity' => null,
                                'scan_date_time' => null,
                                'daily_scanning_slot_id' => null,
                                'daily_shift_team_id' => null,
                                'updated_by' => $username,
                                'updated_at' => now('Asia/Kolkata'),
                            ]);

                        }
                    }
                }
            }


            $current = BundleTicketSecondary::where([['bundle_ticket_id', '=', $bundle_ticket->id]])->get();
            if(sizeof($current) > 1){
                foreach ($current as $s){
                    if($s->scan_quantity == $bundle_ticketSec->scan_quantity){
                        $deleteSwSec = BundleTicketSecondary::where([['id', '=', $s->id]])->pluck('id')->toArray();
                        $newQty = $bundle_ticket->scan_quantity - $s->scan_quantity;
                        $bundle_ticket->update([
                            'scan_quantity' => $newQty,
                            'updated_by'=>$username,
                            'updated_at' => now('Asia/Kolkata')
                        ]);

                        BundleTicketSecondaryRepository::deleteRecs($deleteSwSec);
                        break;
                    }
                }
            }
            else{
                $deleteSwSec = BundleTicketSecondary::where([['id', '=', $current[0]->id]])->pluck('id')->toArray();
                BundleTicketSecondaryRepository::deleteRecs($deleteSwSec);
                $bundle_ticket->update([
                    'scan_quantity' => null,
                    'scan_date_time' => null,
                    'daily_scanning_slot_id' => null,
                    'daily_shift_team_id' => null,
                    'updated_by' => $username,
                    'updated_at' => now('Asia/Kolkata'),
                ]);

            }

            if(substr($bundle_ticket->fpo_operation->routing_operation->operation_code, 0 , 1) == "P" && $bundle_ticket->direction == "IN"){
//                JobCardRepository::fsmProgress($jj);
			$job_card = JobCard::select('job_cards.*')
                ->join('job_card_bundles', 'job_card_bundles.job_card_id', '=', 'job_cards.id')
                ->join('bundles', 'job_card_bundles.bundle_id', '=', 'bundles.id')
                ->join('bundle_tickets', 'bundle_tickets.bundle_id', '=', 'bundles.id')
                ->where('bundle_tickets.id', $bundle_ticket->id)
                ->first();

            $jj = JobCard::find($job_card->id);
                $jj->update([
                    'status' => 'InProgress'
                ]);
            }

            if (substr($bundle_ticket->fpo_operation->routing_operation->operation_code, 0 , 1) == "S" && $bundle_ticket->direction == "IN") {
                $job_card = JobCard::select('job_cards.*')
					->join('job_card_bundles', 'job_card_bundles.job_card_id', '=', 'job_cards.id')
					->join('bundles', 'job_card_bundles.bundle_id', '=', 'bundles.id')
					->join('bundle_tickets', 'bundle_tickets.bundle_id', '=', 'bundles.id')
					->where('bundle_tickets.id', $bundle_ticket->id)
					->first();

				$jj = JobCard::find($job_card->id);
				$flag = false;
                foreach ($jj->job_card_bundles as $job_card_bundle) {
                    $job_card_bundle->load('bundle.bundle_tickets');
                    foreach ($job_card_bundle->bundle->bundle_tickets as $bt) {
                        $op_n_dir_2 = $bt->fpo_operation->routing_operation->operation_code . "-" . $bt->direction;
                        if ((($op_n_dir_2 == 'SW100001-IN')) && (!is_null($bt->scan_quantity) || ($bt->scan_quantity != 0))) {
                            $flag = true;
                        }
                    }
                }
                if (!$flag) {
                    $jj->update([
                        'status' => 'Issued'
                    ]);
                }
            }

            $bt_secondary = EditBundleLog::insert([
                'bundle_ticket_id' => $bundle_ticket->id,
                'bundle_ticket_secondary_id' => $bundle_ticket_id,
                "created_at" => now('Asia/Kolkata'),
                "created_by" => $username,
                "reason" => $reason
            ]);

            DB::commit();
//            $this->_handleJobCardStatus($bundle_ticket);
            return response()->json(["status" => "success"], 200);
        } catch (Exception $e) {
            DB::rollBack();
            throw new \App\Exceptions\GeneralException($e->getMessage());
        }
    }


    public function deleteQCRejects($request){
        try{
            DB::beginTransaction();
            $deleteSwSec = QcReject::where([['id', '=', $request->qc_rej_id]])->pluck('id')->toArray();
            QcRejectRepository::deleteRecs($deleteSwSec);
            DB::commit();
            return response()->json(["status" => "success"], 200);
        }
        catch (Exception $e) {
            DB::rollBack();
            throw new \App\Exceptions\GeneralException($e->getMessage());
        }
    }

    public function unscanNew($bundle_ticket_id, $username)
    {
        try {

            DB::beginTransaction();
            $bundle_ticket_sec = BundleTicketSecondary::findOrFail($bundle_ticket_id);
            $bundle_ticket = BundleTicket::findOrFail($bundle_ticket_sec->bundle_ticket_id);

            $this->__validateScan($bundle_ticket->daily_scanning_slot_id, $bundle_ticket->daily_shift_team_id);

            ///////////////////////   validate Previous Operation   ///////////////////
            $all_tickets = BundleTicket::where('bundle_id', $bundle_ticket->bundle_id)->get();
            $op_seq = $bundle_ticket->fpo_operation->routing_operation->wfx_seq;

            foreach ($all_tickets as $rec) {
                $seq = $rec->fpo_operation->routing_operation->wfx_seq;

                if (($rec->scan_quantity) > 0 && $op_seq < $seq) {
                    $op = $rec->fpo_operation->routing_operation->operation_code . " - " . $rec->direction;
                    //if(substr($op,0,1) !== "C" && substr($op,0,1) !== "E"){
                    throw new Exception("Next Operation already Scanned " . $op . "");
                    // }

                } else if (($rec->scan_quantity) > 0 && $op_seq === $seq && $bundle_ticket->direction === "IN" && $rec->direction === "OUT") {
                    $op = $rec->fpo_operation->routing_operation->operation_code . " - " . $rec->direction;
                    // if(substr($op,0,1) !== "C" && substr($op,0,1) !== "E"){
                    throw new Exception("Next Operation already Scanned " . $op . "");
                    // }

                }

            }

            if($bundle_ticket->usedToWFX != "Y") {
                if(substr($bundle_ticket->fpo_operation->routing_operation->operation_code, 0 , 1) == "P" && $bundle_ticket->direction == "IN"){

                }
                else if(substr($bundle_ticket->fpo_operation->routing_operation->operation_code, 0 , 2) == "SW" && $bundle_ticket->direction == "OUT"){

                }
//                    else {

                $bundle_ticket->update([
                    'scan_quantity' => null,
                    'scan_date_time' => null,
                    'daily_scanning_slot_id' => null,
                    'daily_shift_team_id' => null,
                    'updated_by' => $username,
                    'updated_at' => now('Asia/Kolkata')
                ]);

//                        $bundle_ticket_secondary = BundleTicketSecondary::where([['bundle_ticket_id', '=', $bundle_ticket_id]])->first();
                $bundle_ticket_secondary1 = BundleTicketSecondary::where(['bundle_ticket_id', '=', $bundle_ticket_id])->delete();

//                        if (!is_null($bundle_ticket_secondary)) {
//
//                        }

                //add facility to delete from qc_recoverable, qc_rejetc and bundle_bin
                $recovs = QcRecoverable::where('bundle_ticket_id', $bundle_ticket->id)->pluck('id')->toArray();
                QcRecoverableRepository::deleteRecs($recovs);

                $rejects = QcReject::where('bundle_ticket_id', $bundle_ticket->id)->pluck('id')->toArray();

                $bbs = BundleBin::whereIn('qc_reject_id', $rejects)->pluck('id')->toArray();
                BundleBinRepository::deleteRecs($bbs);

                QcRejectRepository::deleteRecs($rejects);
//                    }
            }
            DB::commit();
            return response()->json(["status" => "success"], 200);
//            }
        } catch (Exception $e) {
            DB::rollBack();
            throw new \App\Exceptions\GeneralException($e->getMessage());
        }
    }

    private function __validateScan($daily_scanning_slot_id, $daily_shift_team_id)
    {
        $dtst = DailyTeamSlotTarget::where(['daily_shift_team_id' => $daily_shift_team_id, 'daily_scanning_slot_id' => $daily_scanning_slot_id])->first();
        if (is_null($dtst)) {
            //throw new Exception("Please set up target information before scanning");
        } else {
            $seq_no = $dtst->seq_no;

            $recs = DailyTeamSlotTarget::where(['daily_shift_team_id' => $daily_shift_team_id])
                ->where('seq_no', '>', $seq_no)
                ->whereNotNull('actual')
                ->orderBy('seq_no')
                ->get();

            Log::info('=================');
            Log::info(DailyTeamSlotTarget::where(['daily_shift_team_id' => $daily_shift_team_id])->get());
            Log::info($recs);
            Log::info('=================');

            if ($recs->count() > 0) {
                throw new Exception("Scanning is in progress for Slot No " . $recs[0]->seq_no . ". Scan modifications are not allowed for Slot No " . $seq_no);
            }
        }
    }

    public function getBundleTicketByFppo($fppo_id)
    {
        $bundles = FpoCutPlan::select('bundles.id')
            ->join('fppos', 'fpo_cut_plans.fppo_id', '=', 'fppos.id')
            ->join('bundles', 'bundles.fppo_id', '=', 'fppos.id')
            ->where('fpo_cut_plans.fppo_id', $fppo_id)
            //->where('fpo_cut_plans.cut_plan_id', $cut_id)
            ->distinct()
            ->get()
            ->toArray();

        return BundleTicket::whereIn('bundle_id', $bundles)->with('bundle')->with('fpo_operation.routing_operation')->with('fpo_operation.fpo.soc')->get();
    }

    public function recordQc(
        $bundle_ticket_id,
        $daily_scanning_slot_id,
        $reject_qty,
        $reject_reason,
        $qc_reject_updated_at,
        $recoverable_quantity,
        $recoverable_reason,
        $qc_recoverable_updated_at
    ) {
        $qcReject = null;
        $qcRecover = null;
        $totalQcQty = $reject_qty + $recoverable_quantity;
        $bt = BundleTicket::where('id', $bundle_ticket_id)->with('bundle')->first();

        if (is_null($bt)) {
            throw new \App\Exceptions\GeneralException("Bundle Ticket does not exist.");
        }

        if ($totalQcQty != ($bt->original_quantity - $bt->scan_quantity)) {
            throw new \App\Exceptions\GeneralException("Full quantity is scanned, please delete and rescan to alter quantities.");
        }

        try {
            DB::beginTransaction();

            $qcRecover = QcRecoverable::where([
                'bundle_ticket_id' => $bundle_ticket_id,
                'daily_scanning_slot_id' => $daily_scanning_slot_id
            ])->first();

            if (is_null($qcRecover)) {
                if (!is_null($recoverable_quantity) && ($recoverable_quantity > 0)) {
                    $qcRecover = QcRecoverableRepository::createRec([
                        'current_date' => now('Asia/Kolkata'),
                        'bundle_ticket_id' => $bundle_ticket_id,
                        'daily_scanning_slot_id' => $daily_scanning_slot_id,
                        'recoverable_quantity' => $recoverable_quantity,
                        'recover_reason' => $recoverable_reason
                    ]);
                }
            } else {
                if (($recoverable_quantity == null) || ($recoverable_quantity == 0)) {
                    QcRecoverableRepository::deleteRecs([$qcRecover->id]);
                } else {
                    QcRecoverableRepository::updateRec($qcRecover->id, [
                        'updated_at' => $qc_recoverable_updated_at,
                        'current_date' => now('Asia/Kolkata'),
                        'recoverable_quantity' => $recoverable_quantity,
                        'recover_reason' => $recoverable_reason
                    ]);
                }
            }

            $qcReject = QcReject::where([
                'bundle_ticket_id' => $bundle_ticket_id,
                'daily_scanning_slot_id' => $daily_scanning_slot_id
            ])->first();

            if (is_null($qcReject)) {
                if (!is_null($reject_qty) && ($reject_qty > 0)) {
                    $qcReject = QcRejectRepository::createRec([
                        'bundle_ticket_id' => $bundle_ticket_id,
                        'daily_scanning_slot_id' => $daily_scanning_slot_id,
                        'quantity' => $reject_qty,
                        'reject_reason' => $reject_reason
                    ]);

                    $bb = BundleBinRepository::createRec([
                        'created_date' => now('Asia/Kolkata'),
                        'record_type' => 'qc reject',
                        'size' => $bt->bundle->size,
                        'quantity' => $reject_qty,
                        'utilized' => false,
                        'qc_reject_id' => $qcReject->id,
                        'created_by_id' => auth()->user()->id
                    ]);
                }
            } else {
                if (($reject_qty == null) || ($reject_qty == 0)) {
                    QcRejectRepository::deleteRecs([$qcReject->id]);
                } else {
                    QcRejectRepository::updateRec($qcReject->id, [
                        'updated_at' => $qc_reject_updated_at,
                        'quantity' => $reject_qty,
                        'reject_reason' => $reject_reason
                    ]);

                    $bb = BundleBin::where('qc_reject_id', $qcReject->id)->first();
                    BundleBinRepository::updateRec($bb->id, [
                        'updated_at' => $bb->updated_at,
                        'quantity' => $reject_qty,
                        'created_by_id' => auth()->user()->id
                    ]);
                }
            }

            $this->_setJobCardState($bundle_ticket_id);
            DB::commit();
            return response()->json([
                "status" => "success",
                "data" => [
                    "QcRecoverable" => $qcRecover,
                    "QcReject" => $qcReject
                ]
            ], 200);
        } catch (Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    public function getById($id){
        $bt = BundleTicket::where(['id' => $id])
            ->first();
        return $bt;
    }

    private function _setJobCardState($bundle_ticket_id)
    {
        $jc = JobCard::with('job_card_bundles.bundle.bundle_tickets.qc_recoverables')
            ->join('job_card_bundles', 'job_cards.id', 'job_card_bundles.job_card_id')
            ->join('bundle_tickets', 'job_card_bundles.bundle_id', 'bundle_tickets.bundle_id')
            ->where('bundle_tickets.id', $bundle_ticket_id)
            ->first();
        $flag = false;
        foreach ($jc->job_card_bundles as $job_card_bundle) {
            foreach ($job_card_bundle->bundle->bundle_tickets as $bundle_ticket) {
                if ($bundle_ticket->qc_recoverables->count() > 0) {
                    if (!is_null($bundle_ticket->qc_recoverables[0]->recoverable_quantity)) {
                        $flag = true;
                        break;
                    }
                }
            }
        }

        if ($flag) {
            JobCardRepository::fsmHold($jc);
        }
    }

    public function fetchQc($bundleTicketId)
    {
        $qcRecovers = QcRecoverable::where('bundle_ticket_id', $bundleTicketId)->get();
        $qcRejects = QcReject::where('bundle_ticket_id', $bundleTicketId)->get();
        return response()->json([
            "status" => "success",
            "data" => [
                "QcRecoverable" => $qcRecovers,
                "QcReject" => $qcRejects
            ]
        ], 200);
    }

    public function getPendingQcRecoverbles($fppo_id)
    {
        $qc_recovers = Fppo::select(
            'bundles.id as bundle_id',
            'bundles.size as bundle_size',
            'routing_operations.description as routing_op_description',
            'qc_recoverables.recoverable_quantity as qc_recover_recoverable_quantity',
            'qc_recoverables.recover_reason as qc_recover_recover_reason',
            'qc_recoverables.current_date as qc_recover_current_date'
        )
            ->join('fpo_cut_plans', 'fpo_cut_plans.fppo_id', '=', 'fppos.id')
            ->join('cut_plans', 'fpo_cut_plans.cut_plan_id', '=', 'cut_plans.id')
            ->join('cut_updates', 'cut_updates.cut_plan_id', '=', 'cut_plans.id')
            ->join('bundle_cut_update', 'bundle_cut_update.cut_update_id', '=', 'cut_updates.id')
            ->join('bundles', 'bundles.id', '=', 'bundle_cut_update.bundle_id')
            ->join('bundle_tickets', 'bundle_tickets.bundle_id', '=', 'bundles.id')
            ->join('qc_recoverables', 'qc_recoverables.bundle_ticket_id', '=', 'bundle_tickets.id')
            ->join('fpo_operations', 'fpo_operations.id', '=', 'bundle_tickets.fpo_operation_id')
            ->join('routing_operations', 'routing_operations.id', '=', 'fpo_operations.routing_operation_id')
            ->where('fppos.id', $fppo_id)
            ->where('qc_recoverables.recoverable_quantity', '>', 0)
            ->get();

        return $qc_recovers;
    }

    public function getScanResults($daily_scanning_slot_id, $daily_shift_team_id)
    {
        $bundle_tickets =  BundleTicket::select(
            'bundle_tickets.updated_at',
            'bundle_tickets.id',
            'bundle_tickets.direction',
            'bundle_tickets.bundle_id',
            'bundle_tickets.original_quantity',
            'bundles.size as bundle_size',
            'bundle_tickets.scan_quantity as revised_quantity',
            'fpo_operations.wip_point',
            'fpo_operations.fpo_id',
            'routing_operations.operation_code',
            'routing_operations.description'
        )
            ->join('fpo_operations', 'bundle_tickets.fpo_operation_id', '=', 'fpo_operations.id')
            ->join('routing_operations', 'fpo_operations.routing_operation_id', '=', 'routing_operations.id')
            ->join('bundles', 'bundle_tickets.bundle_id', '=', 'bundles.id')
            ->where('bundle_tickets.daily_scanning_slot_id', $daily_scanning_slot_id)
            ->where('bundle_tickets.daily_shift_team_id', $daily_shift_team_id)
            ->distinct()
            ->get();

        foreach ($bundle_tickets as $bundle_ticket) {
            $fpo = Fpo::find($bundle_ticket->fpo_id);
            $bundle_ticket->fpo_no = $fpo->wfx_fpo_no;

            $bundle = Bundle::find($bundle_ticket->bundle_id);
            if (!(is_null($bundle))) {
                $bundle_ticket->bundle_id = $bundle->id;
                $bundle_ticket->bundle_size = $bundle->size;
                $bundle_ticket->fppo_no = $bundle->fppo->fppo_no;
            }

            $qc_recoverables = QcRecoverable::where('bundle_ticket_id', $bundle_ticket->id)->orderBy('updated_at', 'DESC')->first();
            if (!(is_null($qc_recoverables))) {
                $bundle_ticket->recoverable_quantity = $qc_recoverables->recoverable_quantity;
            } else {
                $bundle_ticket->recoverable_quantity = 0;
            }

            $q_rejected = QcReject::where('bundle_ticket_id', $bundle_ticket->id)->sum('quantity');
            if (!(is_null($q_rejected))) {
                $bundle_ticket->reject_quantity = $q_rejected;
            } else {
                $bundle_ticket->reject_quantity = 0;
            }
        }
        return $bundle_tickets;
    }

    public function getBundleByOperation($request){
        $job = $request->job;
        $fpo = $request->fpo;

        $op = $request->op;
        $direction = $request->direction;
        $bundle_tickets = [];
        if($job != ""){
            $bundle_tickets =  BundleTicket::select(
                'bundle_tickets.id as id', 'bundle_tickets.fpo_operation_id','bundle_tickets.scan_quantity as scan_quantity','bundles.id as bundle_id','bundles.size','bundles.quantity','job_card_bundles.job_card_id as job','routing_operations.wfx_seq','fpos.qty_json'

            )
                ->join('fpo_operations', 'bundle_tickets.fpo_operation_id', '=', 'fpo_operations.id')
                ->join('routing_operations', 'fpo_operations.routing_operation_id', '=', 'routing_operations.id')
                ->join('bundles', 'bundle_tickets.bundle_id', '=', 'bundles.id')
                ->join('job_card_bundles', 'job_card_bundles.bundle_id', '=', 'bundles.id')
                ->join('job_cards', 'job_cards.id', '=', 'job_card_bundles.job_card_id')
                ->join('fpos', 'fpos.id', '=', 'job_cards.fpo_id')
                ->where('routing_operations.operation_code', 'like', '%' . $op . '%')
                ->where('job_card_bundles.job_card_id', $job)
                ->where('bundle_tickets.direction', $direction)
                ->distinct()
                ->get();
        }
        else if($job == ""){
            $bundle_tickets =  BundleTicket::select(
                'bundle_tickets.id as id', 'bundle_tickets.fpo_operation_id','bundle_tickets.scan_quantity as scan_quantity','bundles.id as bundle_id','bundles.size','bundles.quantity','job_card_bundles.job_card_id as job','routing_operations.wfx_seq','fpos.qty_json'

            )
                ->join('fpo_operations', 'bundle_tickets.fpo_operation_id', '=', 'fpo_operations.id')
                ->join('routing_operations', 'fpo_operations.routing_operation_id', '=', 'routing_operations.id')
                ->join('bundles', 'bundle_tickets.bundle_id', '=', 'bundles.id')
                ->join('job_card_bundles', 'job_card_bundles.bundle_id', '=', 'bundles.id')
                ->join('job_cards', 'job_cards.id', '=', 'job_card_bundles.job_card_id')
                ->join('fpos', 'fpos.id', '=', 'job_cards.fpo_id')
                ->where('routing_operations.operation_code', 'like', '%' . $op . '%')
                ->where('fpos.id', $fpo)
                ->where('bundle_tickets.direction', $direction)
                ->distinct()
                ->get();
        }



        ///////////////////////////////  GET REJECT QTY AND PRE OPERATION SCAN QTY  /////////////////
        if(sizeof($bundle_tickets) > 0){
            foreach($bundle_tickets as $rec){
                $reject = QcReject::select( DB::raw('SUM(quantity) as totalReject'))
                    ->groupByRaw('bundle_ticket_id')
                    ->where('bundle_ticket_id', $rec->id)
                    ->get();
                $rec->reject = "";
                if(sizeof($reject) > 0){
                    $rec->reject = $reject[0]->totalReject;
                }

                if($direction == "OUT"){
                    $pre_bundle_tickets =  BundleTicket::select('bundle_tickets.scan_quantity','routing_operations.operation_code')
                        ->join('fpo_operations', 'bundle_tickets.fpo_operation_id', '=', 'fpo_operations.id')
                        ->join('routing_operations', 'fpo_operations.routing_operation_id', '=', 'routing_operations.id')
                        ->where('bundle_tickets.fpo_operation_id', $rec->fpo_operation_id)
                        ->where('bundle_tickets.bundle_id', $rec->bundle_id)
                        ->where('bundle_tickets.direction', 'IN')
                        ->first();

                    $rec->preOpQty = $pre_bundle_tickets->scan_quantity;
                    $rec->preOp = $pre_bundle_tickets->operation_code." - "."IN";
                }
                else if($direction == "IN"){
                    $pre_bundle_tickets =  BundleTicket::select('bundle_tickets.scan_quantity','routing_operations.operation_code','routing_operations.wfx_seq')
                        ->join('fpo_operations', 'bundle_tickets.fpo_operation_id', '=', 'fpo_operations.id')
                        ->join('routing_operations', 'fpo_operations.routing_operation_id', '=', 'routing_operations.id')
                        ->orderBy('routing_operations.wfx_seq', 'DESC')
                        ->where('routing_operations.wfx_seq','<', $rec->wfx_seq)
                        ->where('bundle_tickets.bundle_id', $rec->bundle_id)
                        ->where('bundle_tickets.direction', 'OUT')
                        ->first();

                    if(is_null($pre_bundle_tickets)){
                        $rec->preOpQty = $rec->quantity;
                        $rec->preOp = '-';
                    }else{
                        $rec->preOpQty = $pre_bundle_tickets->scan_quantity;
                        $rec->preOp = $pre_bundle_tickets->operation_code." - "."OUT";
                    }

                }

            }
        }

        return $bundle_tickets;
    }

    public function manualScanByOperation($request){
        try {
            DB::beginTransaction();

            if (!(isset($request->daily_shift_team_id))) {
                throw new Exception('Daily Team Information is not Received');
            }
            if (!(isset($request->daily_scanning_slot_id))) {
                throw new Exception('Daily Scan Slot is not Received');
            }


            foreach($request->list as $rec){

                $bundle_ticket = BundleTicket::find($rec['ticket_id']);
                if (!isset($bundle_ticket)) {
                    throw new Exception("Bundle Ticket does not exist.");
                }

                ///////////////////////   validate Previous Operation   ///////////////////
                $all_tickets = BundleTicket::where('bundle_id',$bundle_ticket->bundle_id)->get();

                $op_seq = $bundle_ticket->fpo_operation->routing_operation->wfx_seq;
                foreach($all_tickets as $tk_rec){

                    $seq = $tk_rec->fpo_operation->routing_operation->wfx_seq;
                    if(is_null($tk_rec->scan_quantity) && $op_seq > $seq){
                        $op =  $tk_rec->fpo_operation->routing_operation->operation_code." - ".$tk_rec->direction;
                        if(substr($op,0,1) !== "C" ){
                            throw new Exception("Previous Operation Not Scanned ".$op."");
                        }

                    }

                }
                if(floatval($rec['reject'] > 0)){
                    QcRejectRepository::createRec(['daily_scanning_slot_id' => $request->daily_scanning_slot_id, 'bundle_ticket_id' => $rec['ticket_id'], 'quantity' => $rec['reject'], 'reject_reason' => 'Manual Update' ]);
                }

                BundleTicketRepository::updateRec($rec['ticket_id'], ['scan_quantity' => $rec['scan_quantity'], 'daily_shift_team_id' => $request->daily_shift_team_id, 'daily_scanning_slot_id' => $request->daily_scanning_slot_id, 'scan_date_time'=>now('Asia/Kolkata'), 'updated_at'=>now('Asia/Kolkata') ]);
                $bundleTicket = BundleTicketRepository::getById($rec['ticket_id']);
                BundleTicketSecondaryRepository::createRec(['bundle_id' => $bundleTicket['bundle_id'], 'original_quantity' => $bundleTicket['original_quantity'], 'scan_quantity' => $rec['scan_quantity'], 'bundle_ticket_id' => $bundleTicket['id'], 'created_at' => now('Asia/Kolkata'), 'updated_at' => now('Asia/Kolkata'), 'scan_date_time' => now('Asia/Kolkata'), 'daily_scanning_slot_id' => $request->daily_scanning_slot_id, 'daily_shift_team_id' => $request->daily_shift_team_id]);
            }

            DB::commit();
            return response()->json(["status" => "success"], 200);
        } catch (Exception $e) {
            DB::rollBack();
            throw new \App\Exceptions\GeneralException($e->getMessage());
        }
    }


    public function selectTeamAutomatically($request){
//        print_r('from_date_time like %.$request->date_short.%');
        try {

            if (!(isset($request->date_long))) {
                throw new Exception('Date (Long format) is not Received');
            }
            if (!(isset($request->date_short))) {
                throw new Exception('Date (Short format) is not Received');
            }
            $x = null;
            $allSlots = DailyScanningSlot::where('from_date_time' , 'like' , '%'.$request->date_short.'%')->get();
//            $x = $allSlots;
//            print_r($allSlots);
            $consideringTimeStamp = strtotime($request->date_long);

            foreach($allSlots as $slot_rec){
                $fromTimeStamp = strtotime($slot_rec->from_date_time);
                $toTimeStamp = strtotime($slot_rec->to_date_time);

                if($consideringTimeStamp >= $fromTimeStamp && $consideringTimeStamp < $toTimeStamp){
                    $x = $slot_rec;
                }

            }


            return response()->json([
                "status" => "success",
                "data" => $x
            ], 200);        }
        catch (Exception $e) {
//            DB::rollBack();
            throw new \App\Exceptions\GeneralException($e->getMessage());
        }
    }

    /**
     * @throws GeneralException
     */
    public function getGridDataBundleScanning($request){
        $daily_scanning_slot = $request->daily_scanning_slot_id;
        $daily_shift_team = $request->daily_shift_team_id;
        try {
            $data = DB::table('bundle_ticket_secondaries')
                ->select('bundle_ticket_secondaries.bundle_ticket_id as Ticket_ID', 'bundle_ticket_secondaries.scan_quantity as Scanned_Qty', 'bundle_ticket_secondaries.original_quantity as Original_Qty', 'bundle_ticket_secondaries.id as Secondary_ID' , 'bundle_tickets.bundle_id as Bundle_ID' , 'fpos.wfx_fpo_no as FPO' , 'fpo_operations.operation as Operation', 'bundles.size as Size', 'teams.code as Team', 'daily_scanning_slots.seq_no as Slot', 'bundle_tickets..direction as Direction', 'bundle_ticket_secondaries.carton_id as box_id')
                ->join('bundle_tickets', 'bundle_ticket_secondaries.bundle_ticket_id','=','bundle_tickets.id')
                ->join('fpo_operations', 'fpo_operations.id','=','bundle_tickets.fpo_operation_id')
                ->join('bundles', 'bundles.id','=','bundle_tickets.bundle_id')
                ->join('fpos', 'fpos.id','=','fpo_operations.fpo_id')
                ->join('daily_shift_teams', 'daily_shift_teams.id','=','bundle_ticket_secondaries.daily_shift_team_id')
                ->join('daily_scanning_slots', 'daily_scanning_slots.id','=','bundle_ticket_secondaries.daily_scanning_slot_id')
                ->join('teams', 'teams.id', '=', 'daily_shift_teams.team_id')
                ->where('bundle_ticket_secondaries.daily_shift_team_id', $daily_shift_team)
                ->where('bundle_ticket_secondaries.daily_scanning_slot_id', $daily_scanning_slot)
                ->orderby('bundle_ticket_secondaries.scan_date_time','DESC')
                ->get();

            $dataRej = DB::table('qc_rejects')
                ->select('qc_rejects.id as ID', 'qc_rejects.bundle_ticket_id as Bundle_Ticket_ID', 'qc_rejects.quantity as Qty', 'qc_reject_reasons.description as Reason','qc_reject_reasons.operation as Operation', 'qc_reject_reasons.direction as Direction', 'bundle_tickets.original_quantity as OQ', 'bundles.size as size', 'bundle_tickets.bundle_id as Bundle_ID' , 'fpos.wfx_fpo_no as FPO', 'teams.code as Team', 'daily_scanning_slots.seq_no as Slot' )
                ->join('qc_reject_reasons', 'qc_reject_reasons.id', '=', 'qc_rejects.reject_reason')
                ->join('bundle_tickets', 'bundle_tickets.id', '=', 'qc_rejects.bundle_ticket_id')
                ->join('bundles', 'bundles.id','=','bundle_tickets.bundle_id')
                ->join('fpo_operations', 'fpo_operations.id','=','bundle_tickets.fpo_operation_id')
                ->join('fpos', 'fpos.id','=','fpo_operations.fpo_id')
                ->join('daily_shift_teams', 'daily_shift_teams.id','=','qc_rejects.daily_shift_team_id')
                ->join('daily_scanning_slots', 'daily_scanning_slots.id','=','qc_rejects.daily_scanning_slot_id')
                ->join('teams', 'teams.id', '=', 'daily_shift_teams.team_id')
                ->where('qc_rejects.daily_shift_team_id', '=', $daily_shift_team)
                ->where('qc_rejects.daily_scanning_slot_id', '=', $daily_scanning_slot)
                ->get();

            return response()->json([
                "status" => "success",
                "data" => [$data, $dataRej]
            ], 200);

        }
        catch (Exception $e) {
            throw new \App\Exceptions\GeneralException($e->getMessage());
        }
    }


    public function newPackingInScanning($boxId, $ticketId, $quantity, $daily_scanning_slot_id, $daily_shift_team_id,$scan_date_time, $user_id, $rejected_qty, $rejected_reason){
        try{
            DB::beginTransaction();

            $dailyShiftTeam = DB::table('daily_shift_teams')
                ->select('*')
                ->where('id', '=', $daily_shift_team_id)
                ->get();

//            $nowDate = new \DateTime("now");
            $nowDateYMD = $dailyShiftTeam[0]->current_date;// $nowDate->format("Y-m-d");
            $receivedDate = new \DateTime($scan_date_time);
            $receivedDateYMD = $receivedDate->format("Y-m-d");
//            print_r($nowDateYMD);
//            print_r($receivedDateYMD);
            if($nowDateYMD > $receivedDateYMD){
                throw new Exception("Scanning is not allowed for backdates.");
            }


            $bundle_ticket = BundleTicket::find($ticketId);

            $originlQty = intval($bundle_ticket->original_quantity);
			$scanQty = (intval($bundle_ticket->scan_quantity) > 0)? intval($bundle_ticket->scan_quantity) : 0;
			$available_qty = $originlQty - $scanQty ;

            $teamValidationStatus = $this->getTeamValidationInfo($ticketId, $daily_shift_team_id);

            if(!$teamValidationStatus){
                throw new Exception("The entered team does not match with the team of the Job Card.");
            }

            if (!(isset($quantity))) {
                throw new Exception('Quantity Information is not Received');
            }

            if($bundle_ticket->fpo_operation->operation == "PK" && $bundle_ticket->direction == "OUT"){
                throw new Exception('Packing out tickets are not allowed to scan here');
            }

            if($rejected_reason != "" && $rejected_reason != "0" && $rejected_reason != 0)  {
                $newQcReject = QcReject::insert([
                    'daily_scanning_slot_id' => $daily_scanning_slot_id,
                    'daily_shift_team_id' => $daily_shift_team_id,
                    'bundle_ticket_id' => $bundle_ticket->id,
                    'quantity' => $quantity,
                    'reject_reason' => $rejected_reason,
                    'created_at' => now('Asia/Kolkata'),
                    'updated_at' => now('Asia/Kolkata')
                ]);
            }
            else {

                $ifRejected = DB::table('qc_rejects')
                    ->select('*')
                    ->where('bundle_ticket_id' , '=', $ticketId)
                    ->get();

                foreach ($ifRejected as $if){
                    $available_qty = $available_qty - $if->quantity;
                    //$quantity = $quantity - $if->quantity;
                }

                if($available_qty <= 0){
                    throw new Exception('Error! Quantity is 0');
                }

                try {
                    $socBundle = DB::table('bundle_tickets')
                        ->select('socs.id')
                        ->join('fpo_operations', 'fpo_operations.id', '=', 'bundle_tickets.fpo_operation_id')
                        ->join('fpos', 'fpos.id', '=', 'fpo_operations.fpo_id')
                        ->join('socs', 'socs.id', '=', 'fpos.soc_id')
                        ->where('bundle_tickets.id', '=', $bundle_ticket->id)
                        ->get();

                    $socBox = DB::table('packing_list_details')
                        ->select('packing_list_soc.soc_id')
                        ->join('packing_list_soc', 'packing_list_soc.packing_list_id', '=', 'packing_list_details.packing_list_id')
                        ->where('packing_list_details.id' , '=', $boxId)
                        ->get();

                    $socEquals = false;
                    foreach ($socBox as $sb){
                        if($sb->soc_id == $socBundle[0]->id){
                            $socEquals = true;
                        }
                    }

                    if(!$socEquals){
                        throw new Exception("SOCs of the bundle and box do not match!");
                    }

                }
                catch (Exception $en){
                    throw new \App\Exceptions\GeneralException($en->getMessage());
                }


                $bundle_of_ticket = Bundle::find($bundle_ticket->bundle_id);
                if (!isset($bundle_ticket)) {
                    throw new Exception("Bundle Ticket does not exist.");
                }

                if (!(isset($boxId))) {
                    throw new Exception('Scanning Box Information is not Received');
                }

                $all_tickets = BundleTicket::where('bundle_id', $bundle_ticket->bundle_id)->get();

// need to add a button to see the details of the box
                $xxx = $this->bubbleSort($all_tickets);
                $previousOp = $this->findPreviousOp($ticketId);

                $n = sizeof($xxx);
                for ($i = 0; $i < $n; $i++) {

                    if ($xxx[$i]->scan_quantity == null && $xxx[$i]->id === $bundle_ticket->id) {
                        if ($xxx[$i]->direction == "OUT" && substr($xxx[$i]->fpo_operation->routing_operation->operation_code, 0, 2) == "SW") {
                            throw new Exception("SW - OUT tickets are not allowed to scan.");
                        }
                    }
                }

                if ($previousOp === null) {
                    throw new Exception("Unable to find the scan quantity of previous operation. Please contact a system administrator");
                }

                $swOutOfBundle = null;
                foreach ($all_tickets as $rec) {
                    $op = $rec->fpo_operation->routing_operation->operation_code . " - " . $rec->direction;
                    if (substr($op, 0, 2) === "SW" && $rec->direction === "OUT") {
                        $swOutOfBundle = $rec;
                    }
                }
                $Jobcard_rout = JobCardBundle::where('bundle_id',$bundle_ticket->bundle_id)->first();
                $rout = $Jobcard_rout->job_card->fpo->soc->style->routing_id;
                $op_seq = $bundle_ticket->fpo_operation->routing_operation->wfx_seq;
                foreach ($all_tickets as $rec) {
                    $seq = $rec->fpo_operation->routing_operation->wfx_seq;
                    if($rec->fpo_operation->routing_operation->routing_id == $rout) {
                        if (is_null($rec->scan_quantity) && $op_seq > $seq) {
                            $op = $rec->fpo_operation->routing_operation->operation_code . " - " . $rec->direction;

                            if (substr($op, 0, 1) === "C" || substr($op, 0, 1) === "E") {

                            } else if (substr($op, 0, 2) === "SW" && $rec->direction === "OUT") {
                                //                            $swOutOfBundle = $rec;
                            } else {
                                throw new Exception("Previous Operation Not Scanned " . $op . "");
                            }
                        }
                    }
                }

                if ($swOutOfBundle == null) {
                    throw new Exception("Could not able to find SW-OUT ticket");
                }

                if($swOutOfBundle->scan_quantity >= 0 && $swOutOfBundle->scan_quantity != null){
                    $secondary = DB::table('bundle_ticket_secondaries')
                        ->select('*')
                        ->where('bundle_ticket_secondaries.bundle_ticket_id', '=', $swOutOfBundle->id)
                        ->get();

                    foreach ($secondary as $item) {
                        if($item->carton_id == null){
                            $swOutOfBundle->update([
                                'scan_quantity' => $swOutOfBundle->scan_quantity - $item->scan_quantity,
                                'updated_at' => now('Asia/Kolkata'),
                                "updated_by" => $user_id
                            ]);
                            $deleteSwSec = BundleTicketSecondary::where([['id', '=', $item->id]])->pluck('id')->toArray();
                            BundleTicketSecondaryRepository::deleteRecs($deleteSwSec);
                        }
                    }
//                    throw new Exception("SW-OUT ticket (".$swOutOfBundle->id. ") of this PK-IN ticket has already been scanned.Please delete it to proceed.");
                }

                $this->__validateScan($daily_scanning_slot_id, $daily_shift_team_id);
                $this->_validateScan($bundle_ticket, $daily_shift_team_id);

                $packing_list_details = PackingListDetail::select('*')
                    ->where('id', $boxId)
                    ->first();


                $pack_ratio_sum = PackingListSoc::where(['packing_list_id' => $packing_list_details->packing_list_id])->sum('pack_ratio');
                if ($pack_ratio_sum == 0) {
                    $pack_ratio_sum = 1;
                }

                $actual_box_qty = 0;
                $size_available = false;
                foreach ($packing_list_details->qty_json as $key => $value) {
                    if (intval($value) > 0 && $key == $bundle_of_ticket->size) {
                        $size_available = true;
                        $actual_box_qty = intval($value) * $pack_ratio_sum;
                    }
                }

                if (!$size_available) {
                    throw new Exception("Size of the bundle (" . $bundle_of_ticket->size . ") is not available in the Box!");
                } else {
                    $bundle = DB::table('bundle_ticket_secondaries')
                        ->select('bundle_ticket_secondaries.*', 'bundle_tickets.id as bundle_ticket_id', 'bundles.size as size_of_bundle')
                        ->join('bundle_tickets', 'bundle_tickets.id', '=', 'bundle_ticket_secondaries.bundle_ticket_id')
                        ->join('fpo_operations', 'fpo_operations.id', '=', 'bundle_tickets.fpo_operation_id')
                        ->join('routing_operations', 'routing_operations.id', '=', 'fpo_operations.routing_operation_id')
                        ->join('bundles', 'bundles.id', '=', 'bundle_tickets.bundle_id')
                        ->where('bundle_ticket_secondaries.carton_id', $boxId)
                        ->Where('routing_operations.operation_code', 'like', '%' . 'PK' . '%')
                        ->where('bundle_tickets.direction', 'IN')
                        ->get();

                    $total_packing_in_for_box = 0;
                    foreach ($bundle as $bun) {
                        if ($bun->size_of_bundle == $bundle_of_ticket->size) {
                            $total_packing_in_for_box += $bun->scan_quantity;
                        }
                    }

                    if ($actual_box_qty - $total_packing_in_for_box >= $quantity) {
                        if (($bundle_ticket->scan_quantity >= 0) && (!is_null($bundle_ticket->scan_quantity))) {
                            if ($bundle_ticket->original_quantity != $bundle_ticket->scan_quantity) {
                                $qr = QcRecoverable::where('bundle_ticket_id', $bundle_ticket->id)->first();
                                if (!is_null($qr)) {
                                    $rec_qty = is_null($qr->recovered_quantity) ? 0 : $qr->recovered_quantity;
                                    if (($rec_qty != $qr->recoverable_quantity) && ($qr->recoverable_quantity != 0)) {

                                        $bundle_ticket->update([
                                            'scan_quantity' => $quantity + $qr->recoverable_quantity,
                                            'updated_by' => $user_id,
                                            'updated_at' => now('Asia/Kolkata'),
                                            'carton_id' => $boxId
                                        ]);

                                        $qr->update([
                                            'updated_at' => $qr->updated_at,
                                            'recovered_quantity' => $qr->recoverable_quantity,
                                            'recoverable_quantity' => 0
                                        ]);
                                    } else {
                                        throw new Exception("Bundle Ticket already scanned");
                                    }
                                } else {
//                                    throw new Exception("Please use Change Quantity option hence this has been partially scanned");
                                }
                            } else {
                                throw new Exception("Bundle Ticket already scanned");
                            }
                            $swOutOfBundle->update([
                                'scan_quantity' => $swOutOfBundle->scan_quantity + $quantity,
                                'scan_date_time' => now('Asia/Kolkata'),
                                'daily_shift_team_id' => $daily_shift_team_id,
                                'daily_scanning_slot_id' => $daily_scanning_slot_id,
                                'updated_by' => $user_id,
                                'carton_id' => $boxId,
                                'updated_at' => now('Asia/Kolkata')
                            ]);
                            $bt_secondary1 = BundleTicketSecondary::insert([
                                'bundle_ticket_id' => $swOutOfBundle->id,
                                'daily_scanning_slot_id' => $daily_scanning_slot_id,
                                'daily_shift_team_id' => $daily_shift_team_id,
                                'packing_list_id' => $packing_list_details->packing_list_id,
                                'bundle_id' => $swOutOfBundle->bundle_id,
                                'original_quantity' => $swOutOfBundle->original_quantity,
                                "scan_quantity" => $quantity,
                                'carton_id' => $boxId,
                                "scan_date_time" => now('Asia/Kolkata'),
                                'created_by' => $user_id,
                                'updated_by' => $user_id,
                                'created_at' => now('Asia/Kolkata'),
                                'updated_at' => now('Asia/Kolkata')
                            ]);

                            $bundle_ticket->update([
                                'scan_quantity' => $bundle_ticket->scan_quantity + $quantity,
                                'packing_list_id' => $packing_list_details->packing_list_id,
                                'scan_date_time' => now('Asia/Kolkata'),
                                'daily_shift_team_id' => $daily_shift_team_id,
                                'daily_scanning_slot_id' => $daily_scanning_slot_id,
                                'updated_by' => $user_id,
                                'carton_id' => $boxId,
                                'updated_at' => now('Asia/Kolkata')
                            ]);
                            $bt_secondary = BundleTicketSecondary::insert([
                                'bundle_ticket_id' => $bundle_ticket->id,
                                'daily_scanning_slot_id' => $daily_scanning_slot_id,
                                'daily_shift_team_id' => $daily_shift_team_id,
                                'packing_list_id' => $packing_list_details->packing_list_id,
                                'bundle_id' => $bundle_ticket->bundle_id,
                                'original_quantity' => $bundle_ticket->original_quantity,
                                "scan_quantity" => $quantity,
                                'carton_id' => $boxId,
                                "scan_date_time" => now('Asia/Kolkata'),
                                'created_by' => $user_id,
                                'updated_by' => $user_id,
                                'created_at' => now('Asia/Kolkata'),
                                'updated_at' => now('Asia/Kolkata')
                            ]);

                        } else {

                            $swOutOfBundle->update([
                                'scan_quantity' => $quantity,
                                'scan_date_time' => now('Asia/Kolkata'),
                                'daily_shift_team_id' => $daily_shift_team_id,
                                'daily_scanning_slot_id' => $daily_scanning_slot_id,
                                'updated_by' => $user_id,
                                'carton_id' => $boxId,
                                'updated_at' => now('Asia/Kolkata')
                            ]);
                            $bt_secondary1 = BundleTicketSecondary::insert([
                                'bundle_ticket_id' => $swOutOfBundle->id,
                                'daily_scanning_slot_id' => $daily_scanning_slot_id,
                                'daily_shift_team_id' => $daily_shift_team_id,
                                'packing_list_id' => $packing_list_details->packing_list_id,
                                'bundle_id' => $swOutOfBundle->bundle_id,
                                'original_quantity' => $swOutOfBundle->original_quantity,
                                "scan_quantity" => $quantity,
                                'carton_id' => $boxId,
                                "scan_date_time" => now('Asia/Kolkata'),
                                'created_by' => $user_id,
                                'updated_by' => $user_id,
                                'created_at' => now('Asia/Kolkata'),
                                'updated_at' => now('Asia/Kolkata')
                            ]);

                            $bundle_ticket->update([
                                'scan_quantity' => $quantity,
                                'scan_date_time' => now('Asia/Kolkata'),
                                'daily_shift_team_id' => $daily_shift_team_id,
                                'daily_scanning_slot_id' => $daily_scanning_slot_id,
                                'updated_by' => $user_id,
                                'carton_id' => $boxId,
                                'updated_at' => now('Asia/Kolkata')
                            ]);

                            $bt_secondary = BundleTicketSecondary::insert([
                                'bundle_ticket_id' => $bundle_ticket->id,
                                'daily_scanning_slot_id' => $daily_scanning_slot_id,
                                'daily_shift_team_id' => $daily_shift_team_id,
                                'packing_list_id' => $packing_list_details->packing_list_id,
                                'bundle_id' => $bundle_ticket->bundle_id,
                                'original_quantity' => $bundle_ticket->original_quantity,
                                "scan_quantity" => $quantity,
                                'carton_id' => $boxId,
                                "scan_date_time" => now('Asia/Kolkata'),
                                'created_by' => $user_id,
                                'updated_by' => $user_id,
                                'created_at' => now('Asia/Kolkata'),
                                'updated_at' => now('Asia/Kolkata')
                            ]);
                            try {
                                $this->_handleJobCardStatus($bundle_ticket);
                            } catch (Exception $e) {
                                throw new \App\Exceptions\GeneralException($e->getMessage());
                            }
                        }


                        $bundle_ticket1 = BundleTicket::find($bundle_ticket->id);
                        $this->_calculateTargetInformationSetup($bundle_ticket, 'ADD');

                    } else {
//                        throw new Exception("Not enough available quantity for size(" . $bundle_of_ticket->size . ") in the box to scan the quantity(" . $quantity . ") of bundle(" . $bundle_ticket->id . ").");
                        throw new Exception("Insufficient Allocated Bundle Qty for Size " . $bundle_of_ticket->size . ".\n Avl. Qty. in the Box- " . ($actual_box_qty - $total_packing_in_for_box) . " \n Qty. of the bundle - " . $quantity . " .");
                    }
                }
            }

            DB::commit();

            return response()->json([
                "status" => "success",
                "data" => ['bundle'=> $bundle_ticket]
            ], 200);


        }
        catch(Exception $ex){
            DB::rollBack();
            throw new \App\Exceptions\GeneralException($ex->getMessage());
        }
    }

    public static function  validatePackinList($bundle_id,$box_id){

        $data = DB::table('job_cards')
        ->join('job_card_bundles','job_card_bundles.job_card_id','=','job_cards.id')
        ->join('packing_list_details','packing_list_details.packing_list_id','=','job_cards.packing_list_no')
        ->where('packing_list_details.id',$box_id)
        ->where('job_card_bundles.bundle_id',$bundle_id)
        ->first();

        if(is_null($data)){
            return false;
        }
        return true;

    }


    public function newPackingInScanningOPD($boxId, $ticketId, $quantity, $daily_scanning_slot_id, $daily_shift_team_id,$scan_date_time, $user_id, $rejected_qty, $rejected_reason,$operation,$direction){
        try{
            DB::beginTransaction();

            $plv = self::validatePackinList($ticketId,$boxId);
            if(!$plv){
                throw new Exception("Different Packing List");
            }
            $bundle_ticket = DB::table('bundle_tickets')
            ->select('bundle_tickets.*')
            ->join('fpo_operations', 'fpo_operations.id', '=' , 'bundle_tickets.fpo_operation_id')
            ->where('fpo_operations.operation', '=' , $operation)
            ->where('bundle_tickets.direction' , '=', $direction)
            ->where('bundle_tickets.bundle_id', '=', $ticketId)
            ->first();

            if(strtoupper($operation) == "PK" && strtoupper($direction) == "IN"){
                $ranala_location = DB::table('locations')->select('id')
                ->where('location_name','=','Packing')
                ->first();

                DB::table('bundles')->where('id','=',$ticketId)->update(['location_id'=>$ranala_location->id]);
               
            }

            $ticketId = $bundle_ticket->id;

            $dailyShiftTeam = DB::table('daily_shift_teams')
                ->select('*')
                ->where('id', '=', $daily_shift_team_id)
                ->get();

//            $nowDate = new \DateTime("now");
            $nowDateYMD = $dailyShiftTeam[0]->current_date;// $nowDate->format("Y-m-d");
            $receivedDate = new \DateTime($scan_date_time);
            $receivedDateYMD = $receivedDate->format("Y-m-d");
//            print_r($nowDateYMD);
//            print_r($receivedDateYMD);
            if($nowDateYMD > $receivedDateYMD){
                throw new Exception("Scanning is not allowed for backdates.");
            }


            $bundle_ticket = BundleTicket::find($ticketId);
            
            $originlQty = intval($bundle_ticket->original_quantity);
			$scanQty = (intval($bundle_ticket->scan_quantity) > 0)? intval($bundle_ticket->scan_quantity) : 0;
			$available_qty = $originlQty - $scanQty ;

            $teamValidationStatus = $this->getTeamValidationInfo($ticketId, $daily_shift_team_id);

            if(!$teamValidationStatus){
                throw new Exception("The entered team does not match with the team of the Job Card.");
            }

            if (!(isset($quantity))) {
                throw new Exception('Quantity Information is not Received');
            }

            if($bundle_ticket->fpo_operation->operation == "PK" && $bundle_ticket->direction == "OUT"){
                throw new Exception('Packing out tickets are not allowed to scan here');
            }

            if($rejected_reason != "" && $rejected_reason != "0" && $rejected_reason != 0)  {
                $newQcReject = QcReject::insert([
                    'daily_scanning_slot_id' => $daily_scanning_slot_id,
                    'daily_shift_team_id' => $daily_shift_team_id,
                    'bundle_ticket_id' => $bundle_ticket->id,
                    'quantity' => $quantity,
                    'reject_reason' => $rejected_reason,
                    'created_at' => now('Asia/Kolkata'),
                    'updated_at' => now('Asia/Kolkata')
                ]);
            }
            else {

                $ifRejected = DB::table('qc_rejects')
                    ->select('*')
                    ->where('bundle_ticket_id' , '=', $ticketId)
                    ->get();

                foreach ($ifRejected as $if){
                    $available_qty = $available_qty - $if->quantity;
                   // $quantity = $quantity - $if->quantity;
                }

                if($available_qty <= 0){
                    throw new Exception('Error! Quantity is 0');
                }

                try {
                    $socBundle = DB::table('bundle_tickets')
                        ->select('socs.id')
                        ->join('fpo_operations', 'fpo_operations.id', '=', 'bundle_tickets.fpo_operation_id')
                        ->join('fpos', 'fpos.id', '=', 'fpo_operations.fpo_id')
                        ->join('socs', 'socs.id', '=', 'fpos.soc_id')
                        ->where('bundle_tickets.id', '=', $bundle_ticket->id)
                        ->get();

                    $socBox = DB::table('packing_list_details')
                        ->select('packing_list_soc.soc_id')
                        ->join('packing_list_soc', 'packing_list_soc.packing_list_id', '=', 'packing_list_details.packing_list_id')
                        ->where('packing_list_details.id' , '=', $boxId)
                        ->get();

                    $socEquals = false;
                    foreach ($socBox as $sb){
                        if($sb->soc_id == $socBundle[0]->id){
                            $socEquals = true;
                        }
                    }

                    if(!$socEquals){
                        throw new Exception("SOCs of the bundle and box do not match!");
                    }

                }
                catch (Exception $en){
                    throw new \App\Exceptions\GeneralException($en->getMessage());
                }


                $bundle_of_ticket = Bundle::find($bundle_ticket->bundle_id);
                if (!isset($bundle_ticket)) {
                    throw new Exception("Bundle Ticket does not exist.");
                }

                if (!(isset($boxId))) {
                    throw new Exception('Scanning Box Information is not Received');
                }

                $all_tickets = BundleTicket::where('bundle_id', $bundle_ticket->bundle_id)->get();

// need to add a button to see the details of the box
                $xxx = $this->bubbleSort($all_tickets);
                $previousOp = $this->findPreviousOp($ticketId);

                $n = sizeof($xxx);
                for ($i = 0; $i < $n; $i++) {

                    if ($xxx[$i]->scan_quantity == null && $xxx[$i]->id === $bundle_ticket->id) {
                        if ($xxx[$i]->direction == "OUT" && substr($xxx[$i]->fpo_operation->routing_operation->operation_code, 0, 2) == "SW") {
                            throw new Exception("SW - OUT tickets are not allowed to scan.");
                        }
                    }
                }

                if ($previousOp === null) {
                    throw new Exception("Unable to find the scan quantity of previous operation. Please contact a system administrator");
                }

                $swOutOfBundle = null;
                foreach ($all_tickets as $rec) {
                    $op = $rec->fpo_operation->routing_operation->operation_code . " - " . $rec->direction;
                    if (substr($op, 0, 2) === "SW" && $rec->direction === "OUT") {
                        $swOutOfBundle = $rec;
                        
                    }
                }
                $Jobcard_rout = JobCardBundle::where('bundle_id',$bundle_ticket->bundle_id)->first();
                $rout = $Jobcard_rout->job_card->fpo->soc->style->routing_id;
                $op_seq = $bundle_ticket->fpo_operation->routing_operation->wfx_seq;
                foreach ($all_tickets as $rec) {
                    $seq = $rec->fpo_operation->routing_operation->wfx_seq;
                    if($rec->fpo_operation->routing_operation->routing_id == $rout) {
                        if (is_null($rec->scan_quantity) && $op_seq > $seq) {
                            $op = $rec->fpo_operation->routing_operation->operation_code . " - " . $rec->direction;

                            if (substr($op, 0, 1) === "C" || substr($op, 0, 1) === "E") {

                            } else if (substr($op, 0, 2) === "SW" && $rec->direction === "OUT") {
                                //                            $swOutOfBundle = $rec;
                            } else {
                                throw new Exception("Previous Operation Not Scanned " . $op . "");
                            }
                        }
                    }else{
                        throw new Exception("Different Route Code ");
                    }
                }

                if ($swOutOfBundle == null) {
                    throw new Exception("Could not able to find SW-OUT ticket");
                }

                if($swOutOfBundle->scan_quantity >= 0 && $swOutOfBundle->scan_quantity != null){
                    
                    $secondary = DB::table('bundle_ticket_secondaries')
                        ->select('*')
                        ->where('bundle_ticket_secondaries.bundle_ticket_id', '=', $swOutOfBundle->id)
                        ->get();

                    foreach ($secondary as $item) {
                        if($item->carton_id == null){
                            $swOutOfBundle->update([
                                'scan_quantity' => $swOutOfBundle->scan_quantity - $item->scan_quantity,
                                'updated_at' => now('Asia/Kolkata'),
                                "updated_by" => $user_id
                            ]);
                            $deleteSwSec = BundleTicketSecondary::where([['id', '=', $item->id]])->pluck('id')->toArray();
                            BundleTicketSecondaryRepository::deleteRecs($deleteSwSec);
                        }
                    }
//                    throw new Exception("SW-OUT ticket (".$swOutOfBundle->id. ") of this PK-IN ticket has already been scanned.Please delete it to proceed.");
                }

                $this->__validateScan($daily_scanning_slot_id, $daily_shift_team_id);
                //$this->_validateScan($bundle_ticket, $daily_shift_team_id);

                $packing_list_details = PackingListDetail::select('*')
                    ->where('id', $boxId)
                    ->first();


                $pack_ratio_sum = PackingListSoc::where(['packing_list_id' => $packing_list_details->packing_list_id])->sum('pack_ratio');
                if ($pack_ratio_sum == 0) {
                    $pack_ratio_sum = 1;
                }

                $actual_box_qty = 0;
                $size_available = false;
                foreach ($packing_list_details->qty_json as $key => $value) {
                    if (intval($value) > 0 && $key == $bundle_of_ticket->size) {
                        $size_available = true;
                        $actual_box_qty = intval($value) * $pack_ratio_sum;
                    }
                }

                if (!$size_available) {
                    throw new Exception("Size of the bundle (" . $bundle_of_ticket->size . ") is not available in the Box!");
                } else {
                    $bundle = DB::table('bundle_ticket_secondaries')
                        ->select('bundle_ticket_secondaries.*', 'bundle_tickets.id as bundle_ticket_id', 'bundles.size as size_of_bundle')
                        ->join('bundle_tickets', 'bundle_tickets.id', '=', 'bundle_ticket_secondaries.bundle_ticket_id')
                        ->join('fpo_operations', 'fpo_operations.id', '=', 'bundle_tickets.fpo_operation_id')
                        ->join('routing_operations', 'routing_operations.id', '=', 'fpo_operations.routing_operation_id')
                        ->join('bundles', 'bundles.id', '=', 'bundle_tickets.bundle_id')
                        ->where('bundle_ticket_secondaries.carton_id', $boxId)
                        ->Where('routing_operations.operation_code', 'like', '%' . 'PK' . '%')
                        ->where('bundle_tickets.direction', 'IN')
                        ->get();

                    $total_packing_in_for_box = 0;
                    foreach ($bundle as $bun) {
                        if ($bun->size_of_bundle == $bundle_of_ticket->size) {
                            $total_packing_in_for_box += $bun->scan_quantity;
                        }
                    }

                    if ($actual_box_qty - $total_packing_in_for_box >= $quantity) {
                        if (($bundle_ticket->scan_quantity >= 0) && (!is_null($bundle_ticket->scan_quantity))) {
                            if ($bundle_ticket->original_quantity != $bundle_ticket->scan_quantity) {
                                $qr = QcRecoverable::where('bundle_ticket_id', $bundle_ticket->id)->first();
                                if (!is_null($qr)) {
                                    $rec_qty = is_null($qr->recovered_quantity) ? 0 : $qr->recovered_quantity;
                                    if (($rec_qty != $qr->recoverable_quantity) && ($qr->recoverable_quantity != 0)) {

                                        $bundle_ticket->update([
                                            'scan_quantity' => $quantity + $qr->recoverable_quantity,
                                            'updated_by' => $user_id,
                                            'updated_at' => now('Asia/Kolkata'),
                                            'carton_id' => $boxId
                                        ]);

                                        $qr->update([
                                            'updated_at' => $qr->updated_at,
                                            'recovered_quantity' => $qr->recoverable_quantity,
                                            'recoverable_quantity' => 0
                                        ]);
                                    } else {
                                        throw new Exception("Bundle Ticket already scanned");
                                    }
                                } else {
//                                    throw new Exception("Please use Change Quantity option hence this has been partially scanned");
                                }
                            } else {
                                throw new Exception("Bundle Ticket already scanned");
                            }
                            $swOutOfBundle->update([
                                'scan_quantity' => $swOutOfBundle->scan_quantity + $quantity,
                                'scan_date_time' => now('Asia/Kolkata'),
                                'daily_shift_team_id' => $daily_shift_team_id,
                                'daily_scanning_slot_id' => $daily_scanning_slot_id,
                                'updated_by' => $user_id,
                                'carton_id' => $boxId,
                                'updated_at' => now('Asia/Kolkata')
                            ]);
                            $bt_secondary1 = BundleTicketSecondary::insert([
                                'bundle_ticket_id' => $swOutOfBundle->id,
                                'daily_scanning_slot_id' => $daily_scanning_slot_id,
                                'daily_shift_team_id' => $daily_shift_team_id,
                                'packing_list_id' => $packing_list_details->packing_list_id,
                                'bundle_id' => $swOutOfBundle->bundle_id,
                                'original_quantity' => $swOutOfBundle->original_quantity,
                                "scan_quantity" => $quantity,
                                'carton_id' => $boxId,
                                "scan_date_time" => now('Asia/Kolkata'),
                                'created_by' => $user_id,
                                'updated_by' => $user_id,
                                'created_at' => now('Asia/Kolkata'),
                                'updated_at' => now('Asia/Kolkata')
                            ]);

                            $bundle_ticket->update([
                                'scan_quantity' => $bundle_ticket->scan_quantity + $quantity,
                                'packing_list_id' => $packing_list_details->packing_list_id,
                                'scan_date_time' => now('Asia/Kolkata'),
                                'daily_shift_team_id' => $daily_shift_team_id,
                                'daily_scanning_slot_id' => $daily_scanning_slot_id,
                                'updated_by' => $user_id,
                                'carton_id' => $boxId,
                                'updated_at' => now('Asia/Kolkata')
                            ]);
                            $bt_secondary = BundleTicketSecondary::insert([
                                'bundle_ticket_id' => $bundle_ticket->id,
                                'daily_scanning_slot_id' => $daily_scanning_slot_id,
                                'daily_shift_team_id' => $daily_shift_team_id,
                                'packing_list_id' => $packing_list_details->packing_list_id,
                                'bundle_id' => $bundle_ticket->bundle_id,
                                'original_quantity' => $bundle_ticket->original_quantity,
                                "scan_quantity" => $quantity,
                                'carton_id' => $boxId,
                                "scan_date_time" => now('Asia/Kolkata'),
                                'created_by' => $user_id,
                                'updated_by' => $user_id,
                                'created_at' => now('Asia/Kolkata'),
                                'updated_at' => now('Asia/Kolkata')
                            ]);

                        } else {

                            $swOutOfBundle->update([
                                'scan_quantity' => $quantity,
                                'scan_date_time' => now('Asia/Kolkata'),
                                'daily_shift_team_id' => $daily_shift_team_id,
                                'daily_scanning_slot_id' => $daily_scanning_slot_id,
                                'updated_by' => $user_id,
                                'carton_id' => $boxId,
                                'updated_at' => now('Asia/Kolkata')
                            ]);
                            $bt_secondary1 = BundleTicketSecondary::insert([
                                'bundle_ticket_id' => $swOutOfBundle->id,
                                'daily_scanning_slot_id' => $daily_scanning_slot_id,
                                'daily_shift_team_id' => $daily_shift_team_id,
                                'packing_list_id' => $packing_list_details->packing_list_id,
                                'bundle_id' => $swOutOfBundle->bundle_id,
                                'original_quantity' => $swOutOfBundle->original_quantity,
                                "scan_quantity" => $quantity,
                                'carton_id' => $boxId,
                                "scan_date_time" => now('Asia/Kolkata'),
                                'created_by' => $user_id,
                                'updated_by' => $user_id,
                                'created_at' => now('Asia/Kolkata'),
                                'updated_at' => now('Asia/Kolkata')
                            ]);

                            $bundle_ticket->update([
                                'scan_quantity' => $quantity,
                                'scan_date_time' => now('Asia/Kolkata'),
                                'daily_shift_team_id' => $daily_shift_team_id,
                                'daily_scanning_slot_id' => $daily_scanning_slot_id,
                                'updated_by' => $user_id,
                                'carton_id' => $boxId,
                                'updated_at' => now('Asia/Kolkata')
                            ]);

                            $bt_secondary = BundleTicketSecondary::insert([
                                'bundle_ticket_id' => $bundle_ticket->id,
                                'daily_scanning_slot_id' => $daily_scanning_slot_id,
                                'daily_shift_team_id' => $daily_shift_team_id,
                                'packing_list_id' => $packing_list_details->packing_list_id,
                                'bundle_id' => $bundle_ticket->bundle_id,
                                'original_quantity' => $bundle_ticket->original_quantity,
                                "scan_quantity" => $quantity,
                                'carton_id' => $boxId,
                                "scan_date_time" => now('Asia/Kolkata'),
                                'created_by' => $user_id,
                                'updated_by' => $user_id,
                                'created_at' => now('Asia/Kolkata'),
                                'updated_at' => now('Asia/Kolkata')
                            ]);
                            try {
                                $this->_handleJobCardStatus($bundle_ticket);
                            } catch (Exception $e) {
                                throw new \App\Exceptions\GeneralException($e->getMessage());
                            }
                        }


                        $bundle_ticket1 = BundleTicket::find($bundle_ticket->id);
                        $this->_calculateTargetInformationSetup($bundle_ticket, 'ADD');

                    } else {
//                        throw new Exception("Not enough available quantity for size(" . $bundle_of_ticket->size . ") in the box to scan the quantity(" . $quantity . ") of bundle(" . $bundle_ticket->id . ").");
                        throw new Exception("Insufficient Allocated Bundle Qty for Size " . $bundle_of_ticket->size . ".\n Avl. Qty. in the Box- " . ($actual_box_qty - $total_packing_in_for_box) . " \n Qty. of the bundle - " . $quantity . " .");
                    }
                }
            }

            DB::commit();

            return response()->json([
                "status" => "success",
                "data" => ['bundle'=> $bundle_ticket]
            ], 200);


        }
        catch(Exception $ex){
            DB::rollBack();
            throw new \App\Exceptions\GeneralException($ex->getMessage());
        }
    }



    public function getDataBarcode($bundle_ticket_id){
        try{
            $bundle_ticket = BundleTicket::find($bundle_ticket_id);
            $fpo_operation = FpoOperation::find($bundle_ticket->fpo_operation_id);
            $qc_rejects = QcReject::where('bundle_ticket_id' , $bundle_ticket_id)->get();
            $bundle = Bundle::find($bundle_ticket->bundle_id);
            $all_tickets = BundleTicket::where('bundle_id',$bundle_ticket->bundle_id)->get();

            $xxx = $this->bubbleSort($all_tickets);
            $previousOp = $this->findPreviousOp($bundle_ticket_id);

            $n = sizeof($xxx);

            if($bundle_ticket->scan_quantity == $bundle_ticket->original_quantity){
                throw new Exception("Bundle ticket already scanned - ".$bundle_ticket->id. " ");
            }

            for ($i = 0; $i < $n; $i++){
                if($xxx[$i]->scan_quantity == null && $xxx[$i]->id == $bundle_ticket->id) {
                    if ($xxx[$i]->direction == "OUT" && substr($xxx[$i]->fpo_operation->routing_operation->operation_code, 0, 2) == "SW") {
                        throw new Exception("SW - OUT tickets are not allowed to scan.");
                    }
                }
            }

            if($previousOp === null){
                throw new Exception("Unable to find the scan quantity of previous operation. Please contact a system administrator");
            }
            else{
                $qc_rejects_prev = QcReject::where('bundle_ticket_id' , $previousOp->id)->get();
            }

//            $re_re = QcRejectReason::all();
            $re_re = DB::table('qc_reject_reasons')
                ->where('operation', '=', $bundle_ticket->fpo_operation->operation)
                ->where('direction', '=', $bundle_ticket->direction)
                ->get();

            $return = [];

            $return[0]["bundle_ticket"] = $bundle_ticket;
            $return[0]["bundle"] = $bundle;
            $return[0]['fpo_operation'] = $fpo_operation;
            $return[0]["qc_rejects_current"] = $qc_rejects;
            $return[0]["qc_rejects_previous"] = $qc_rejects_prev;
            $return[0]["previous_op"] = $previousOp;
            $return[0]["reject_reasons"] = $re_re;

            return $return;
        }
        catch(Exception $ex){
            throw new \App\Exceptions\GeneralException($ex->getMessage());
        }
    }

    public function getOperationDataBarcode($bundle_ticket_id,$operation ,$direction){
        try{
            $bundle_ticket = DB::table('bundle_tickets')
                                ->select('bundle_tickets.*')
                                ->join('fpo_operations', 'fpo_operations.id', '=' , 'bundle_tickets.fpo_operation_id')
                                ->where('fpo_operations.operation', '=' , $operation)
                                ->where('bundle_tickets.direction' , '=', $direction)
                                ->where('bundle_tickets.bundle_id', '=', $bundle_ticket_id)
                                ->get();

            $bundle_ticket = $bundle_ticket[0];
            $bundle_ticket_id = $bundle_ticket->id;
            $fpo_operation = FpoOperation::find($bundle_ticket->fpo_operation_id);
            $qc_rejects = QcReject::where('bundle_ticket_id' , $bundle_ticket->id)->get();
            $bundle = Bundle::find($bundle_ticket->bundle_id);
            $all_tickets = BundleTicket::where('bundle_id',$bundle_ticket->bundle_id)->get();

            $xxx = $this->bubbleSort($all_tickets);
            $previousOp = $this->findPreviousOp($bundle_ticket->id);

            $n = sizeof($xxx);

            if($bundle_ticket->scan_quantity == $bundle_ticket->original_quantity){
                throw new Exception("Bundle ticket already scanned - ".$bundle_ticket->id. " ");
            }

            for ($i = 0; $i < $n; $i++){
                if($xxx[$i]->scan_quantity == null && $xxx[$i]->id == $bundle_ticket->id) {
                    if ($xxx[$i]->direction == "OUT" && substr($xxx[$i]->fpo_operation->routing_operation->operation_code, 0, 2) == "SW") {
                        throw new Exception("SW - OUT tickets are not allowed to scan.");
                    }
                }
            }

            if($previousOp === null){
                throw new Exception("Unable to find the scan quantity of previous operation. Please contact a system administrator");
            }
            else{
                $qc_rejects_prev = QcReject::where('bundle_ticket_id' , $previousOp->id)->get();
            }

//            $re_re = QcRejectReason::all();
            $re_re = DB::table('qc_reject_reasons')
                ->where('operation', '=', $operation)
                ->where('direction', '=', $bundle_ticket->direction)
                ->get();

            $return = [];

            $return[0]["bundle_ticket"] = $bundle_ticket;
            $return[0]["bundle"] = $bundle;
            $return[0]['fpo_operation'] = $fpo_operation;
            $return[0]["qc_rejects_current"] = $qc_rejects;
            $return[0]["qc_rejects_previous"] = $qc_rejects_prev;
            $return[0]["previous_op"] = $previousOp;
            $return[0]["reject_reasons"] = $re_re;

            return $return;
        }
        catch(Exception $ex){
            throw new \App\Exceptions\GeneralException($ex->getMessage());
        }
    }

    public function findPreviousOp($bundle_ticket_id){
        $previousOp = null;
        $bundle_ticket = BundleTicket::find($bundle_ticket_id);
        $all_tickets = BundleTicket::where('bundle_id',$bundle_ticket->bundle_id)->get();

        $sortedTickets = $this->bubbleSort($all_tickets);
        $n = sizeof($sortedTickets);
        for ($i = 0; $i < $n; $i++){
            if($sortedTickets[$i]->id == $bundle_ticket->id){
                for($j = $i-1; $j >= 0; $j --){
                    if($sortedTickets[$j]->scan_quantity != null && $sortedTickets[$j]->scan_quantity != 0 && $sortedTickets[$j]->scan_quantity != ""){
                        if($sortedTickets[$j]->fpo_operation->operation == "SW" && $sortedTickets[$j]->direction == "OUT") {
                        }
                        else{
                            $previousOp = $sortedTickets[$j];
                            break;
                        }
                    }
                }
            }
        }
        return $previousOp;
    }

    public function getTeamValidationInfo($bundle_ticket_id, $daily_shift_team_id){
        $teamValidation = false;
        $spAvailable = false;
        $bundle_ticket = DB::table('bundle_tickets')//BundleTicket::find($bundle_ticket_id);
            ->select('bundle_tickets.*', 'fpo_operations.operation as operation')
            ->join('fpo_operations', 'fpo_operations.id', '=', 'bundle_tickets.fpo_operation_id')
            ->where('bundle_tickets.id', '=', $bundle_ticket_id)
            ->get();

//        return $bundle_ticket[0]->operation;
       
        $all_tickets = BundleTicket::where('bundle_id',$bundle_ticket[0]->bundle_id)->get();
        
        $sortedTickets = $this->bubbleSort($all_tickets);
        $n = sizeof($sortedTickets);
        for ($i = 0; $i < $n; $i++){
            if($sortedTickets[$i]->fpo_operation->operation == "SP"){
                $spAvailable = true;
            }
        }
        
        if($spAvailable){
            
            if($bundle_ticket[0]->operation == "SP"){
                $teamIdEntered = DB::table('daily_shift_teams')
                    ->select('daily_shift_teams.team_id as team_id', 'teams.code as code')
                    ->join('teams', 'teams.id', '=', 'daily_shift_teams.team_id')
                    ->where('daily_shift_teams.id', '=', $daily_shift_team_id)
                    ->get();

                if(sizeof($teamIdEntered) <= 0){
                    throw new Exception('Please setup daily shift teams and target information.');
                }

                $teamIdJobCard = DB::table('job_card_bundles')
                    ->select('job_cards.team_id as team_id', 'teams.code as code')
                    ->join('job_cards', 'job_cards.id', '=', 'job_card_bundles.job_card_id')
                    ->join('teams', 'teams.id', '=', 'job_cards.team_id')
                    ->where('job_card_bundles.bundle_id', '=', $bundle_ticket[0]->bundle_id)
                    ->first();

                $teamValidationStatus = DB::table('team_validation_status')
                    ->select('team_validation_value')
                    ->where('id', '=', 1)
                    ->get();

                    
                if($teamValidationStatus[0]->team_validation_value == 1){
                    if(json_decode(json_encode($teamIdEntered), true)[0]['team_id'] != json_decode(json_encode($teamIdJobCard), true)['team_id']){
//                    throw new Exception('Team id ('.json_decode(json_encode($teamIdEntered), true)[0]['code'].') does not match with the job card team - '.json_decode(json_encode($teamIdJobCard), true)['code']);
                        $teamValidation = false;
                    }
                    else{
                        $teamValidation = true;
                    }
                }
                else{
                    $teamValidation = true;
                }
            }
            else{
                $teamValidation = true;
            }
        }
        else{
            
            $teamIdEntered = DB::table('daily_shift_teams')
                ->select('daily_shift_teams.team_id as team_id', 'teams.code as code')
                ->join('teams', 'teams.id', '=', 'daily_shift_teams.team_id')
                ->where('daily_shift_teams.id', '=', $daily_shift_team_id)
                ->get();

            if(sizeof($teamIdEntered) <= 0){
                throw new Exception('Please setup daily shift teams and target information.');
            }
            
            $teamIdJobCard = DB::table('job_card_bundles')
                ->select('job_cards.team_id as team_id', 'teams.code as code')
                ->join('job_cards', 'job_cards.id', '=', 'job_card_bundles.job_card_id')
                ->join('teams', 'teams.id', '=', 'job_cards.team_id')
                ->where('job_card_bundles.bundle_id', '=', $bundle_ticket[0]->bundle_id)
                ->first();

                if(is_null($teamIdJobCard)){
                    throw new \App\Exceptions\GeneralException("Job Card Not Found");
                }

            $teamValidationStatus = DB::table('team_validation_status')
                ->select('team_validation_value')
                ->where('id', '=', 1)
                ->get();


            if($teamValidationStatus[0]->team_validation_value == 1){
                if(json_decode(json_encode($teamIdEntered), true)[0]['team_id'] != json_decode(json_encode($teamIdJobCard), true)['team_id']){
//                    throw new Exception('Team id ('.json_decode(json_encode($teamIdEntered), true)[0]['code'].') does not match with the job card team - '.json_decode(json_encode($teamIdJobCard), true)['code']);
                    $teamValidation = false;
                }
                else{
                    $teamValidation = true;
                }
            }
            else{
                $teamValidation = true;
            }

        }

        return $teamValidation;
    }

    public static function getSearchBundleTicket()
    {
        $results = DB::table('bundle_tickets')
            ->select('id', 'scan_quantity')
            ->orderBy('id', 'DESC')
            ->get();

        return $results;
    }

    public static function getSearchByTicketId($ticketId)
    {

        $results = DB::table('bundle_tickets')
            ->select('id', 'scan_quantity')
            ->where('id', '=', $ticketId)
            ->get();

        return $results;
    }

    public function getEditData($ticketId){
        try{
            $bt = DB::table('bundle_tickets')
                ->select('bundle_tickets.*', 'fpo_operations.operation', 'bundles.size as size')
                ->join('fpo_operations', 'fpo_operations.id', '=', 'bundle_tickets.fpo_operation_id')
                ->join('bundles', 'bundles.id', '=', 'bundle_tickets.bundle_id')
                ->where('bundle_tickets.id', '=', $ticketId)
                ->get();

            if(is_null($bt[0]->scan_quantity) && is_null($bt[0]->scan_date_time)){
                $return = $bt; //DB::table('bundle_tickets')
//                    ->select('bundle_tickets.*', 'fpo_operations.operation')
//                    ->join('fpo_operations', 'fpo_operations.id', '=', 'bundle_tickets.fpo_operation_id')
//                    ->where('bundle_tickets.id', '=', $ticketId)
//                    ->get();

                return $return;

            }
            else{
                if($bt[0]->operation == "CT"){
                    throw new \App\Exceptions\GeneralException("CT tickets are not allowed to edit");
                }
                else if($bt[0]->operation == "PK" && $bt[0]->direction == "OUT"){
                    throw new \App\Exceptions\GeneralException("PK OUT tickets are not allowed to edit. Please use the Box Edit option.");
                }
                else {
                    $sec = DB::table('bundle_ticket_secondaries')
                        ->select('bundle_ticket_secondaries.*', 'bundle_tickets.direction as direction', 'fpo_operations.operation as operation', 'daily_scanning_slots.seq_no as slot', 'teams.code as team', 'bundles.size as size', 'daily_shift_teams.current_date as current_date','daily_shift_teams.id as daily_shift_teams_id')
                        ->join('bundle_tickets', 'bundle_tickets.id', '=', 'bundle_ticket_secondaries.bundle_ticket_id')
                        ->join('fpo_operations', 'fpo_operations.id', '=', 'bundle_tickets.fpo_operation_id')
                        ->join('bundles', 'bundles.id', '=', 'bundle_tickets.bundle_id')
                        ->join('daily_shift_teams', 'daily_shift_teams.id', '=', 'bundle_ticket_secondaries.daily_shift_team_id')
                        ->join('daily_scanning_slots', 'daily_scanning_slots.id', '=', 'bundle_ticket_secondaries.daily_scanning_slot_id')
                        ->join('teams', 'teams.id', '=', 'daily_shift_teams.team_id')
                        ->where('bundle_ticket_secondaries.bundle_ticket_id', '=', $ticketId)
                        ->get();

                    return $sec;
                }
            }
        }catch (Exception $e){
            throw new \App\Exceptions\GeneralException($e->getMessage());
        }
    }

    public function getEditDataBox($boxId){
        try{
            $return = [];
            $tickets = DB::table('bundle_ticket_secondaries')
                ->select('bundle_ticket_secondaries.*', 'bundle_tickets.direction as direction', 'fpo_operations.operation as operation', 'daily_scanning_slots.seq_no as slot', 'teams.code as team', 'bundles.size as size')
                ->join('bundle_tickets', 'bundle_tickets.id', '=', 'bundle_ticket_secondaries.bundle_ticket_id')
                ->join('bundles', 'bundles.id', '=', 'bundle_tickets.bundle_id')
                ->join('fpo_operations', 'fpo_operations.id', '=', 'bundle_tickets.fpo_operation_id')
                ->join('daily_shift_teams', 'daily_shift_teams.id', '=', 'bundle_ticket_secondaries.daily_shift_team_id')
                ->join('daily_scanning_slots', 'daily_scanning_slots.id', '=', 'bundle_ticket_secondaries.daily_scanning_slot_id')
                ->join('teams', 'teams.id', '=', 'daily_shift_teams.team_id')
                ->where('bundle_ticket_secondaries.carton_id', '=', $boxId)
                ->where('fpo_operations.operation', '=', 'PK')
                ->get();

            $boxes = DB::table('packing_list_details')
                ->select('*')
                ->where('id', '=', $boxId)
                ->get();

            array_push($return, $tickets);
            array_push($return, $boxes);

            if(sizeof($tickets) <= 0){
                throw new \App\Exceptions\GeneralException("The box has not been scanned yet.");
            }
            else{
                return $return;
            }
        }catch (Exception $e){
            throw new \App\Exceptions\GeneralException($e->getMessage());
        }
    }

    public function getTeams($date){
        try{
            $teams = DB::table('daily_shift_teams')
                ->select('teams.code', 'teams.id')
                ->join('teams', 'teams.id', '=', 'daily_shift_teams.team_id')
                ->where('daily_shift_teams.current_date', '=' , date('Y-m-d',strtotime($date)))
                ->get();

            return $teams;

        }catch (Exception $e){
            throw new \App\Exceptions\GeneralException($e->getMessage());
        }
    }

    public function saveNewTeamBox($boxId, $newTeam, $newDate, $dateModified, $userId){
        try{
            DB::beginTransaction();

            $box = DB::table('packing_list_details')
                ->select('*')
                ->where('id', '=', $boxId)
                ->get();

            if($dateModified){
                DB::table('packing_list_details')
                    ->where('id', '=', $boxId)
                    ->update(['into_wh_time'=> $newDate, 'team_id' => $newTeam]);
            }
            else{
                DB::table('packing_list_details')
                    ->where('id', '=', $boxId)
                    ->update(['team_id' => $newTeam]);
            }
            $bt_secondary = EditBundleLog::insert([
                'box_id' => $boxId,
                'old_into_wh_time' => $box[0]->into_wh_time,
                'new_into_wh_time' => $newDate,
                'old_box_team' => null,
                'new_box_team' => $newTeam,
                "created_at" => now('Asia/Kolkata'),
                "created_by" => $userId
            ]);


            DB::commit();
            return response()->json([
                "status" => "success"
            ], 200);
        }catch (Exception $e){
            DB::rollBack();
            throw new \App\Exceptions\GeneralException($e->getMessage());
        }
    }

    public function saveNewTeam($secId, $newTeam, $newDate, $dateModified, $userId, $reason){
        try{
            DB::beginTransaction();

            $dataArr = [];

            $btSec = DB::table('bundle_ticket_secondaries')
                ->select('bundle_ticket_secondaries.*', 'bundle_tickets.direction as direction', 'fpo_operations.operation as operation')
                ->join('bundle_tickets', 'bundle_tickets.id', '=', 'bundle_ticket_secondaries.bundle_ticket_id')
                ->join('fpo_operations', 'fpo_operations.id', '=', 'bundle_tickets.fpo_operation_id')
                ->where('bundle_ticket_secondaries.id', '=', $secId)
                ->get();

            $dst = DB::table('daily_shift_teams')
                ->select('id')
                ->where('team_id', '=', $newTeam)
                ->where('current_date', '=', date('Y-m-d',strtotime($newDate)))
                ->get();

            $teamValidationStatus = DB::table('team_validation_status')
                ->select('edit_bundle_value')
                ->where('id', '=', 1)
                ->get();
            if($teamValidationStatus[0]->edit_bundle_value == 1) {

                if ($btSec[0]->operation == "PK" && $btSec[0]->direction == 'IN') {
                    $pkOut = DB::table('bundle_ticket_secondaries')
                        ->select('bundle_ticket_secondaries.*')
                        ->join('bundle_tickets', 'bundle_tickets.id', '=', 'bundle_ticket_secondaries.bundle_ticket_id')
                        ->join('fpo_operations', 'fpo_operations.id', '=', 'bundle_tickets.fpo_operation_id')
                        ->join('bundles', 'bundles.id', '=', 'bundle_tickets.bundle_id')
                        ->where('bundles.id', '=', $btSec[0]->bundle_id)
                        ->where('fpo_operations.operation', '=', "PK")
                        ->where('bundle_tickets.direction', '=', "OUT")
                        ->where('bundle_ticket_secondaries.scan_quantity', '=', $btSec[0]->scan_quantity)
                        ->get();

                    $pkInSend = [];
                    $pkOutSend = [];

                    $pkInSend['bundle_ticket_id'] = $btSec[0]->bundle_ticket_id;
                    $pkInSend['sec_id'] = $btSec[0]->id;
                    $pkInSend['old_daily_shift_team_id'] = $btSec[0]->daily_shift_team_id;
                    $pkInSend['new_daily_shift_team_id'] = $dst[0]->id;
                    $pkInSend['old_scan_date'] = $btSec[0]->scan_date_time;
                    $pkInSend['new_scan_date'] = $newDate;
                    $pkInSend['user_id'] = $userId;

                    array_push($dataArr, $pkInSend);

                    if (sizeof($pkOut) > 0) {
                        if (!is_null($pkOut[0]->scan_quantity)) {
                            $pkOutSend['bundle_ticket_id'] = $pkOut[0]->bundle_ticket_id;
                            $pkOutSend['sec_id'] = $pkOut[0]->id;
                            $pkOutSend['old_daily_shift_team_id'] = $pkOut[0]->daily_shift_team_id;
                            $pkOutSend['new_daily_shift_team_id'] = $dst[0]->id;
                            $pkOutSend['old_scan_date'] = $pkOut[0]->scan_date_time;
                            $pkOutSend['new_scan_date'] = $newDate;
                            $pkOutSend['user_id'] = $userId;
                            array_push($dataArr, $pkOutSend);
                        }
                    }


                } else if ($btSec[0]->operation == "PK" && $btSec[0]->direction == 'OUT') {
                    $pkIn = DB::table('bundle_ticket_secondaries')
                        ->select('bundle_ticket_secondaries.*')
                        ->join('bundle_tickets', 'bundle_tickets.id', '=', 'bundle_ticket_secondaries.bundle_ticket_id')
                        ->join('fpo_operations', 'fpo_operations.id', '=', 'bundle_tickets.fpo_operation_id')
                        ->join('bundles', 'bundles.id', '=', 'bundle_tickets.bundle_id')
                        ->where('bundles.id', '=', $btSec[0]->bundle_id)
                        ->where('fpo_operations.operation', '=', "PK")
                        ->where('bundle_tickets.direction', '=', "IN")
                        ->where('bundle_ticket_secondaries.scan_quantity', '=', $btSec[0]->scan_quantity)
                        ->get();

                    $pkInSend = [];
                    $pkOutSend = [];

                    $pkOutSend['bundle_ticket_id'] = $btSec[0]->bundle_ticket_id;
                    $pkOutSend['sec_id'] = $btSec[0]->id;
                    $pkOutSend['old_daily_shift_team_id'] = $btSec[0]->daily_shift_team_id;
                    $pkOutSend['new_daily_shift_team_id'] = $dst[0]->id;
                    $pkOutSend['old_scan_date'] = $btSec[0]->scan_date_time;
                    $pkOutSend['new_scan_date'] = $newDate;
                    $pkOutSend['user_id'] = $userId;
                    $pkOutSend['reason'] = $reason;

                    $pkInSend['bundle_ticket_id'] = $pkIn[0]->bundle_ticket_id;
                    $pkInSend['sec_id'] = $pkIn[0]->id;
                    $pkInSend['old_daily_shift_team_id'] = $pkIn[0]->daily_shift_team_id;
                    $pkInSend['new_daily_shift_team_id'] = $dst[0]->id;
                    $pkInSend['old_scan_date'] = $pkIn[0]->scan_date_time;
                    $pkInSend['new_scan_date'] = $newDate;
                    $pkInSend['user_id'] = $userId;
                    $pkInSend['reason'] = $reason;

                    array_push($dataArr, $pkInSend);
                    array_push($dataArr, $pkOutSend);
                } else {
                    $pkOutSend = [];

                    $pkOutSend['bundle_ticket_id'] = $btSec[0]->bundle_ticket_id;
                    $pkOutSend['sec_id'] = $btSec[0]->id;
                    $pkOutSend['old_daily_shift_team_id'] = $btSec[0]->daily_shift_team_id;
                    $pkOutSend['new_daily_shift_team_id'] = $dst[0]->id;
                    $pkOutSend['old_scan_date'] = $btSec[0]->scan_date_time;
                    $pkOutSend['new_scan_date'] = $newDate;
                    $pkOutSend['user_id'] = $userId;
                    $pkOutSend['reason'] = $reason;
                    array_push($dataArr, $pkOutSend);
                }
            }
            else{
                $pkOutSend = [];

                $pkOutSend['bundle_ticket_id'] = $btSec[0]->bundle_ticket_id;
                $pkOutSend['sec_id'] = $btSec[0]->id;
                $pkOutSend['old_daily_shift_team_id'] = $btSec[0]->daily_shift_team_id;
                $pkOutSend['new_daily_shift_team_id'] = $dst[0]->id;
                $pkOutSend['old_scan_date'] = $btSec[0]->scan_date_time;
                $pkOutSend['new_scan_date'] = $newDate;
                $pkOutSend['user_id'] = $userId;
                $pkOutSend['reason'] = $reason;
                array_push($dataArr, $pkOutSend);
            }

            $x = $this->updateTeamDate($dataArr, $dateModified);

            if($x){
                DB::commit();
                return response()->json([
                    "status" => "success"
                ], 200);
            }
            else{
                DB::rollBack();
                return response()->json([
                    "status" => "error"
                ], 201);
            }
        }
        catch (Exception $e){
            DB::rollBack();
            throw new \App\Exceptions\GeneralException($e->getMessage());
        }
    }

    private function updateTeamDate($dataArr, $dateModified){
        try{
            DB::beginTransaction();

            foreach ($dataArr as $da) {
                $bt_secondary = EditBundleLog::insert([
                    'bundle_ticket_id' => $da['bundle_ticket_id'],
                    'bundle_ticket_secondary_id' => $da['sec_id'],
                    'old_daily_shift_team' => $da['old_daily_shift_team_id'],
                    'new_daily_shift_team' => $da['new_daily_shift_team_id'],
                    'old_scan_date' => $da['old_scan_date'],
                    'new_scan_date' => $da['new_scan_date'],
                    "created_at" => now('Asia/Kolkata'),
                    "created_by" => $da['user_id'],
                    "reason" => $da['reason']
                ]);

                if ($dateModified) {
                    DB::table('bundle_tickets')
                        ->where('id', $da['bundle_ticket_id'])
                        ->update(["daily_shift_team_id" => $da['new_daily_shift_team_id'], "scan_date_time" => $da['new_scan_date']]);

                    DB::table('bundle_ticket_secondaries')
                        ->where('id', $da['sec_id'])
                        ->update(["daily_shift_team_id" => $da['new_daily_shift_team_id'], "scan_date_time" => $da['new_scan_date']]);
                } else {
                    DB::table('bundle_tickets')
                        ->where('id', $da['bundle_ticket_id'])
                        ->update(["daily_shift_team_id" =>  $da['new_daily_shift_team_id']]);

                    DB::table('bundle_ticket_secondaries')
                        ->where('id', $da['sec_id'])
                        ->update(["daily_shift_team_id" =>  $da['new_daily_shift_team_id']]);
                }
            }

            DB::commit();
            return true;
        }
        catch (Exception $e){
            DB::rollBack();
            return false;
        }
    }

    public function getLocationById($location_id){
        try{
            $location = DB::table('locations')
                ->select('locations.location_name', 'locations.id')
                ->where('id',$location_id)
                ->first();

                return response()->json(
                    [
                        'status' => 'success',
                        'data' => $location,
                    ],
                    200
                );

        }catch (Exception $e){
            throw new \App\Exceptions\GeneralException($e->getMessage());
        }
    }
}
