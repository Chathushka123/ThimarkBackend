<?php
namespace App\Http\Repositories;

use App\DailyScanningSlot;
use App\DailyShift;
use App\DowntimeLog;
use App\DailyShiftTeam;
use App\DailyTeamSlotTarget;
use App\FrRecord;
use App\JobCard;
use App\JobCardBundle;
use App\Team;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\DB;
use Exception;
use Carbon\Carbon;
use Illuminate\Container\Util;
use Illuminate\Support\Facades\Log;

class QueriesRepository
{
    public function getDashBoardData($vsm_code, $supevisor_code, $current_date_time,$shift){
        
        Log::info($current_date_time);
       // $daily_shift = DailyShift::where('start_date_time', '<=', Carbon::parse($current_date_time))->where('end_date_time', '>=', Carbon::parse($current_date_time))->first();  
       $daily_shift = DailyShift::where('id', '=', $shift)->first();   
        Log::info($daily_shift);

        if(!(empty($daily_shift))){
            
        $daily_scanning_slots = DailyScanningSlot::where('daily_shift_id', $daily_shift->id)->get(); 
        Log::info($daily_scanning_slots);

        $teams = $this->_getTeams($vsm_code, $supevisor_code, $daily_shift);
        
        Log::info("TEAMS");
        Log::info($teams);
        
        
        $pie_data = $this->getPieChartData($teams, $daily_scanning_slots, Carbon::parse($current_date_time),$daily_shift);
        $qty_data = $this->getQtyVariance($teams, $daily_scanning_slots);
        $eff_data = $this->getEffVariance($teams, $daily_scanning_slots, Carbon::parse($current_date_time),$daily_shift);
        $sah_data = $this->getSahVariance($teams, $daily_scanning_slots, Carbon::parse($current_date_time));
        return response()->json(["pie_data" => $pie_data, "qty_data" => $qty_data, "eff_data" => $eff_data, "sah_data" => $sah_data], 200);
        }
        else{
            throw new Exception("No Data Found for the given iputs, Current Date Time, VSM , Supervisor");
        }
    }

    private function _getTeams($vsm_code, $supevisor_code, $daily_shift){

        $daily_shift_teams_ids = DailyShiftTeam::where('daily_shift_id', $daily_shift->id)->pluck('id')->toArray();
        Log::info("Daily Shift Teams IDs");
        Log::info($daily_shift_teams_ids);

        if($vsm_code == "ALL" && $supevisor_code == "ALL")
        {
            $team_details = Team::select(
                'teams.id as team_id',
                'teams.code as team_code',
                'teams.description as description',
                'daily_shift_teams.id as daily_shift_team_id',
                
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
                'daily_shift_teams.id as daily_shift_team_id'
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
        $damage = ["20", "43", "3", "0", "0", "32", "10","20","20", "43", "3", "0", "0", "32", "10","20", "43", "3", "0", "0", "32", "10","20", "43", "3", "0", "0", "32", "10"];
        $total_quantity = ["200", "210", "180", "200", "200", "150", "200"];
        $running_efficiency = ["88", "77", "66", "55", "77", "100", "89"];
        $total_sah = ["2", "4", "3", "0", "0", "3", "1"];

        $qty_data = [];
        $i = 0;

        $qty_variance = $this->getQtyVariance($teams, $daily_scanning_slots);
        $eff_variance = $this->getEffVariance($teams, $daily_scanning_slots, $current_date_time,$daily_shift);
        $sah_variance = $this->getSahVariance($teams, $daily_scanning_slots, $current_date_time);
        $down_time = $this->getDownTime($teams);
        
        foreach($teams as $team){
            $total_qty = 0;
            $avg_efc=0;
            $total_sloat=0;
            $total_sah=0;
            $total_planned_sah=0;
            $total_downtime =0;
            foreach($qty_variance as $index=>$rec){
                foreach($rec as $key => $val){
                    if($team->description == $index ){
                        $total_qty +=$val['Actual Quantity'];
                    }    
                }   
            }
            foreach($eff_variance as $index=>$rec){
                foreach($rec as $key => $val){
                    if($team->description == $index ){
                        $avg_efc +=$val['Planned Efficiency'];
                        $total_sloat++;
                    }    
                }   
            }

            if($total_sloat > 0){
                $avg_efc = round($avg_efc/$total_sloat,2);
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
                            "damage"=> $damage[$i], 
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
            foreach ($daily_scanning_slots as $slot) {
                $index++;
                
                $target = DailyTeamSlotTarget::where('daily_scanning_slot_id', $slot->id)
                                            ->where('daily_shift_team_id', $team->daily_shift_team_id)
                                            ->first();

                $all_target = DailyTeamSlotTarget::where('daily_shift_team_id', $team->daily_shift_team_id)
                                            ->get();
                                            
                if(!(empty($target))){ 
                    if($index == 1){
                        $quantity = ["Planned Quantity" => $target->forecast, "Revised Quantity" => $target->revised, "Actual Quantity" => $target->actual];
                       
							if(intval($target->actual) > 0){
								 $revised = $target->forecast - $target->actual; 
							}
							else{
								$revised=0;
							}
                        $pre_revised=$revised;
                    }
                    else{
						if(intval($pre_revised) > 0 || intval($pre_revised) < 0){
						 
                        $total_revised =0;
                        $future_slot=0;
							if(!(empty($all_target))){ 
								foreach ($all_target as $all_slot) {
									if(intval($all_slot->daily_scanning_slot_id) >= intval($slot->id)) {
										if($new_revised == 0){
											$total_revised+=$all_slot->revised;  
										}
										else{
											$total_revised+=$new_revised;
											
										}
										
										$future_slot++;
									}
								}
								$total_revised +=$pre_revised;
							
							   // print_r($total_revised." ".$future_slot." ");
								$new_revised=$total_revised/$future_slot;
								if(intval($target->actual) > 0){
									$revised = $new_revised - $target->actual; 
								}
								else{
									$revised=0;
								}
								$total_revised = round($total_revised/$future_slot,0);
								$pre_revised=$revised;

							}
						}
						else{
							if($new_revised == 0){
								foreach ($all_target as $all_slot) {
									if(intval($all_slot->daily_scanning_slot_id) == intval($slot->id)) {
										$total_revised = round($all_slot->revised,0);
									}
								}
							}
							else{
								$total_revised = round($new_revised,0);
							}
							
							
						}


                    //if(!(empty($target))){                            
                        $quantity = ["Planned Quantity" => $target->forecast, "Revised Quantity" => $total_revised, "Actual Quantity" => $target->actual];
                    }
                }
                else{
                    $quantity = ["Planned Quantity" => 0, "Revised Quantity" => 0, "Actual Quantity" => 0];

                }
                $qty_data[$team->description][]  = array_merge(["name" => "Slot - " . $slot->seq_no], $quantity);
            }
            
        }
        
        // foreach($qty_data as $index=>$rec){
        //     $revised =0;
        //     $total_revise_balance=0;
        //     foreach($rec as $key => $val){
        //         $revised =$val['Planned Quantity']-$val['Actual Quantity'];
        //         $future_sloat =0;
        //         if($revised > 0){
        //             foreach($rec as $key1 => $val1){
        //                 if($key1 > $key){
        //                     $total_revise_balance+=$val1['Revised Quantity'];
        //                     $future_sloat++;
        //                 }
        //             }
        //             $total_revise_balance=round($total_revise_balance/$future_sloat,0);
        //             foreach($rec as $key2 => $val2){
        //                 if($key2 > $key){
        //                    // print_r($total_revise_balance." ");
        //                    //$qty_data['index'][$key2]['Revised Quantity']=$total_revise_balance;

        //                    $qty_data1=[$qty_data[$index][$key2]['Revised Quantity']=>$total_revise_balance,
        //                                 $qty_data[$index][$key2]['Planned Quantity']=>$total_revise_balance,
        //                                 $qty_data[$index][$key2]['Actual Quantity']=>$total_revise_balance];

        //                    $qty_data[$index][] = array_replace($qty_data, $qty_data1);
        //                    // print_r($qty_data['index'][$key2]['Revised Quantity']." ");
        //                     //$val2['Revised Quantity']=$total_revise_balance;
                            
        //                 }
        //             }
        //         }
        //     }   
        // }
        return $qty_data;
    }

    private function getSahVariance($teams, $daily_scanning_slots, $current_date_time)
    {
        // $teams = ["01B", "03B", "09B", "19B", "34B", "20B", "33B"];
        // $slots = ["Slot 1", "Slot 2", "Slot 3", "Slot 4", "Slot 5", "Slot 6"];
        // $quantity = ["Efficiency" => "11"];
        // $qty_data = [];

        // foreach($teams as $team){
        //     foreach($slots as $slot){
        //         foreach($quantity as $key=>$value){
        //             $quantity["Efficiency"] = $value + 5;
        //         }
        //         $qty_data[$team][]  = array_merge(["name"=> $slot], $quantity);
        //     }
        // }

        // return $qty_data;


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
                                            

                if(!(empty($target))) {
                    $quantity = ["Actual SAH" => ($target->actual * $target->actual_smv)/60, "Planned SAH" => ($target->forecast* $smv)/60];
                } 
                else{            
                $quantity = ["Actual SAH" => 0, "Planned SAH" => 0];
                }
                $qty_data[$team->description][]  = array_merge(["name" => "Slot - " . $slot->seq_no], $quantity);
            }
            
        }
        return $qty_data;

    }

    private function getEffVariance($teams, $daily_scanning_slots, $current_date_time,$daily_shift)
    {
        // $teams = ["01B", "03B", "09B", "19B", "34B", "20B", "33B"];
        // $slots = ["Slot 1", "Slot 2", "Slot 3", "Slot 4", "Slot 5", "Slot 6"];
        // $quantity = ["Planned SAH" => "36", "Actual SAH" => "36"];
        // $qty_data = [];

        // foreach($teams as $team){
        //     foreach($slots as $slot){
        //         foreach($quantity as $key=>$value){
        //             $quantity["Planned SAH"] = $value + 20;
        //             $quantity["Actual SAH"] = $value + 18;
        //         }
        //         $qty_data[$team][]  = array_merge(["name"=> $slot], $quantity);
        //     }
        // }

        // return $qty_data;
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

                $hours = DB::table('shift_details')
                        ->select('hours')
                        ->where('id', $daily_shift->shift_detail_id)
                        ->first();

                      

                if(!(empty($target))){
                    //$quantity = ["Planned Efficiency" => ((($target->actual * $smv)/60)/(($target->forecast* $smv)/60))*100];
                    $quantity = ["Planned Efficiency" => ((($target->actual * $target->actual_smv))/(($target->forecast* $target->actual_smv))),  "Actual Efficiency"=>(($target->actual * $target->actual_smv)/60)/($NoWorks*1)];
                }
                else{
                    $quantity = ["Planned Efficiency" => 0,  "Actual Efficiency"=>"0"] ;
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




}