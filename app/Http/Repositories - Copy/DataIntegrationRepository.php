<?php

namespace App\Http\Repositories;

use App\Buyer;
use App\Carton;
use App\DailyShift;
use App\DailyShiftTeam;
use App\Employee;
use App\Exceptions\GeneralException;
use App\ForeignKeyMapper;
use App\Fpo;
use App\CombineOrder;
use App\FpoFabric;
use App\FrRecord;
use App\Http\Controllers\Api\RoutingOperationController;
use App\Http\Resources\SocResource;
use App\Routing;
use App\RoutingOperation;
use App\Soc;
use App\Style;
use App\StyleFabric;
use App\Team;
use App\TeamCategory;
use Carbon\Carbon;
use CreateDailyTeamShiftsTable;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class DataIntegrationRepository
{

  public function createAndUpdateCartons($request)
  {
    try {
      DB::beginTransaction();
      $key = $request->carton_type;
      if (!(is_null($key))) {
        $rec = [];
        $rec['carton_type'] = $request->carton_type;
        $rec['uom'] = $request->uom;
        $rec['height'] = Utilities::NVL($request->height, 0);
        $rec['width'] = Utilities::NVL($request->width, 0);
        $rec['length'] = Utilities::NVL($request->length, 0);
        $rec['weight'] = Utilities::NVL($request->weight, 0);

        $carton = Carton::where('carton_type', $key)->first();

        if ((is_null($carton))) {
          $model = CartonRepository::createRec($rec);
        } else {
          $rec['updated_at'] = $carton->updated_at;
          $model = CartonRepository::updateRec($carton->id, $rec);
        }
      } else {
        throw new Exception("Invalid value for Carton Type");
      }
      DB::commit();
      return response()->json(["status" => "success", "data" => $model], 200);
    } catch (Exception $e) {
      DB::rollBack();
      //throw new \App\Exceptions\GeneralException($e->getMessage());
      return response()->json(["status" => "error", "error_message" => $e->getMessage()], 200);
    }
  }

  public function createAndUpdateRoutes($request)
  {
    try {
      DB::beginTransaction();
      $key = $request->route_code;
      if (!(is_null($key))) {
        $rec = [];
        $rec['route_code'] = $request->route_code;
        $rec['description'] = $request->description;
        $route = Routing::where('route_code', $key)->first();

        if ((is_null($route))) {
          $model = RoutingRepository::createRec($rec);
        } else {
          $rec['updated_at'] = $route->updated_at;
          $model = RoutingRepository::updateRec($route->id, $rec);
        }
      } else {
        throw new Exception("Invalid value for Route Code");
      }
      DB::commit();
      return response()->json(["status" => "success", "data" => $model], 200);
    } catch (Exception $e) {
      DB::rollBack();
      //throw new \App\Exceptions\GeneralException($e->getMessage());
      return response()->json(["status" => "error", "error_message" => $e->getMessage()], 200);
    }
  }

  public function createAndUpdateBuyers($request)
  {
    try {
      DB::beginTransaction();
      $key = $request->buyer_code;
      if (!(is_null($key))) {
        $rec = [];
        $rec['buyer_code'] = $request->buyer_code;
        $rec['name'] = $request->name;
        $buyer = Buyer::where('buyer_code', $key)->first();

        if ((is_null($buyer))) {
          $model = BuyerRepository::createRec($rec);
        } else {
          $rec['updated_at'] = $buyer->updated_at;
          $model = BuyerRepository::updateRec($buyer->id, $rec);
        }
      } else {
        throw new Exception("Invalid value for buyer Code");
      }
      DB::commit();
      return response()->json(["status" => "success", "data" => $model], 200);
    } catch (Exception $e) {
      DB::rollBack();
      //throw new \App\Exceptions\GeneralException($e->getMessage());
      return response()->json(["status" => "error", "error_message" => $e->getMessage()], 200);
    }
  }

  public function createAndUpdateRouteOperations($request)
  {
    try {
      DB::beginTransaction();
      $key = $request->operation_code;
      if (!(is_null($key))) {
        $rec = [];
        $rec['operation_code'] = $request->operation_code;
        $rec['description'] = $request->description;
        $rec['wfx_seq'] = $request->wfx_seq_no;
        $rec['sort_seq'] = $request->wfx_seq_no;
        $rec['shop_floor_seq'] = 0;
        $rec['in'] = $request->in;
        $rec['out'] = $request->out;
        $rec['smv'] = is_null($request->smv) ? 0 : $request->smv;
        $rec['level'] = 1;
        $rec['wip_point'] = $request->wip_point;;
        $rec['print_bundle'] = $request->print_bundle;
        $rec['parallel_operation_no'] = $request->wfx_seq_no * 100;
        $route = Routing::where('route_code', $request->route_code)->first();
        if (is_null($route)) {
          throw new Exception("Route Code " . $request->route_code . " Does not exists");
        }
        $rec['routing_id'] = $route->id;

        $routing_operation = RoutingOperation::where('routing_id', $route->id)->where('operation_code', $key)->first();

        if ((is_null($routing_operation))) {
          $model = RoutingOperationRepository::createRec($rec);
        } else {
          $rec['updated_at'] = $routing_operation->updated_at;
          $model = RoutingOperationRepository::updateRec($routing_operation->id, $rec);
        }
      } else {
        throw new Exception("Invalid value for Operation Code");
      }
      DB::commit();
      return response()->json(["status" => "success", "data" => $model], 200);
    } catch (Exception $e) {
      DB::rollBack();
      //throw new \App\Exceptions\GeneralException($e->getMessage());
      return response()->json(["status" => "error", "error_message" => $e->getMessage()], 200);
    }
  }

  public function createAndUpdateStyles($request)
  {
    try {

      DB::beginTransaction();
      $key = $request->style_code;
      if (!(is_null($key))) {
        $rec = [];
        $rec['style_code'] = $request->style_code;
        $rec['description'] = $request->description;
        $rec['size_fit'] = $request->size_fit;
        $rec['size_fit_json'] = $request->size_fit_json; 
        $route = Routing::where('route_code', $request->routing_code)->first();
        if (is_null($route)) {
          throw new Exception("Route Code " . $request->routing_code . " Does not exists");
        }
        $rec['routing_id'] = $route->id;

        //$style = Style::where('routing_id', $route->id)->where('style_code', $key)->first();
        $style = Style::where('style_code', $key)->first();

        if ((is_null($style))) {
          $model = StyleRepository::createRec($rec);
        } else {
          $model= StyleRepository::updateRec($style->id,$rec);
        }
        // else {
        //  $rec['updated_at'] = $style->updated_at;
        //  $model = RoutingOperationRepository::updateRec($style->id, $rec);
        //}
      } else {
        throw new Exception("Invalid value for Style Code");
      }
      DB::commit();
      return response()->json(["status" => "success", "data" => $model], 200);
    } catch (Exception $e) {
      DB::rollBack();
      //throw new \App\Exceptions\GeneralException($e->getMessage());
      return response()->json(["status" => "error", "error_message" => $e->getMessage()], 200);
    }
  }

  public function createAndUpdateStyleFabrics($request)
  {
    try {

      DB::beginTransaction();
      $key = $request->style_code;
      if (!(is_null($key))) {
        $rec = [];
        $rec['fabric'] = $request->fabric;
        $style = Style::where('style_code', $request->style_code)->first();
        if (is_null($style)) {
          throw new Exception("Style Code " . $request->style_code . " Doesn't exist");
        }
        $rec['style_id'] = $style->id;

        $styleFabric = StyleFabric::where('style_id', $style->id)->where('fabric', $request->fabri_code)->first();

        if ((is_null($styleFabric))) {
          $model = StyleFabricRepository::createRec($rec);
        } else {
          $model = null;
        }
        // else {
        //  $rec['updated_at'] = $style->updated_at;
        //  $model = RoutingOperationRepository::updateRec($style->id, $rec);
        //}
      } else {
        throw new Exception("Invalid value for Style Code");
      }
      DB::commit();
      return response()->json(["status" => "success", "data" => $model], 200);
    } catch (Exception $e) {
      DB::rollBack();
      //throw new \App\Exceptions\GeneralException($e->getMessage());
      return response()->json(["status" => "error", "error_message" => $e->getMessage()], 200);
    }
  }

  public function createAndUpdateSocs($request)
  {
    try {

      DB::beginTransaction();
      $qty_json = $request->qty_json;

      $key = $request->wfx_soc_no;
      if (!(is_null($key))) {
        $rec = [];
        $rec['wfx_soc_no'] = $request->wfx_soc_no;
        $rec['garment_color'] = $request->garment_color;
        $rec['pack_color'] = $request->pack_color;
        $rec['customer_style_ref'] = $request->customer_style_ref;
        $rec['kit_pack_id'] = $request->kit_pack_id;

        $buyer = Buyer::where('buyer_code', $request->buyer_code)->first();
        if (is_null($buyer)) {
          throw new Exception("Buyer Code " . $request->buyer_code . " Does not exists");
        }
        $rec['buyer_id'] = $buyer->id;

        $style = Style::where('style_code', $request->style_code)->first();
        if (is_null($style)) {
          throw new Exception("Style Code " . $request->style_code . " Does not exists");
        }
        $rec['style_id'] = $style->id;
        $rec['qty_json_order'] = $style->size_fit;


        $style_json_key = $style->size_fit;
        asort($style_json_key);
        //Log::info($style_json_key);

        $request_json_key = array_keys($qty_json);
        asort($request_json_key);
        //Log::info($request_json_key);


        $style_json_str = array_reduce($style_json_key, function ($carry, $item) {
          $carry = $carry . '^' . $item;
          return $carry;
        }, "");

        $request_json_str = array_reduce($request_json_key, function ($carry, $item) {
          $carry = $carry . '^' . $item;
          return $carry;
        }, "");

        //Log::info($style_json_str);
        //Log::info($request_json_str);

        if ($style_json_str != $request_json_str) {
          if (sizeof($request_json_key) != sizeof($style_json_key)) {
            if (sizeof($request_json_key) < sizeof($style_json_key)) {
              $result = array_diff($style_json_key, $request_json_key);
              foreach (array_values($result) as $item) {
                $qty_json[$item] = 0;
              }
            } else {
              throw new Exception("Soc Size-Fit does not match with Style Size-Fit");
            }
          } else {
            throw new Exception("Soc Size-Fit does not match with Style Size-Fit");
          }
        }
        $rec['qty_json'] = $qty_json;

        $soc = Soc::where('wfx_soc_no', $request->wfx_soc_no)->first();

        if ((is_null($soc))) {
          $model = SocRepository::createRec($rec);
        } else {
          $model = SocRepository::updateRec($soc->id,$rec);
          

        }
        // else {
        //  $rec['updated_at'] = $style->updated_at;
        //  $model = RoutingOperationRepository::updateRec($style->id, $rec);
        //}
      } else {
        throw new Exception("Invalid value for wfx_soc_no");
      }
      DB::commit();

      return response()->json(["status" => "success", "data" => $model], 200);
    } catch (Exception $e) {
      DB::rollBack();
      //throw new \App\Exceptions\GeneralException($e->getMessage());
      return response()->json(["status" => "error", "error_message" => $e->getMessage()], 200);
    }
  }

  public function createAndUpdateFpos($request)
  {
    try {

      DB::beginTransaction();
      $qty_json = $request->qty_json;
      $key = $request->wfx_fpo_no;
      if (!(is_null($key)) && !is_null($qty_json)) {
        $rec = [];
        $rec['wfx_fpo_no'] = $request->wfx_fpo_no;

        $soc = Soc::where('wfx_soc_no', $request->wfx_soc_no)->first();
        if (is_null($soc)) {
          throw new Exception("Invalid value for Soc Number");
        }
        $rec['soc_id'] = $soc->id;
        $rec['garment_color'] = $soc->garment_color;
        $rec['pack_color'] = $soc->pack_color;
        $rec['customer_style_ref'] = $soc->customer_style_ref;
        $rec['kit_pack_id'] = $soc->kit_pack_id;
        $rec['buyer_id'] = $soc->buyer_id;
        $rec['style_id'] = $soc->style_id;
        
        $style = Style::find($soc->style_id);
        if (is_null($style)) {
          throw new Exception("Invalid value for Style Code");
        }
        $rec['style_id'] = $style->id;
        $rec['qty_json_order'] = $soc->qty_json_order;

        $soc_json_key = array_keys($soc->qty_json);
        asort($soc_json_key);

        $request_json_key = array_keys($qty_json);
        asort($request_json_key);

        $soc_json_str = array_reduce($soc_json_key, function ($carry, $item) {
          $carry = $carry . '^' . $item;
          return $carry;
        }, "");

        $request_json_str = array_reduce($request_json_key, function ($carry, $item) {
          $carry = $carry . '^' . $item;
          return $carry;
        }, "");

        if ($soc_json_str != $request_json_str) {
          if (sizeof($request_json_key) != sizeof($soc_json_key)) {
            if (sizeof($request_json_key) < sizeof($soc_json_key)) {
              $result = array_diff($soc_json_key, $request_json_key);
              foreach (array_values($result) as $item) {
                $qty_json[$item] = 0;
              }
            } else {
              throw new Exception("FPO size-fit does not match with FPO size-fit");
            }
          } else {
            throw new Exception("FPO size-fit does not match with FPO size-fit");
          }
        }
        $rec['qty_json'] = $qty_json;

        $fpo = Fpo::where('wfx_fpo_no', $request->wfx_fpo_no)->first();
        $rec['updated_at'] = $fpo["updated_at"];

        if ((is_null($fpo))) {
          $model = FpoRepository::createRec($rec);
          $route_operations = RoutingOperation::where('routing_id', $style->routing_id);
          foreach ($route_operations as $oper) {
            $oper_rec["fpo_id"] = $model->id;
            $oper_rec["routing_operation_id"] = $oper->id;
            $fpo_op_model = FpoOperationRepository::createRec($oper_rec);
          }
        } else {
         // $model = null;
         
         $model = FpoRepository::updateRec($fpo->id,$rec);
         

        }
        // else {
        //  $rec['updated_at'] = $style->updated_at;
        //  $model = RoutingOperationRepository::updateRec($style->id, $rec);
        //}
      } else if(is_null($key)) {
        throw new Exception("Invalid value for wfx_fpo_no");
      }else if(is_null($qty_json)){
        throw new Exception("Invalid value for Qty Json");
      }
      DB::commit();
      return response()->json(["status" => "success", "data" => $model], 200);
    } catch (Exception $e) {
      DB::rollBack();
      //throw new \App\Exceptions\GeneralException($e->getMessage());
      return response()->json(["status" => "error", "error_message" => $e->getMessage()], 200);
    }
  }

  public function createAndUpdateFpoFabrics($request)
  {
    try {

      DB::beginTransaction();
      $key = $request->wfx_fpo_no;
      if (!(is_null($key))) {
        $fpo = Fpo::where('wfx_fpo_no', $request->wfx_fpo_no)->first();
        if (is_null($fpo)) {
          throw new Exception("Fpo Number " . $request->wfx_fpo_no . " Does not exist");
        }

        $soc = $fpo->soc;
        $style = Style::find($soc->style_id);
        if (is_null($style)) {
          throw new Exception("Invalid value for Style Code");
        }

        $style_fabric = StyleFabric::where('style_id', $style->id)->where('fabric', $request->fabric)->first();
        if (is_null($style_fabric)) {
          throw new Exception("Given Fabric " . $request->fabric . " doesn't exist in Style for this SOC - FPO");
        }

        $rec = [];
        $rec['fpo_id'] = $fpo->id;
        $rec['style_fabric_id'] = $style_fabric->id;
        $rec['avg_consumption'] = $request->consumption;

        $fpoFabric = FpoFabric::where('style_fabric_id', $style_fabric->id)->where('fpo_id', $fpo->id)->first();

        if ((is_null($fpoFabric))) {
          $model = FpoFabricRepository::createRec($rec);
        } else {
          $model = null;
        }
        // else {
        //  $rec['updated_at'] = $style->updated_at;
        //  $model = RoutingOperationRepository::updateRec($style->id, $rec);
        //}
      } else {
        throw new Exception("Invalid value for Fpo Number");
      }
      DB::commit();
      return response()->json(["status" => "success", "data" => $model], 200);
    } catch (Exception $e) {
      DB::rollBack();
      //throw new \App\Exceptions\GeneralException($e->getMessage());
      return response()->json(["status" => "error", "error_message" => $e->getMessage()], 200);
    }
  }

  public function createAndUpdateEmployees($request)
  {
    try {
      DB::beginTransaction();
      $key = $request->emp_code;

      if (!(is_null($key))) {
        $rec = [];
        $rec['emp_code'] = $request->emp_code;
        $rec['first_name'] = $request->first_name;
        $rec['last_name'] = $request->last_name;
        $rec['employee_type'] = $request->employee_type;
        $rec['employee_status'] = $request->employee_status;
        $employee = Employee::where('emp_code', $key)->first();

        if ((is_null($employee))) {
          $model = EmployeeRepository::createRec($rec);
        } else {
          $rec['updated_at'] = $employee->updated_at;
          $rec['base_team_id'] = $employee->base_team_id;
          $model = EmployeeRepository::updateRec($employee->id, $rec);
        }
      } else {
        throw new Exception("Invalid value for Employee Code");
      }
      DB::commit();
      return response()->json(["status" => "success", "data" => $model], 200);
    } catch (Exception $e) {
      DB::rollBack();
      //throw new \App\Exceptions\GeneralException($e->getMessage());
      return response()->json(["status" => "error", "error_message" => $e->getMessage()], 200);
    }
  }

  public function createAndUpdateTeams($request)
  {
    try {

      DB::beginTransaction();
      $key = $request->code;
      if (!(is_null($key))) {

        $supervisor = Employee::where('emp_code', $request->supervisor_code)->first();
        if (is_null($supervisor)) {
          throw new Exception("Supvisor " . $request->supervisor_code . " is not registered as an Employee");
        }

        $team_category = TeamCategory::where('code', $request->team_category_code)->first();
        if (is_null($team_category)) {
          throw new Exception("Team Category  " . $request->team_category_code . "Does not Exists");
        }

        $rec = [];
        $rec['code'] = $request->code;
        $rec['description'] = $request->description;
        $rec['team_category_id'] = $team_category->id;
        $rec['vsm_code'] = $team_category->vsm_code;
        $rec['supervisor_id'] = $supervisor->id;

        $Team = Team::where('code', $key)->first();

        if ((is_null($Team))) {
          $model = TeamRepository::createRec($rec);
        } else {

          $rec['updated_at'] = $Team->updated_at;
          $model = TeamRepository::updateRec($Team->id, $rec);
        }
      } else {
        throw new Exception("Invalid value for Team Code");
      }
      DB::commit();
      return response()->json(["status" => "success", "data" => $model], 200);
    } catch (Exception $e) {
      DB::rollBack();
      //throw new \App\Exceptions\GeneralException($e->getMessage());
      return response()->json(["status" => "error", "error_message" => $e->getMessage()], 200);
    }
  }

  public function SyncEmployeesWithTeams($request)
  {
    try {

      DB::beginTransaction();

      $key1 = $request->team_code;
      $key2 = $request->employee_code;

      if ((is_null($key1))) {
        throw new Exception("Invalid Value for Team Code");
      }

      if ((is_null($key2))) {
        throw new Exception("Invalid Value for Employee Code");
      }

      $employee = Employee::where("emp_code", $request->employee_code)->first();
      if (is_null($employee)) {
        throw new Exception("Employee " . $request->employee_code . " Does not Exists");
      }



      $team = Team::where("code", $request->team_code)->first();
      if (is_null($team)) {
        throw new Exception("Team " . $request->team_code . "Does not Exists");
      }

      $rec = [];
      $rec['employee_id'] = $employee->id;
      $rec['base_team_id'] = $team->id;
      $rec['updated_at'] = $employee->updated_at;

      $model = EmployeeRepository::updateRec($employee->id, $rec);

      DB::commit();
      return response()->json(["status" => "success", "data" => $model], 200);
    } catch (Exception $e) {
      DB::rollBack();
      //throw new \App\Exceptions\GeneralException($e->getMessage());
      return response()->json(["status" => "error", "error_message" => $e->getMessage()], 200);
    }
  }

  public function createAndUpdateFrRecords($request)
  {
    try {
      DB::beginTransaction();
      Log::info($request);
      $key = $request->run_date;
      if (!(is_null($key))) {

        $team = Team::where("code", $request->team_code)->first();
 
        if (empty($team)) {
          throw new Exception("Team " . $request->team_code . " Does not Exists");
        }

        $rec = [];
        $rec['run_date'] = Carbon::parse($request->run_date);
        $rec['team_id'] = $team->id;
        $rec['total_planned_target'] = $request->total_planned_target;
        $rec['planned_efficiency'] = $request->planned_efficiency;
        $rec['planned_sah'] = $request->planned_sah;
        $rec['smv'] = $request->smv;
        $rec['style_code'] = $request->style_code;
        $rec['soc_no'] = $request->soc_no;
        $rec['fpo_no'] = $request->fpo_no;
        
        Log::info($rec);

        $fr_record = FrRecord::where('team_id', $team->id)->where('run_date', $request->run_date)->first();

        if (is_null($fr_record)) {
          $model = FrRecordRepository::createRec($rec);
        } 
        else {
          $rec['updated_at'] = $fr_record->updated_at;
          $model = FrRecordRepository::updateRec($fr_record->id, $rec);
        }
      } else {
        throw new Exception("Invalid value for Date Field");
      }
      DB::commit();
      return response()->json(["status" => "success", "data" => $model], 200);
    } catch (Exception $e) {
      DB::rollBack();
      //throw new \App\Exceptions\GeneralException($e->getMessage());
      return response()->json(["status" => "error", "error_message" => $e->getMessage()], 200);
    }
  }

  public function syncTotalTargetValues($request)
  {
    try {
      DB::beginTransaction();

      $key = $request->run_date;
      if (!(is_null($key))) {
        $fr_records = DB::table('fr_records')
        ->select(DB::raw('sum(total_planned_target) as total_planned_target, team_id'))
        ->whereDate('run_date', Carbon::parse($request->run_date))
        ->groupBy('team_id')
        ->get();

        foreach ($fr_records as $fr) {
          $daily_shift_ids = DailyShift::whereDate('current_date', Carbon::parse($request->run_date))->pluck('id')->toArray();

          $daily_shift_team_rec = Team::select(
            'daily_shift_teams.id'
          )
            ->join('daily_shift_teams', 'daily_shift_teams.team_id', '=', 'teams.id')
            ->where('teams.id', $fr->team_id)
            ->whereIn('daily_shift_teams.daily_shift_id', $daily_shift_ids)
            ->first();

          if (!(empty($daily_shift_team_id))) {
            $daily_shift_team = DailyShiftTeam::find('id', $daily_shift_team_rec->id);
            if (!(empty($daily_shift_team))) {
              $rec = [];
              $rec['updated_at'] = $daily_shift_team->updated_at;
              $rec['total_target'] = $fr->total_planned_target;
              $model = DailyShiftTeamRepository::updateRec($daily_shift_team->id, $rec);
            }
          }
        }

      DB::commit();
      return response()->json(["status" => "success"], 200);
      }
    } catch (Exception $e) {
      DB::rollBack();
      //throw new \App\Exceptions\GeneralException($e->getMessage());
      return response()->json(["status" => "error", "error_message" => $e->getMessage()], 200);
    }
  }

  public function CreateCombineOrders(){
    try {
      DB::beginTransaction();

      $fpos = FPO::select('*')
      ->where('combine_order_id',null)
      ->where('ORGWFXFPO',"!=",null)
      ->where('ORGWFXFPO',"!=","")
      //->where('id',"=",1480)
      ->orderby('soc_id','ASC')
      ->get();

      //return $fpos;

      $same_fpo = [];
      $soc_id = "";
      if(!is_null($fpos) && isset($fpos) && sizeof($fpos) > 0){
        foreach($fpos as $key =>$val){
          if($soc_id === ""){
            $soc_id = $val['soc_id'];
            array_push($same_fpo,$val['id']);
          }else if($soc_id == $val['soc_id']){
            array_push($same_fpo,$val['id']);
          }else{
            $cmb_no =  CombineOrderRepository::_getNextOrderNo($same_fpo[0]);
            $model =  CombineOrder::create(['combine_order_no' => $cmb_no,'description'=>"System Generated"]);

            foreach($same_fpo as $k =>$v){            
              $fpo = FPO::find($v);            
              $fpo->update(['combine_order_id'=>$model->id,'status'=>'Closed']);            
            }
            
            $cut_plan = self::createRatioPlan($model);
            
            $same_fpo = [];
            $soc_id = $val['soc_id'];
            array_push($same_fpo,$val['id']);
          }
        }

        $cmb_no =  CombineOrderRepository::_getNextOrderNo($same_fpo[0]);
        $model =  CombineOrder::create(['combine_order_no' => $cmb_no,'description'=>"System Generated"]);

        foreach($same_fpo as $k =>$v){            
          $fpo = FPO::find($v);            
          $fpo->update(['combine_order_id'=>$model->id]);            
        }
        
        $cut_plan = self::createRatioPlan($model);
      }
      DB::commit();
      return response()->json(["status" => "success"], 200);
      }
     catch (Exception $e) {
      DB::rollBack();
      //throw new \App\Exceptions\GeneralException($e->getMessage());
      return response()->json(["status" => "error", "error_message" => $e->getMessage()], 200);
    }
  }

  public function createRatioPlan($model){
    $fpos = FPO::select("*")
    ->where('combine_order_id',$model->id)
    ->get();

    $qty_json = [];

    foreach($fpos as $rec){
      $qty = $rec->qty_json;

      foreach($qty as $key => $val){
        $qty_json[$key]=(array_key_exists($key, $qty_json)) ? (intval($qty_json[$key])+intval($val)) : intval($val);
      }
    }

      $plan_array = [
        'cut_no' => 'Cut-01',
        'ratio_json' => $qty_json,
        'value_json' => $qty_json,
        'qty_json_order' => null,
        'marker_name' => "System Generated Marker",
        'yrds' => "0.00",
        'inch' => "0.00",
        'acc_width' =>"0.00",
        'max_plies' => 1,
        'main_fabric' => null,
        'style_fabric_id' => null,
        'combine_order_id' => $model->id
      ];

      return CutPlanRepository::createRec($plan_array);

    
  }



}
