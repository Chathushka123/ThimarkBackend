<?php
namespace App\Http\Repositories;

use App\DailyScanningSlot;
use App\DailyShift;
use App\DowntimeLog;
use App\DailyShiftTeam;
use App\DashboardTemplate;
use App\DailyTeamSlotTarget;
use App\ShiftDetail;
use App\FrRecord;
use App\JobCard;
use App\JobCardBundle;
use App\Team;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Exception;
use Carbon\Carbon;
use Illuminate\Container\Util;
use Illuminate\Support\Facades\Log;

class QueriesRepository
{
    public function getDashBoardData($vsm_code, $supevisor_code, $current_date_time,$shift,$dashboard){
        
        Log::info($current_date_time);
       // $daily_shift = DailyShift::where('start_date_time', '<=', Carbon::parse($current_date_time))->where('end_date_time', '>=', Carbon::parse($current_date_time))->first();

       if($dashboard != null){
        $daily_shift = DailyShift::where('start_date_time', '<=', Carbon::parse($current_date_time))->where('end_date_time', '>=', Carbon::parse($current_date_time))->first();
       // $daily_shift = DailyShift::where('start_date_time', '<=', date('Y-m-d h:i:s'))->where('end_date_time', '>=', date('Y-m-d h:i:s'))->first();
       }
        
        else{
            // $daily_shift = DailyShift::where('start_date_time', '<=', Carbon::parse($current_date_time))->where('end_date_time', '>=', Carbon::parse($current_date_time))->first();
            $daily_shift = DailyShift::where('id', '=', $shift)->first(); 
        }
        
         
        Log::info($daily_shift);

        if(!(empty($daily_shift))){
            
        $daily_scanning_slots = DailyScanningSlot::where('daily_shift_id', $daily_shift->id)->get(); 
        Log::info($daily_scanning_slots);

        $teams = $this->_getTeams($vsm_code, $supevisor_code, $daily_shift,$dashboard);
        
        Log::info("TEAMS");
        Log::info($teams);
        
        
        $pie_data = $this->getPieChartData($teams, $daily_scanning_slots, Carbon::parse($current_date_time),$daily_shift);
        $qty_data = $this->getQtyVariance($teams, $daily_scanning_slots);
        $eff_data = $this->getEffVariance($teams, $daily_scanning_slots, Carbon::parse($current_date_time),$daily_shift);
        $sah_data = $this->getSahVariance($teams, $daily_scanning_slots, Carbon::parse($current_date_time),$daily_shift);
        return response()->json(["pie_data" => $pie_data, "qty_data" => $qty_data, "eff_data" => $eff_data, "sah_data" => $sah_data], 200);
        }
        else{
            throw new Exception("No Data Found for the given iputs, Current Date Time, VSM , Supervisor");
        }
    }

    private function _getTeams($vsm_code, $supevisor_code, $daily_shift,$dashboard){

        $daily_shift_teams_ids = DailyShiftTeam::where('daily_shift_id', $daily_shift->id)->pluck('id')->toArray();
        Log::info("Daily Shift Teams IDs");
        Log::info($daily_shift_teams_ids);

        if($dashboard != null){
            $team_details = Team::select(
                'teams.id as team_id',
                'teams.code as team_code',
                'teams.description as description',
                'daily_shift_teams.id as daily_shift_team_id',
                'daily_shift_teams.planned_sah as planned_sah',
                'daily_shift_teams.planned_efficient as planned_efficient'
            )
            ->join('daily_shift_teams', 'daily_shift_teams.team_id', '=', 'teams.id')
            ->join('dashboard_template_details', 'dashboard_template_details.team_id', '=', 'teams.id')
            ->join('dashboard_templates', 'dashboard_templates.id', '=', 'dashboard_template_details.dashboard_id')
            ->whereIn('daily_shift_teams.id', $daily_shift_teams_ids)
            ->where('dashboard_templates.template_name', $dashboard)
            ->get(); 
        }else{

            if($vsm_code == "ALL" && $supevisor_code == "ALL")
            {
                $team_details = Team::select(
                    'teams.id as team_id',
                    'teams.code as team_code',
                    'teams.description as description',
                    'daily_shift_teams.id as daily_shift_team_id',
                    'daily_shift_teams.planned_sah as planned_sah',
                    'daily_shift_teams.planned_efficient as planned_efficient'
                )
                ->join('daily_shift_teams', 'daily_shift_teams.team_id', '=', 'teams.id')
                ->whereIn('daily_shift_teams.id', $daily_shift_teams_ids)
                ->get(); 
            }

            if($vsm_code != "ALL" && $supevisor_code == "ALL")
            {
                $team_details = Team::select(
                    'teams.id as team_id',
                    'teams.code as team_code',
                    'teams.description as description',
                    'daily_shift_teams.id as daily_shift_team_id',
                    'daily_shift_teams.planned_sah as planned_sah',
                    'daily_shift_teams.planned_efficient as planned_efficient'
                )
                ->join('daily_shift_teams', 'daily_shift_teams.team_id', '=', 'teams.id')
                ->whereIn('daily_shift_teams.id', $daily_shift_teams_ids)
                ->where('teams.vsm_code', $vsm_code)
                ->get(); 
            }

            if($vsm_code != "ALL" && $supevisor_code != "ALL")
            {
                $team_details = Team::select(
                    'teams.id as team_id',
                    'teams.code as team_code',
                    'teams.description as description',
                    'daily_shift_teams.id as daily_shift_team_id'
                )
                ->join('daily_shift_teams', 'daily_shift_teams.team_id', '=', 'teams.id')
                ->join('employees', 'employees.id', '=', 'teams.supervisor_id')
                ->whereIn('daily_shift_teams.id', $daily_shift_teams_ids)
                ->where('teams.vsm_code', $vsm_code)
                ->where('employees.emp_code', $supevisor_code)
                ->get(); 
            }
        }

        return $team_details;
        
    }

    public function _getTeamsPerShift($vsm_code, $supevisor_code, $daily_shift,$dashboard){

        $daily_shift_teams_ids = DailyShiftTeam::where('daily_shift_id', $daily_shift)->pluck('id')->toArray();
        Log::info("Daily Shift Teams IDs");
        Log::info($daily_shift_teams_ids);

        if($vsm_code == "ALL" && $supevisor_code == "ALL")
        {
            $team_details = Team::select(
                'teams.id as team_id',
                'teams.code as team_code',
                'teams.description as description',
                'daily_shift_teams.id as daily_shift_team_id',
                'daily_shift_teams.planned_sah as planned_sah',
                'daily_shift_teams.planned_efficient as planned_efficient'
            )
            ->join('daily_shift_teams', 'daily_shift_teams.team_id', '=', 'teams.id')
            ->whereIn('daily_shift_teams.id', $daily_shift_teams_ids)
            ->get(); 
        }

        if($vsm_code != "ALL" && $supevisor_code == "ALL")
        {
            $team_details = Team::select(
                'teams.id as team_id',
                'teams.code as team_code',
                'teams.description as description',
                'daily_shift_teams.id as daily_shift_team_id',
                'daily_shift_teams.planned_sah as planned_sah',
                'daily_shift_teams.planned_efficient as planned_efficient'
            )
            ->join('daily_shift_teams', 'daily_shift_teams.team_id', '=', 'teams.id')
            ->whereIn('daily_shift_teams.id', $daily_shift_teams_ids)
            ->where('teams.vsm_code', $vsm_code)
            ->get(); 
        }

        if($vsm_code != "ALL" && $supevisor_code != "ALL")
        {
            $team_details = Team::select(
                'teams.id as team_id',
                'teams.code as team_code',
                'teams.description as description',
                'daily_shift_teams.id as daily_shift_team_id'
            )
            ->join('daily_shift_teams', 'daily_shift_teams.team_id', '=', 'teams.id')
            ->join('employees', 'employees.id', '=', 'teams.supervisor_id')
            ->whereIn('daily_shift_teams.id', $daily_shift_teams_ids)
            ->where('teams.vsm_code', $vsm_code)
            ->where('employees.emp_code', $supevisor_code)
            ->get(); 
        }
        

        return $team_details;
        
    }

    private function getDownTime($teams){
        $all_teams = [];
        foreach($teams as $team){
            array_push($all_teams,$team->daily_shift_team_id);
        }
        
            $down_time = DowntimeLog::select(
                'downtime_logs.*'
            )
            ->whereIn('downtime_logs.daily_shift_team_id', $all_teams)
            ->get(); 
            return $down_time;

            
    }
    
    
    private function getPieChartData($teams, $daily_scanning_slots, $current_date_time,$daily_shift)
    {
        
        $downtime = ["2", "4", "3.5", "0", "0.5", "6", "1"];
        $damage = ["0", "43", "3", "0", "0", "32", "10","20","20", "43", "3", "0", "0", "32", "10","20", "43", "3", "0", "0", "32", "10","20", "43", "3", "0", "0", "32", "10"];
        $total_quantity = ["200", "210", "180", "200", "200", "150", "200"];
        $running_efficiency = ["88", "77", "66", "55", "77", "100", "89"];
        $total_sah = ["2", "4", "3", "0", "0", "3", "1"];

        $qty_data = [];
        $i = 0;

        $qty_variance = $this->getQtyVariance($teams, $daily_scanning_slots);
        $eff_variance = $this->getEffVariance($teams, $daily_scanning_slots, $current_date_time,$daily_shift);
        $sah_variance = $this->getSahVariance($teams, $daily_scanning_slots, $current_date_time,$daily_shift);
        $down_time = $this->getDownTime($teams);
        
        foreach($teams as $team){
            $total_qty = 0;
            $avg_efc=0;
            $total_sloat=0;
            $total_sah=0;
            $total_planned_sah=0;
            $total_downtime =0;
            $scanned_slot=0;

           // $scaned_sloat = 0;
            $unscanned_pass_sloat = 0;

            foreach($qty_variance as $index=>$rec){
                foreach($rec as $key => $val){
                    if($team->description == $index ){
                        $total_qty +=$val['Actual Quantity'];

                        if($val['Actual Quantity'] > 0){
                            $scanned_slot++;
                            $scanned_slot += $unscanned_pass_sloat;
                            $unscanned_pass_sloat = 0;

                        }
                        else{
                            $unscanned_pass_sloat++;
                        }

                    }    
                }   
            }
            foreach($eff_variance as $index=>$rec){
                foreach($rec as $key => $val){
                    
                    if($team->description == $index ){
                        $avg_efc +=$val['Actual Efficiency'];
                        if($val['Actual Efficiency'] > 0){
                            //$scanned_slot++;
                        }
                        $total_sloat++;
                    }    
                }   
            }

            if($scanned_slot > 0){
                $avg_efc = round($avg_efc/$scanned_slot,0)."%";
            }else{
                $avg_efc = 0;
            }
            
            $total_sloat=0;

            foreach($sah_variance as $index=>$rec){
                foreach($rec as $key => $val){
                    if($team->description == $index ){
                        $total_sah +=$val['Actual SAH'];
                        $total_planned_sah += $val['Planned SAH'];
                        
                    }    
                }   
            }

            /////////////////////  Down Time   ///////////////
            if(!is_null($down_time)){
                foreach($down_time as $row){
                    if($row->daily_shift_team_id == $team->daily_shift_team_id){
                        $total_downtime += intval($row->downtime_minutes);
                    }
                }
            }
            

            $qty_data[] = ["name" => $team->description, 
                            
                            "value" => 1, 
                            "downtime"=> $total_downtime, 
                            "damage"=> $damage[0], 
                            "total_quantity"=>$total_qty, 
                            "running_efficiency"=>$avg_efc,
                            "total_sah"=>round($total_sah,2),
                            "planned_sah"=>round($total_planned_sah,2) ];
            $i++;

        }
        
        return $qty_data;
        
    }

    private function getQtyVariance($teams, $daily_scanning_slots)
    {

        $qty_data = [];
        
        foreach ($teams as $team) {  
            $index = 0;
            $revised=0; 
            $pre_revised=0;
            $new_revised=0;  
			$is_last = 0;
            foreach ($daily_scanning_slots as $slot) {
                $index++;
                $actual = 0; 
                
                $target = DailyTeamSlotTarget::where('daily_scanning_slot_id', $slot->id)
                                            ->where('daily_shift_team_id', $team->daily_shift_team_id)
                                            ->first();

                $all_target = DailyTeamSlotTarget::where('daily_shift_team_id', $team->daily_shift_team_id)
                                            ->orderby('daily_scanning_slot_id')
                                            ->get();
                                            
                if(!(empty($target))){ 
                    if($index == 1){
                        
                        if($target->actual > 0){
                            $actual = $target->actual;
                        }
                        $quantity = ["Planned Quantity" => $target->forecast, "Revised Quantity" => $target->forecast, "Actual Quantity" => $actual];
                       
							if(intval($target->actual) > 0){
								 $revised = $target->forecast - $target->actual; 
							}
							else{
                                /////// check after sloat are scanned or not ///////////////
                                $next_target = DailyTeamSlotTarget::where('daily_scanning_slot_id',">", $slot->id)
                                            ->where('daily_shift_team_id', $team->daily_shift_team_id)
                                            ->where('actual',">" ,0)
                                            ->first();
                                if(isset($next_target->actual) && $next_target->actual > 0){
                                    $revised += $target->forecast;
                                }
                                else{
                                    $revised=0;
                                }
								
							}
                        $pre_revised=$revised;
                    }
                    else{
						// if(intval($pre_revised) > 0 || intval($pre_revised) < 0){
                          
                        $total_revised =0;
                        $future_slot=0;
                        $revised=0;
							if(!(empty($all_target))){ 
								foreach ($all_target as $all_slot) {
									if(intval($all_slot->daily_scanning_slot_id) >= intval($slot->id)) {
	
										$total_revised+=$all_slot->forecast;  
										$future_slot++;
									}
								}
								//$total_revised +=$pre_revised;
							
							   // print_r($total_revised." ".$future_slot." ");
								
								if(intval($target->actual) > 0){
									$revised = $target->forecast - $target->actual; 
                                    $new_revised=($total_revised+$pre_revised)/$future_slot;
                                    if($team->daily_shift_team_id == 1564){
                                           // print_r($pre_revised."***".$future_slot."***".$total_revised."/==");
                                        }
								}
								else{
                                                                    /////// check after sloat are scanned or not ///////////////
                                    $next_target = DailyTeamSlotTarget::where('daily_scanning_slot_id',">", $slot->id)
                                    ->where('daily_shift_team_id', $team->daily_shift_team_id)
                                    ->where('actual',">" ,0)
                                    ->first();
                                    if(isset($next_target->actual) && $next_target->actual > 0){
                                      
                                       
                                        $revised = $target->forecast;
                                       
                                        $new_revised=($total_revised+$pre_revised)/$future_slot;
                                        
                                       
                                    }
                                    else{
										if($is_last == 0){
											$new_revised=($total_revised+$pre_revised)/$future_slot;
											$is_last = 1;
										}
                                        $revised=0;
                                    }
									//$revised=0;
								}
                                
								$total_revised = round($new_revised,0);
								$pre_revised += $revised;
                              
                              
                                

							}
                    //if(!(empty($target))){    
                        if($target->actual > 0){
                            $actual = $target->actual;
                        }
						if($total_revised < 0){
							$total_revised = 0;
						}
                        
                        $quantity = ["Planned Quantity" => $target->forecast, "Revised Quantity" => $total_revised, "Actual Quantity" => $actual];
                    }
                }
                else{
                    $quantity = ["Planned Quantity" => 0, "Revised Quantity" => 0, "Actual Quantity" => 0];

                }
                $qty_data[$team->description][]  = array_merge(["name" => "Slot - " . $slot->seq_no], $quantity);
            }
            
        }
     
        return $qty_data;
    }

    private function getSahVariance($teams, $daily_scanning_slots, $current_date_time,$daily_shift)
    {
       
        $shiftHours = ShiftDetail::where('id', $daily_shift->shift_detail_id)->first();

        $qty_data = [];

        foreach ($teams as $team) {   
            $smv = 7.74;
            $planned_sah = 0;
            Log::info($current_date_time->toDateString());
            Log::info('TeamID '. $team->team_id);
            $fr_record = FrRecord::where('team_id', $team->team_id)->whereDate('run_date', '=' , $current_date_time->toDateString())->first();
            
            Log::info('FR');
            Log::info(($fr_record));
            if(!(empty($fr_record))) {
                $smv = $fr_record->smv;
                $planned_sah = $fr_record->planned_sah;
            }  
            foreach ($daily_scanning_slots as $slot) {
                $target = DailyTeamSlotTarget::where('daily_scanning_slot_id', $slot->id)
                                            ->where('daily_shift_team_id', $team->daily_shift_team_id)
                                            ->first();
                                            

                $hours = DB::table('daily_scanning_slots')
                        ->select('*')
                        ->where('daily_shift_id', $daily_shift->id)
                        ->where('id', $slot->id)
                        ->first();

                $from = date_create($hours->to_date_time);
                $to = date_create($hours->from_date_time);
                $interval = date_diff($from, $to);

                
                $difference = intval($interval->h)+floatval($interval->i)/60;
                $planned_sah = ($team->planned_sah*$difference)/$shiftHours->hours;

                if(!(empty($target))) {
                    $quantity = ["Actual SAH" => round(($target->actual * $target->actual_smv)/60,2), "Planned SAH" => $planned_sah];
                } 
                else{            
                $quantity = ["Actual SAH" => 0, "Planned SAH" => $planned_sah];
                }
                $qty_data[$team->description][]  = array_merge(["name" => "Slot - " . $slot->seq_no], $quantity);
            }
            
        }
        return $qty_data;

    }

    private function getEffVariance($teams, $daily_scanning_slots, $current_date_time,$daily_shift)
    {



        ///////     Get Shift Hours   /////////////////////

        $qty_data = [];

        foreach ($teams as $team) {   
            $smv = 7.74;         
            foreach ($daily_scanning_slots as $slot) {
                $work_hours = 2;
                $target = DailyTeamSlotTarget::where('daily_scanning_slot_id', $slot->id)
                                            ->where('daily_shift_team_id', $team->daily_shift_team_id)
                                            ->first();

                $NoWorks = DB::table('employees')
                            ->select('id')
                            ->where('base_team_id', $team->team_id)
                            ->get()->count();
                                
                if(!intval($NoWorks) > 0){
                    $NoWorks = 1;
                }


                ////////////////////   Get SAH Details /////////////////////
                $hours = DB::table('daily_scanning_slots')
                ->select('*')
                ->where('daily_shift_id', $daily_shift->id)
                ->where('id', $slot->id)
                ->first();

                $from = date_create($hours->to_date_time);
                $to = date_create($hours->from_date_time);
                $interval = date_diff($from, $to);

                
                $difference = intval($interval->h)+floatval($interval->i)/60;
               // $planned_sah = ($team->planned_sah*$difference)/$shiftHours->hours;
                             

                if(!(empty($target))){

                    $actual_sah = 0;
                    $planned_sah = 0;
                    if($target->actual >0 && $target->actual_smv > 0){
                        $actual_sah = ($target->actual * $target->actual_smv)/60;
                    }
                    $quantity = ["Planned Efficiency" => $team->planned_efficient,  "Actual Efficiency"=>round((($actual_sah)/($NoWorks*$difference))*100,2)];
                }
                else{
                    $quantity = ["Planned Efficiency" => $team->planned_efficient,  "Actual Efficiency"=>"0"] ;
                }                           
                

                $qty_data[$team->description][]  = array_merge(["name" => "Slot - " . $slot->seq_no], $quantity);
            }
            
        }
        return $qty_data;
    }



    public function getSuperMarketWip($team_id){
        $total = 0;
        $quantities = JobCardBundle::select('original_quantity', 'resized_quantity')
        ->join('job_cards', 'job_cards.id', '=', 'job_card_bundles.job_card_id')     
        ->where('job_cards.status','Finalized')
        ->where('job_cards.team_id', $team_id)
        ->get(); 
        
        foreach($quantities as $qty) {
            Log::info($qty);
            $total = $total  + Utilities::NVL(Utilities::NVL($qty->resized_quantity, $qty->original_quantity), 0);
        }  
        return $total;
    }

  
    public function getProductionWip($team_id){
        $total = 0;
        $quantities = JobCardBundle::select('original_quantity', 'resized_quantity')
        ->join('job_cards', 'job_cards.id', '=', 'job_card_bundles.job_card_id')     
        ->whereIn('job_cards.status',['Hold', 'InProgress', 'Issued'])
        ->where('job_cards.team_id', $team_id)
        ->get(); 

        foreach($quantities as $qty) {
            $total = $total  + Utilities::NVL(Utilities::NVL($qty->resized_quantity, $qty->original_quantity), 0);
        }  
        return $total;
    }

    public function getDashboardTemplate(){
        $dashboard = DashboardTemplate::select('*')
        ->get();  
        
        return $dashboard;
    }




}