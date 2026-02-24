<?php

namespace App\Http\Repositories;

use App\BundleTicket;
use App\BundleTicketSecondary;
use App\DailyShiftTeam;
use App\DailyTeamSlotTarget;
use App\Exceptions\GeneralException;
use App\FpoOperation;
use App\Http\Validators\BundleTicketCreateValidator;
use App\Http\Validators\BundleTicketSecondaryCreateValidator;
use App\JobCard;
use App\JobCardBundle;
use App\QcRecoverable;
use App\QcReject;
use App\RoutingOperation;
use Carbon\Carbon;
use App\Bundle;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Exception;

class BundleTicketSecondaryRepository
{
    public static function createRec(array $rec)
    {
        try {
            $model = BundleTicketSecondary::insert($rec);
        } catch (Exception $e) {
            throw new \App\Exceptions\GeneralException($e->getMessage());
        }

        return response()->json(
            [
                'status' => 'success',
                'data' => $model,
            ],
            200
        );

    }


    private function _handleJobCardStatus($bundle_ticket)
    {
        $job_card = JobCard::select('job_cards.*')
            ->join('job_card_bundles', 'job_card_bundles.job_card_id', '=', 'job_cards.id')
            ->join('bundles', 'job_card_bundles.bundle_id', '=', 'bundles.id')
            ->join('bundle_tickets', 'bundle_tickets.bundle_id', '=', 'bundles.id')
            ->where('bundle_tickets.id', $bundle_ticket->id)
            ->first();

        $jj = JobCard::find($job_card->id);

        if (!(is_null($job_card))) {
            if (!(($job_card->status == 'Issued') || ($job_card->status == 'InProgress'))) {
                throw new Exception('Connect Job Card No - ' . $job_card->id . ' is not issued to Production. Scanning is not allowed');
            }

            $op_n_dir = $bundle_ticket->fpo_operation->routing_operation->operation_code . "-" . $bundle_ticket->direction;

            if (($job_card->status == 'Issued') && ($op_n_dir == 'SW100001-IN')) {
                JobCardRepository::fsmProgress($jj);
            }
            if (($job_card->status == 'InProgress') && ($op_n_dir == 'PK100001-IN')) {
                // check whether all the BT's of all the Bundles of this JC have SW100001-OUT scanned
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

    public static function deleteRecs(array $recs)
    {
        
        BundleTicketSecondary::destroy($recs);
    }


    public function createRecCheckedOld(array $recS, array $yy, $bundle_ticket_id, $user_id)
    {
        try {
            $sc_qty = 0;
            $pack_list_id = 0;
            $scan_date = null;
            $daily_scanning_slot_id = 0;
            $daily_shift_team_id = 0;
            DB::beginTransaction();

            $bundle_ticket = BundleTicket::find($bundle_ticket_id);
            if (!isset($bundle_ticket)) {
                throw new Exception("Bundle Ticket does not exist.");
            }

            foreach ($recS as $r) {
                // print_r($r);
                $sc_qty = $r['scan_quantity'];
                $pack_list_id = $r['packing_list_id'];
                $scan_date = now('Asia/Kolkata');
                $daily_scanning_slot_id = $r['daily_scanning_slot_id'];
                $daily_shift_team_id = $r['daily_shift_team_id'];
                $created_by = $user_id;
                $updated_by = $user_id;

            }
//            $Jobcard_rout = JobCardBundle::where('bundle_id',$bundle_ticket->bundle_id)->first();
//            $rout = $Jobcard_rout->job_card->fpo->soc->style->routing_id;
//
//            $Jobcard_rout = JobCardBundle::where('bundle_id',$bundle_ticket->bundle_id)->first();
//            $rout = $Jobcard_rout->job_card->fpo->soc->style->routing_id;


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
                                'updated_by' => $user_id,
                                'updated_at' => now('Asia/Kolkata')
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
                        //throw new Exception("No Recoverable Bundles to scan.");
                    }
                } else {
                    throw new Exception("Bundle Ticket already scanned");
                }

                if(is_null($bundle_ticket->scan_quantity)) {
                    $bundle_ticket->update([
                        'scan_quantity' => $sc_qty,
                        'packing_list_id' => $pack_list_id,
                        'scan_date_time' => now('Asia/Kolkata'),
                        'daily_shift_team_id' => $daily_shift_team_id,
                        'daily_scanning_slot_id' => $daily_scanning_slot_id,
                        'updated_by' => $user_id,
                        'updated_at' => now('Asia/Kolkata'),
                        'created_at' => now('Asia/Kolkata'),
                    ]);
                }
                else{
                    $bundle_ticket->update([
                        'scan_quantity' => $bundle_ticket->scan_quantity + $sc_qty,
                        'packing_list_id' => $pack_list_id,
                        'scan_date_time' => now('Asia/Kolkata'),
                        'daily_shift_team_id' => $daily_shift_team_id,
                        'daily_scanning_slot_id' => $daily_scanning_slot_id,
                        'updated_at' => now('Asia/Kolkata'),
                        'created_at' => now('Asia/Kolkata'),
                        'updated_by' => $user_id
                    ]);
                }
            } else {
                if(is_null($bundle_ticket->scan_quantity)) {
                    $bundle_ticket->update([
                        'scan_quantity' => $sc_qty,
                        'packing_list_id' => $pack_list_id,
                        'scan_date_time' => now('Asia/Kolkata'),
                        'daily_shift_team_id' => $daily_shift_team_id,
                        'daily_scanning_slot_id' => $daily_scanning_slot_id,
                        'updated_at' => now('Asia/Kolkata'),
                        'created_at' => now('Asia/Kolkata'),
                        'updated_by' => $user_id
                    ]);
                }
                else{
                    $bundle_ticket->update([
                        'scan_quantity' => $bundle_ticket->scan_quantity + $sc_qty,
                        'packing_list_id' => $pack_list_id,
                        'scan_date_time' => now('Asia/Kolkata'),
                        'daily_shift_team_id' => $daily_shift_team_id,
                        'daily_scanning_slot_id' => $daily_scanning_slot_id,
                        'updated_at' => now('Asia/Kolkata'),
                        'created_at' => now('Asia/Kolkata'),
                        'updated_by' => $user_id
                    ]);
                }

                try {
                    $this->_handleJobCardStatus($bundle_ticket);
                }catch (Exception $e){
                    throw new \App\Exceptions\GeneralException($e->getMessage());
                }
            }

            $bundle_ticket_secondary = BundleTicketSecondary::where([['bundle_ticket_id', '=', $bundle_ticket_id], ['packing_list_id', '=', $pack_list_id]])->first();
            BundleTicketSecondary::insert($yy);


            $this->_calculateTargetInformationSetup($bundle_ticket, 'ADD');

            DB::commit();
            return response()->json(["status" => "success"], 200);
        } catch (Exception $e) {
            DB::rollBack();
            throw new \App\Exceptions\GeneralException($e->getMessage());
        }

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

    public function createRecChecked(array $recX, $bundle_ticket_id, $user_id,$rejected_qty, $rejected_reason)
    {
        try {
            $sc_qty = 0;
            $pack_list_id = 0;
            $scan_date = null;
            $daily_scanning_slot_id = 0;
            $daily_shift_team_id = 0;


            DB::beginTransaction();
            $bundle_ticket = BundleTicket::find($bundle_ticket_id);

            $teamValidationStatus = $this->getTeamValidationInfo($bundle_ticket_id, $recX[0]['daily_shift_team_id']);

            if(!$teamValidationStatus){
                throw new Exception("The entered team does not match with the team of the Job Card.");
            }

            if($bundle_ticket->fpo_operation->operation == "PK" && $bundle_ticket->direction == "OUT"){
                throw new Exception('Packing out tickets are not allowed to scan here');
            }

            if($rejected_reason != "" && $rejected_reason != "0" && $rejected_reason != 0) {
                $newQcReject = QcReject::insert([
                    'daily_scanning_slot_id' => $recX[0]['daily_scanning_slot_id'],
                    'daily_shift_team_id' => $recX[0]['daily_shift_team_id'],
                    'bundle_ticket_id' => $bundle_ticket->id,
                    'quantity' => $recX[0]['scan_quantity'],
                    'reject_reason' => $rejected_reason,
                    'created_at' => now('Asia/Kolkata'),
                    'updated_at' => now('Asia/Kolkata')
                ]);
            }
            else {

                if (!isset($bundle_ticket)) {
                    throw new Exception("Bundle Ticket does not exist.");
                }

                foreach ($recX as $r) {
                    // print_r($r);
                    $sc_qty = $r['scan_quantity'];
                    $pack_list_id = $r['packing_list_id'];
                    $scan_date = $r['scan_date_time'];//now('Asia/Kolkata');
                    $daily_scanning_slot_id = $r['daily_scanning_slot_id'];
                    $daily_shift_team_id = $r['daily_shift_team_id'];
                    $created_by = $user_id;
                    $updated_by = $user_id;

                }

                $dailyShiftTeam = DB::table('daily_shift_teams')
                    ->select('*')
                    ->where('id', '=', $daily_shift_team_id)
                    ->get();

                $nowDateYMD = $dailyShiftTeam[0]->current_date;// $nowDate->format("Y-m-d");
                $receivedDate = new \DateTime($scan_date);
                $receivedDateYMD = $receivedDate->format("Y-m-d");

                if($nowDateYMD > $receivedDateYMD){
                    throw new Exception("Scanning is not allowed for backdates.");
                }

                ///////////////////////   validate Previous Operation   ///////////////////
                $all_tickets = BundleTicket::where('bundle_id', $bundle_ticket->bundle_id)->get();


                $xxx = $this->bubbleSort($all_tickets);
                $previousOp = $this->findPreviousOp($bundle_ticket_id);

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
                                $swOutOfBundle = $rec;
                            } else {
                                throw new Exception("Previous Operation Not Scanned " . $op . "");
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
                                    'updated_by' => $user_id,
                                    'updated_at' => now('Asia/Kolkata')
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
                            //throw new Exception("No Recoverable Bundles to scan.");
                        }
                    } else {
                        throw new Exception("Bundle Ticket already scanned");
                    }

                    if (is_null($bundle_ticket->scan_quantity)) {
                        $bundle_ticket->update([
                            'scan_quantity' => $sc_qty,
                            'packing_list_id' => $pack_list_id,
                            'scan_date_time' => now('Asia/Kolkata'),
                            'daily_shift_team_id' => $daily_shift_team_id,
                            'daily_scanning_slot_id' => $daily_scanning_slot_id,
                            'updated_by' => $user_id,
                            'updated_at' => now('Asia/Kolkata')
                        ]);
                    } else {
                        $bundle_ticket->update([
                            'scan_quantity' => $bundle_ticket->scan_quantity + $sc_qty,
                            'packing_list_id' => $pack_list_id,
                            'scan_date_time' => now('Asia/Kolkata'),
                            'daily_shift_team_id' => $daily_shift_team_id,
                            'daily_scanning_slot_id' => $daily_scanning_slot_id,
                            'updated_by' => $user_id,
                            'updated_at' => now('Asia/Kolkata')
                        ]);
                    }
                } else {
                    if (!is_null($bundle_ticket->scan_quantity)) {
                        $bundle_ticket->update([
                            'scan_quantity' => $sc_qty,
                            'packing_list_id' => $pack_list_id,
                            'scan_date_time' => now('Asia/Kolkata'),
                            'daily_shift_team_id' => $daily_shift_team_id,
                            'daily_scanning_slot_id' => $daily_scanning_slot_id,
                            'updated_by' => $user_id,
                            'updated_at' => now('Asia/Kolkata')
                        ]);
                    } else {
                        $bundle_ticket->update([
                            'scan_quantity' => $bundle_ticket->scan_quantity + $sc_qty,
                            'packing_list_id' => $pack_list_id,
                            'scan_date_time' => now('Asia/Kolkata'),
                            'daily_shift_team_id' => $daily_shift_team_id,
                            'daily_scanning_slot_id' => $daily_scanning_slot_id,
                            'updated_by' => $user_id,
                            'updated_at' => now('Asia/Kolkata')
                        ]);
                    }

                    try {
                        $this->_handleJobCardStatus($bundle_ticket);
                    } catch (Exception $e) {
                        throw new \App\Exceptions\GeneralException($e->getMessage());
                    }
                }

                $bundle_ticket_secondary = BundleTicketSecondary::where([['bundle_ticket_id', '=', $bundle_ticket_id], ['packing_list_id', '=', $pack_list_id]])->first();

                if (!is_null($bundle_ticket_secondary)) {
                    $model = BundleTicketSecondary::insert($recX);
                } else {
                    $model = BundleTicketSecondary::insert($recX);
                }

                $bundle_ticket1 = BundleTicket::find($bundle_ticket_id);
                $this->_calculateTargetInformationSetup($bundle_ticket, 'ADD');
            }
            DB::commit();
            return response()->json(["status" => "success"], 200);
        } catch (Exception $e) {
            DB::rollBack();
            throw new \App\Exceptions\GeneralException($e->getMessage());
        }

    }


    public function createRecCheckedOPD(array $recX, $bundle_ticket_id, $user_id,$rejected_qty, $rejected_reason,$operation,$direction,$location)
    {
        try {

            $bundle_ticket = DB::table('bundle_tickets')
            ->select('bundle_tickets.*')
            ->join('fpo_operations', 'fpo_operations.id', '=' , 'bundle_tickets.fpo_operation_id')
            ->where('fpo_operations.operation', '=' , $operation)
            ->where('bundle_tickets.direction' , '=', $direction)
            ->where('bundle_tickets.bundle_id', '=', $bundle_ticket_id)
            ->first();

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

                DB::table('bundles')->where('id','=',$bundle_ticket_id)->update(['location_id'=>$ranala_location->id]);
               
            }
            
            $bundle_ticket_id = $bundle_ticket->id;


            $sc_qty = 0;
            $pack_list_id = 0;
            $scan_date = null;
            $daily_scanning_slot_id = 0;
            $daily_shift_team_id = 0;


            DB::beginTransaction();
            $bundle_ticket = BundleTicket::find($bundle_ticket_id);

            $teamValidationStatus = true;
            if(strtoupper($operation) != "EA"){
                $teamValidationStatus = $this->getTeamValidationInfo($bundle_ticket_id, $recX[0]['daily_shift_team_id']);
            }

            if(!$teamValidationStatus){
                throw new Exception("The entered team does not match with the team of the Job Card.");
            }

            if($bundle_ticket->fpo_operation->operation == "PK" && $bundle_ticket->direction == "OUT"){
                throw new Exception('Packing out tickets are not allowed to scan here');
            }

            if($rejected_reason != "" && $rejected_reason != "0" && $rejected_reason != 0) {
                $this->_validateScan($bundle_ticket, $recX[0]['daily_shift_team_id']);

                $newQcReject = QcReject::insert([
                    'daily_scanning_slot_id' => $recX[0]['daily_scanning_slot_id'],
                    'daily_shift_team_id' => $recX[0]['daily_shift_team_id'],
                    'bundle_ticket_id' => $bundle_ticket->id,
                    'quantity' => $recX[0]['scan_quantity'],
                    'reject_reason' => $rejected_reason,
                    'created_at' => now('Asia/Kolkata'),
                    'updated_at' => now('Asia/Kolkata')
                ]);
            }
            else {

                //////////////////////////

             if(strtoupper($operation) == "EA" && strtoupper($direction) == "IN"){
                $locationObj = DB::table('locations')->select('*')
                ->where('id','=',$location)
                ->first();

                if(is_null($locationObj)){
                    throw new Exception("Please Enter Valid Location");
                }else{
                    if($locationObj->site == "EA_Send"){
                        DB::table('bundles')->where('id','=',$bundle_ticket_id)->update(['location_id'=>$location]);
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

                ////////////////////////

                if (!isset($bundle_ticket)) {
                    throw new Exception("Bundle Ticket does not exist.");
                }

                foreach ($recX as $r) {
                    // print_r($r);
                    $sc_qty = $r['scan_quantity'];
                    $pack_list_id = $r['packing_list_id'];
                    $scan_date = $r['scan_date_time'];//now('Asia/Kolkata');
                    $daily_scanning_slot_id = $r['daily_scanning_slot_id'];
                    $daily_shift_team_id = $r['daily_shift_team_id'];
                    $created_by = $user_id;
                    $updated_by = $user_id;

                }

                $dailyShiftTeam = DB::table('daily_shift_teams')
                    ->select('*')
                    ->where('id', '=', $daily_shift_team_id)
                    ->get();

                $nowDateYMD = $dailyShiftTeam[0]->current_date;// $nowDate->format("Y-m-d");
                $receivedDate = new \DateTime($scan_date);
                $receivedDateYMD = $receivedDate->format("Y-m-d");

                if($nowDateYMD > $receivedDateYMD){
                    throw new Exception("Scanning is not allowed for backdates.");
                }

                ///////////////////////   validate Previous Operation   ///////////////////
                $all_tickets = BundleTicket::where('bundle_id', $bundle_ticket->bundle_id)->get();


                $xxx = $this->bubbleSort($all_tickets);
                $previousOp = $this->findPreviousOp($bundle_ticket_id);

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
                // $Jobcard_rout = JobCardBundle::where('bundle_id',$bundle_ticket->bundle_id)->first();
                // $rout = $Jobcard_rout->job_card->fpo->soc->style->routing_id;

                $style_route = Bundle::select('styles.routing_id')
                ->join('fpo_cut_plans','fpo_cut_plans.fppo_id','=','bundles.fppo_id')
                ->join('fpos','fpos.id','=','fpo_cut_plans.fpo_id')
                ->join('socs','socs.id','=','fpos.soc_id')
                ->join('styles','styles.id','=','socs.style_id')
                ->first();
    
                $rout  = $style_route ->routing_id;

                $op_seq = $bundle_ticket->fpo_operation->routing_operation->wfx_seq;
                foreach ($all_tickets as $rec) {
                    $seq = $rec->fpo_operation->routing_operation->wfx_seq;
                    if($rec->fpo_operation->routing_operation->routing_id == $rout) {
                        if (is_null($rec->scan_quantity) && $op_seq > $seq) {
                            $op = $rec->fpo_operation->routing_operation->operation_code . " - " . $rec->direction;

                            if (substr($op, 0, 1) === "C" || substr($op, 0, 1) === "E") {

                            } else if (substr($op, 0, 2) === "SW" && $rec->direction === "OUT") {
                                $swOutOfBundle = $rec;
                            } else {
                                throw new Exception("Previous Operation Not Scanned123 " . $op . "");
                            }
                        }
                    }else{
                        throw new Exception("Different Route Code ");
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
                                    'updated_by' => $user_id,
                                    'updated_at' => now('Asia/Kolkata')
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
                            //throw new Exception("No Recoverable Bundles to scan.");
                        }
                    } else {
                        throw new Exception("Bundle Ticket already scanned");
                    }

                    if (is_null($bundle_ticket->scan_quantity)) {
                        $bundle_ticket->update([
                            'scan_quantity' => $sc_qty,
                            'packing_list_id' => $pack_list_id,
                            'scan_date_time' => now('Asia/Kolkata'),
                            'daily_shift_team_id' => $daily_shift_team_id,
                            'daily_scanning_slot_id' => $daily_scanning_slot_id,
                            'updated_by' => $user_id,
                            'updated_at' => now('Asia/Kolkata')
                        ]);
                    } else {
                        $bundle_ticket->update([
                            'scan_quantity' => $bundle_ticket->scan_quantity + $sc_qty,
                            'packing_list_id' => $pack_list_id,
                            'scan_date_time' => now('Asia/Kolkata'),
                            'daily_shift_team_id' => $daily_shift_team_id,
                            'daily_scanning_slot_id' => $daily_scanning_slot_id,
                            'updated_by' => $user_id,
                            'updated_at' => now('Asia/Kolkata')
                        ]);
                    }
                } else {
                    if (!is_null($bundle_ticket->scan_quantity)) {
                        $bundle_ticket->update([
                            'scan_quantity' => $sc_qty,
                            'packing_list_id' => $pack_list_id,
                            'scan_date_time' => now('Asia/Kolkata'),
                            'daily_shift_team_id' => $daily_shift_team_id,
                            'daily_scanning_slot_id' => $daily_scanning_slot_id,
                            'updated_by' => $user_id,
                            'updated_at' => now('Asia/Kolkata')
                        ]);
                    } else {
                        $bundle_ticket->update([
                            'scan_quantity' => $bundle_ticket->scan_quantity + $sc_qty,
                            'packing_list_id' => $pack_list_id,
                            'scan_date_time' => now('Asia/Kolkata'),
                            'daily_shift_team_id' => $daily_shift_team_id,
                            'daily_scanning_slot_id' => $daily_scanning_slot_id,
                            'updated_by' => $user_id,
                            'updated_at' => now('Asia/Kolkata')
                        ]);
                    }

                    try {
                        if(strtoupper($operation) != "EA"){
                            $this->_handleJobCardStatus($bundle_ticket);
                        }
                    } catch (Exception $e) {
                        throw new \App\Exceptions\GeneralException($e->getMessage());
                    }
                }

                $bundle_ticket_secondary = BundleTicketSecondary::where([['bundle_ticket_id', '=', $bundle_ticket_id], ['packing_list_id', '=', $pack_list_id]])->first();

                $recX[0]['bundle_ticket_id'] = $bundle_ticket->id;
                if (!is_null($bundle_ticket_secondary)) {
                    $model = BundleTicketSecondary::insert($recX);
                } else {
                    $model = BundleTicketSecondary::insert($recX);
                }

                $bundle_ticket1 = BundleTicket::find($bundle_ticket_id);
                $this->_calculateTargetInformationSetup($bundle_ticket, 'ADD');
            }
            DB::commit();
            return response()->json(["status" => "success"], 200);
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
                        $previousOp = $sortedTickets[$j];
                        break;
                    }
                }
            }
        }

        return $previousOp;
    }


}
