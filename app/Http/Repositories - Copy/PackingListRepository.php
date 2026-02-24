<?php

namespace App\Http\Repositories;

use App\BundleTicketSecondary;
use App\Buyer;
use App\Style;
use Carbon\Carbon;
use App\Carton;
use App\CartonPackingList;
use Illuminate\Http\Request;
use App\PackingList;
use App\DailyShift;
use App\Http\Resources\PackingListResource;
use App\Http\Resources\PackingListWithParentsResource;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use PDF;
use Illuminate\Validation\Rule;
use Exception;
use App\JobCard;
use App\BundleTicket;
use App\Http\Validators\PackingListCreateValidator;
use App\Http\Validators\PackingListUpdateValidator;
use App\PackingListDetail;
use App\PackingListSoc;
use App\PackingSocConsumption;
use App\Soc;
use Illuminate\Support\Facades\Log;
use PhpParser\Node\Expr\Exit_;
use PHPUnit\Util\Json;
use stdClass;

use Illuminate\Support\Facades\Auth;
use App\User;

class PackingListRepository
{
    public function show(PackingList $packinglist)
    {
        return response()->json(
            [
                'status' => 'success',
                'data' => new PackingListWithParentsResource($packinglist),
            ],
            200
        );
    }

    public static function createRec(array $rec)
    {

        $validator = Validator::make(
            $rec,
            PackingListCreateValidator::getCreateRules()
        );
        if ($validator->fails()) {
            throw new Exception($validator->errors());
        }

        try {
            $model = PackingList::create($rec);
        } catch (Exception $e) {

            throw new \App\Exceptions\GeneralException($e->getMessage());
        }
        return $model;
    }

    public static function updateRec($model_id, array $rec)
    {

        if (array_key_exists('id', $rec)) {
            throw new Exception("Packing List No cannot be modified.");
        }
        $model = PackingList::findOrFail($model_id);

        if (!$model->updated_at->eq(\Carbon\Carbon::parse($rec['updated_at']))) {
            $entity = (new \ReflectionClass($model))->getShortName();
            throw new \App\Exceptions\ConcurrencyCheckFailedException($entity);
        }
        Utilities::hydrate($model, $rec);
        $validator = Validator::make(
            $rec,
            PackingListUpdateValidator::getUpdateRules($model_id)
        );
        if ($validator->fails()) {
            throw new Exception($validator->errors());
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
        try {
            DB::beginTransaction();

            //  delete full packing list
            foreach($recs as $key => $val){
                $job_card = DB::table('job_cards')
                    ->select('*')
                    ->where('packing_list_no', '=', $val)
                    ->first();
                if(!is_null($job_card)){

                    throw new \App\Exceptions\GeneralException("Packing List Already Assign to job JobCard ".$job_card->id."");
                }
                $bundle_scan = DB::table('bundle_ticket_secondaries')
                    ->select('*')
                    ->where('packing_list_id', '=', $val)
                    ->first();
                if(!is_null($bundle_scan)){

                    throw new \App\Exceptions\GeneralException("Bundle Already Scanned for this Packing List, Bundle ID ".$bundle_scan->bundle_id."");
                }

                $details = DB::table('packing_list_details')
                    ->select('*')
                    ->where('packing_list_id', '=', $val)
                    ->get();

                if($details != null){
                    foreach($details as $rec){
                        DB::table('packing_soc_consumptions')->where('packing_list_detail_id', '=', $rec->id)->delete();
                    }
                }
                DB::table('packing_list_details')->where('packing_list_id', '=', $val)->delete();
                DB::table('carton_packing_list')->where('packing_list_id', '=', $val)->delete();
                DB::table('packing_list_soc')->where('packing_list_id', '=', $val)->delete();
                //DB::table('packing_lists')->where('id', '=', $val)->delete();

            }
            PackingList::destroy($recs);
            DB::commit();
            return response()->json(["status" => "success"], 200);
        } catch (Exception $e) {
            DB::rollBack();
            throw new \App\Exceptions\GeneralException($e->getMessage());
        }
    }

    public static function createAndUpdatePackingList($request)
    {
        try {
            DB::beginTransaction();

            //  get round method from 
            $round_method = DB::table('team_validation_status')
            ->select('cutting_tolerance')
            ->first();

            $method = 'round';
            if(!is_null($round_method)){
              $method = $round_method->cutting_tolerance;
            }

            $model = null;
            $event = $request->event;
            $packing_list_id = $request->packing_list_id;
            //
            $packing_list_soc = $request->packing_list_soc;
            $carton_packing_list = $request->carton_packing_list;

            $packing_list_soc_upd = $packing_list_soc["UPD"];
            $packing_list_soc_cre = $packing_list_soc["CRE"];
            $packing_list_soc_del = $packing_list_soc["DEL"];

            $carton_packing_list_upd = $carton_packing_list["UPD"];
            $carton_packing_list_cre = $carton_packing_list["CRE"];
            $carton_packing_list_del = $carton_packing_list["DEL"];


            if ($event == "CRE") {
                $rec['vpo'] = $request->vpo;
                $rec['shipment_mode'] = $request->shipment_mode;
                $rec['packing_list_date'] = $request->packing_list_date;
                $rec['packing_list_delivery_date'] = $request->packing_list_delivery_date;
                $rec['buyer_id'] = $request->buyer_id;
                $rec['customer_style_ref'] = $request->customer_style_ref;
                $rec['parameter_type'] = $request->parameter_type;
                $rec['calculated_no_of_cartons'] = $request->calculated_no_of_cartons;
                $rec['carton_number_format_id'] = $request->carton_number_format_id;
                $rec['sorting_json'] = json_encode($request->sorting_json);
                $rec['description'] = $request->description;
                $rec['destination'] = $request->destination;
                $rec['style_id'] = $request->style_id;
                // print_r($rec['sorting_json']);
                // $rec['sorting_json']=json_decode($request->sorting_json,true);

                $model = self::createRec($rec);
                $packing_list_id = $model->id;
            } elseif ($event == "UPD") {
                $model = PackingList::find($packing_list_id);
                $rec['vpo'] = $request->vpo;
                $rec['shipment_mode'] = $request->shipment_mode;
                $rec['packing_list_date'] = $request->packing_list_date;
                $rec['packing_list_delivery_date'] = $request->packing_list_delivery_date;
                $rec['parameter_type'] = $request->parameter_type;
                $rec['calculated_no_of_cartons'] = $request->calculated_no_of_cartons;
                $rec['carton_number_format_id'] = $request->carton_number_format_id;
                $rec['updated_at'] = $request->updated_at;
                $rec['sorting_json'] = $request->sorting_json;
                $rec['description'] = $request->description;
                $rec['destination'] = $request->destination;
                self::updateRec($model->id, $rec);

            }

            if (isset($packing_list_id)) {
                $model = PackingList::find($packing_list_id);
                //create soc
                foreach ($packing_list_soc_cre as $soc_cre) {

                    $soc = $soc_cre['soc_id'];
                    $new_soc = Soc::where('id',$soc)->first();
                    $soc_list = PackingListSoc::where('soc_id',$soc)->get();
                   // $tolerance = (floatval($new_soc->tolerance)>0)?floatval($new_soc->tolerance)/100 : 0;
                   $tolerance_json = $new_soc->tolerance_json;

                    $soc_qty = json_decode(json_encode($new_soc->qty_json),true);
                    $found = false;
                    foreach($soc_list as $rec){
                        //if($rec->id != $soc_cre['packing_list_soc_id']){
                        $found = true;
                        $qty = json_decode(json_encode($rec->quantity_json),true);
                        foreach($qty as $key1=>$val1){
                            if(!is_null($tolerance_json)){
                                //$soc_qty[$key1]= $soc_qty[$key1]+(ceil($soc_qty[$key1]*$tolerance))- $val1;
                                $soc_qty[$key1]= $soc_qty[$key1]+($method($soc_qty[$key1]*intval($tolerance_json[$key1])/100))- $val1;
                            }else{
                                $soc_qty[$key1]= $soc_qty[$key1]- $val1;
                            }
                        }
                        // }
                    }
                    $upd_qty = json_decode(json_encode($soc_cre['quantity_json']),true);

                    foreach($upd_qty as $key=>$val){
                        foreach($soc_qty as $key1=>$val1){
                            //print_r($soc_qty[$key1]-$upd_qty[$key]);
                            if(strcmp($key1, $key) == 0){
                                if(!$found){
                                    if(!is_null($tolerance_json)){
                                        if(($soc_qty[$key1]+($method($soc_qty[$key1]*intval($tolerance_json[$key1])/100))-$upd_qty[$key])<0){
                                            throw new Exception("Not Enough Quantity For Size ".$key1."");
                                        }
                                    }else{
                                        if(($soc_qty[$key1]-$upd_qty[$key])<0){
                                            throw new Exception("Not Enough Quantity For Size ".$key1."");
                                        }
                                    }
                                }else{
                                    if(($soc_qty[$key1]-$upd_qty[$key])<0){

                                        throw new Exception("Not Enough Quantity For Size ".$key1."");
                                    }
                                }
                            }
                        }
                    }
                    $soc_cre['packing_list_id'] = $packing_list_id;
                    PackingListSocRepository::createRec($soc_cre);

                }

                //update soc
                foreach ($packing_list_soc_upd as $soc_upd) {
                    $packing_list_soc = PackingListSoc::where('id',$soc_upd['packing_list_soc_id'])->first();
                    $soc_list = PackingListSoc::where('soc_id',$packing_list_soc->soc_id)->get();
                    $soc_qty = json_decode(json_encode($packing_list_soc->soc->qty_json),true);
                   // $tolerance = (floatval($packing_list_soc->soc->tolerance)>0)?floatval($packing_list_soc->soc->tolerance)/100 : 0;
                   $tolerance_json = $packing_list_soc->soc->tolerance_json;

                    $found = false;
                    foreach($soc_list as $rec){
                        if($rec->id != $soc_upd['packing_list_soc_id']){
                            $found = true;
                            $qty = json_decode(json_encode($rec->quantity_json),true);
                            foreach($qty as $key1=>$val1){
                                //$soc_qty[$key1]= intval($soc_qty[$key1])+(intval($soc_qty[$key1]*$tolerance))- intval($val1);
                                if(!is_null($tolerance_json)){
                                    $soc_qty[$key1]= intval($soc_qty[$key1])+($method($soc_qty[$key1]*intval($tolerance_json[$key1])/100))- intval($val1);
                                }else{
                                    $soc_qty[$key1]= intval($soc_qty[$key1]) - intval($val1);
                                }

                            }
                        }
                    }

                    $upd_qty = json_decode(json_encode($soc_upd['quantity_json']),true);
                    foreach($upd_qty as $key=>$val){
                        foreach($soc_qty as $key1=>$val1){
                            //print_r($soc_qty[$key1]-$upd_qty[$key]);
                            if(strcmp($key1, $key) == 0){
                                if(!$found){
                                    if(!is_null($tolerance_json)){
                                        if(($soc_qty[$key1]+($method($soc_qty[$key1]*intval($tolerance_json[$key1])/100))-$upd_qty[$key])<0){
                                            throw new Exception("Not Enough Quantity For Size ".$key1."");
                                        }
                                    }else{
                                        if(($soc_qty[$key1]+($method($soc_qty[$key1]))-$upd_qty[$key])<0){
                                            throw new Exception("Not Enough Quantity For Size ".$key1."");
                                        }
                                    }
                                }else{
                                    if(($soc_qty[$key1]-$upd_qty[$key])<0){
                                        throw new Exception("Not Enough Quantity For Size ".$key1."");
                                    }
                                }
                            }
                        }
                    }

                    PackingListSocRepository::updateRec($soc_upd['packing_list_soc_id'], $soc_upd);
                }

                //remove bundles
                PackingListSocRepository::deleteRecs($packing_list_soc_del);


                //create Carton
                foreach ($carton_packing_list_cre as $carton_cre) {
                    $carton_cre['packing_list_id'] = $packing_list_id;
                    $sum_ratio =  0;
                    foreach ($carton_cre['ratio_json'] as $key => $value) {
                        $sum_ratio = $sum_ratio + (is_null($value) ? 0 : $value);
                    }
                    //if( $sum_ratio >  $carton_cre['pcs_per_carton']){
                    //  throw new Exception("Ratio exceeds pcs per carton");
                    //}

                    $carton_cre['total_quantity'] = $carton_cre['pcs_per_carton'] * $carton_cre['no_of_cartons'];

                    $carton_type =  CartonPackingListRepository::createRec($carton_cre);

                    if($model->status == 'Revision'){
                        self::create_revision_box($carton_type, $packing_list_id,$model->carton_number_format_id);
                    }

                }

                //update Carton
                foreach ($carton_packing_list_upd as $carton_upd) {
                    $sum_ratio =  0;
                    foreach ($carton_upd['ratio_json'] as $key => $value) {
                        $sum_ratio = $sum_ratio + (is_null($value) ? 0 : $value);
                    }
                    if ($sum_ratio >  $carton_upd['pcs_per_carton']) {
                        throw new Exception("Ration exceeds pcs per carton");
                    }
                    $carton_upd['total_quantity'] = $carton_upd['pcs_per_carton'] * $carton_upd['no_of_cartons'];
                    if($model->status == 'Revision'){
                        self::_validate_box([$carton_upd['carton_packing_list_id']]);
                        $carton_type = CartonPackingListRepository::updateRec($carton_upd['carton_packing_list_id'], $carton_upd);
                        self::create_revision_box($carton_type, $packing_list_id,$model->carton_number_format_id);
                    }else{
                        CartonPackingListRepository::updateRec($carton_upd['carton_packing_list_id'], $carton_upd);
                    }

                }

                //remove Carton
                if($model->status == 'Revision'){
                    self::_validate_box($carton_packing_list_del);
                    CartonPackingListRepository::deleteRecs($carton_packing_list_del);
                }else{
                    CartonPackingListRepository::deleteRecs($carton_packing_list_del);
                }

            } else {
                throw new Exception("Insufficient information about Packing List");
            }
            if($model->status == 'Revision'){
                self::update_carton_numbering($packing_list_id,$model->carton_number_format_id);
            }
            DB::commit();
            return response()->json(["status" => "success", "data" => $model], 200);
        } catch (Exception $e) {
            DB::rollBack();
            throw new \App\Exceptions\GeneralException($e->getMessage());
        }
    }

    private static function create_revision_box($carton_type, $packing_list_id, $carton_number_format_id){

        // if ration json has only one
        $flag = false;
        if (self::_isJsonSingleElement($carton_type->ratio_json)) {
            $flag = true;
        }
        if ($flag) {
            $total = $carton_type->pcs_per_carton;
        } else {
            $total = array_reduce($carton_type->ratio_json, function ($carry, $item) {
                $carry = $carry + $item;
                return $carry;
            }, 0);
        }
        for ($i = 0; $i < $carton_type->no_of_cartons; $i++) {
            $detail_rec = [
                'packing_list_id' => $packing_list_id,
                'carton_number' => 0,
                'carton_packing_list_id' => $carton_type->id,
                'qty_json' => $carton_type->ratio_json,
                'total' => $total,
                'carton_id' => $carton_type->carton_id,
                'carton_no2' => 0
            ];
            $model = PackingListDetailRepository::createRec($detail_rec);
            //self::_createSocConsumption($packing_list_id, $model->id, $carton_param);
        }

    }

    public static function update_carton_numbering($packing_list_id,$numberingID){
        $box = CartonPackingList::select('packing_list_details.id','carton_packing_list.no_of_cartons','carton_packing_list.id as carton_id')
            ->join('packing_list_details', 'packing_list_details.carton_packing_list_id', '=', 'carton_packing_list.id')
            ->where('carton_packing_list.packing_list_id', $packing_list_id)
            ->orderBy('carton_packing_list.id')
            ->get();
        $pre_carton_id = 0;

        $box_no = 0;
        $total = 0;
        $str_box = "";

        $total_carton =0;

        foreach ($box as $box_data) {
            $total_carton += 1;
        }

        foreach($box as $rec){
            if(!($numberingID ==1 || $numberingID ==2) && $pre_carton_id != $rec->carton_id){
                $box_no =0;
                $pre_carton_id = $rec->carton_id;
            }

            if($numberingID == 1){
                $box_no++;
                $str_box = $box_no;
            }
            else if($numberingID == 2){
                $box_no++;
                $str_box = $box_no." of ".$total_carton;
            }
            else if($numberingID == 3){
                $box_no++;
                $str_box = $box_no;
            }
            else if($numberingID == 4){
                $box_no++;
                $str_box = $box_no." of ".$rec->no_of_cartons;
            }
            DB::table('packing_list_details')
                ->where('id', $rec->id)
                ->update(['carton_number' => $box_no,'carton_no2'=>$str_box]);
        }
    }

    public static function _validate_box($carton_packing_list_del){

        $update_box = CartonPackingList::select('packing_list_details.id')
            ->join('packing_list_details', 'packing_list_details.carton_packing_list_id', '=', 'carton_packing_list.id')
            ->join('bundle_ticket_secondaries', 'bundle_ticket_secondaries.carton_id', '=', 'packing_list_details.id')
            ->whereIn('carton_packing_list.id', $carton_packing_list_del)
            ->orderBy('carton_packing_list.id')
            ->first();

        if(!is_null($update_box) > 0){
            throw new \App\Exceptions\GeneralException('Box('.$update_box->id.') already scanned for this type of carton ');
        }else{
            DB::table('packing_soc_consumptions')
                ->join('packing_list_details', 'packing_list_details.id', '=', 'packing_soc_consumptions.packing_list_detail_id')
                ->join('carton_packing_list', 'carton_packing_list.id', '=', 'packing_list_details.carton_packing_list_id')
                ->whereIn('carton_packing_list.id', $carton_packing_list_del)
                ->delete();

            DB::table('packing_list_details')
                ->whereIn('carton_packing_list_id', $carton_packing_list_del)
                ->delete();


        }
    }

    public function finalizeRevisePackingList($request){
        try {
            DB::beginTransaction();

            DB::table('packing_lists')
                ->where('id', $request->packing_list_id)
                ->update(['status' => "Generated"]);

            DB::commit();
            return response()->json(["status" => "success"], 200);
        } catch (Exception $e) {
            DB::rollBack();
            throw new \App\Exceptions\GeneralException($e->getMessage());
        }
    }


    public static function getFullPackingList($packing_list_id)
    {
        /// get round method from 
        $round_method = DB::table('team_validation_status')
        ->select('cutting_tolerance')
        ->first();

        $method = 'round';
        if(!is_null($round_method)){
        $method = $round_method->cutting_tolerance;
        }

        $packing_list = PackingList::find($packing_list_id);
        $array = json_decode( $packing_list->sorting_json, true );
        $packing_list->sorting_json=$array;

        $packing_list->buyer_code = Buyer::find($packing_list->buyer_id)->buyer_code;
        $packing_list->buyer_name = Buyer::find($packing_list->buyer_id)->name;

        //get soc
        if(is_null($packing_list->style_id)){
            $tartget_soc = Soc::where('customer_style_ref', $packing_list->customer_style_ref)->where('buyer_id', $packing_list->buyer_id)->first();
            $packing_list->style = $tartget_soc->style;
        }else{
            $packing_list->style = Style::where('id', $packing_list->style_id)->first();
        }

        $packing_list_soc = PackingListSoc::where('packing_list_id', $packing_list->id)->get();
        foreach ($packing_list_soc as $value) {
            $soc = Soc::find($value['soc_id']);
           // $tolerance = (floatval($soc->tolerance)>0)?floatval($soc->tolerance)/100 : 0;

            $value['soc_no'] = $soc->wfx_soc_no;
            $value['garment_color'] = $soc->garment_color;
            //$value['balance_qty_json1'] = $soc->qty_json;
            $actual_tolerance_json = $soc->tolerance_json;

            if(!is_null($actual_tolerance_json)){
                $tolerance_json = [];
                foreach($soc->qty_json as $key => $val){

                    if(intval($val) > 0){
                        $tolerance_json[$key] = $val+ $method($val*intval($actual_tolerance_json[$key])/100);
                        //$value['balance_qty_json'][$key] = $val+ intval($val*$tolerance);
                    }
                    else{
                        $tolerance_json[$key] = $val;
                      //  $value['balance_qty_json'][$key] = $val;
                    }

                }
            }else{
				foreach($soc->qty_json as $key => $val){

                    if(intval($val) > 0){
                        $tolerance_json[$key] = $val;
                        //$value['balance_qty_json'][$key] = $val+ intval($val*$tolerance);
                    }
                    else{
                        $tolerance_json[$key] = $val;
                      //  $value['balance_qty_json'][$key] = $val;
                    }

                }
			}
            $value['balance_qty_json'] = $tolerance_json;


        }
       // print_r($packing_list_soc."/");
        foreach ($packing_list_soc as $value) {
            $balance=[];
            $all_soc = PackingListSoc::select('quantity_json')
                ->where('soc_id',$value['soc_id'])
                ->get();
            foreach ($all_soc as $rec) {
                foreach($rec['quantity_json'] as $key => $val){
                    // $value['balance_qty_json'][$key] =$value['balance_qty_json'][$key]-$val;

                    //$balance[$key]=(array_key_exists($key, $balance) ? $balance[$key]- $val: $value['balance_qty_json'][$key]-$val);
                    if(isset($balance[$key])){
                        $balance[$key] = $balance[$key]- $val;
                    }else if(isset($value['balance_qty_json'][$key])){
                        $balance[$key] = $value['balance_qty_json'][$key]-$val;
                    }
                    else{
                        $balance[$key] = 0;
                    }

                    //print_r();
                    // foreach($value['balance_qty_json'] as $key1 => $val1){
                    //   if($key1 == $key){
                    //     //print_r($value['balance_qty_json']$key1);
                    //   }
                    // }
                    //     $value['balance_qty_json'][$key] = intval($value['balance_qty_json'][$key])-intval($val);
                }

            };
            $value['balance_qty_json']=$balance;
        }

        //get soc balances
        $sum_soc_qty = [];
        $balc_soc_qty = [];
        $tot_box_qty = [];
        //$recs = PackingListSoc::select('quantity_json')->where('packing_list_id', $packing_list_id)->get();

        // foreach ($recs as $rec) {
        //   foreach ($rec['quantity_json'] as $key => $value) {
        //     $sum_soc_qty[$key] = $value + (array_key_exists($key, $sum_soc_qty) ? $sum_soc_qty[$key] : 0);
        //   }
        // }

        // $planned_json = CartonPackingListRepository::getPlannedTotals($packing_list_id);
        // foreach ($sum_soc_qty as $sockey => $socval) {
        //   $balc_soc_qty[$sockey] = $socval - $planned_json[$sockey];
        // }

        //get carton packinglist
        $carton_packing_list = CartonPackingList::where('packing_list_id', $packing_list->id)->get();

        // if ($carton_packing_list->count() > 0) {
        //   foreach ($carton_packing_list as $list) {
        //     $total = array_reduce($list->ratio_json, function ($carry, $item) {
        //       $carry = $carry + $item;
        //       return $carry;
        //     });

        //     $list['no_of_carton_generated'] = floor($total / $list['pcs_per_carton']);
        //   }
        // }

        return [
            "PackingList" => $packing_list,
            "PackingListSoc" => $packing_list_soc,
            // "SocTotals" => ['total' => $sum_soc_qty, 'balance' => $balc_soc_qty],
            "CartonPackingList" => $carton_packing_list
        ];
    }

    public function getPackingListBalanceQuantity($packing_list_id,$socNo)
    {

        $sum_soc_qty = [];
        $balc_soc_qty = [];
        //$recs = PackingListSoc::select('quantity_json')->where('packing_list_id', $packing_list_id)->get();

        $recs = PackingListSoc::select('quantity_json')
            ->whereIn('soc_id', $socNo)
            ->where('packing_list_id', $packing_list_id)
            ->get();

        if ($recs->count() > 0) {
            foreach ($recs as $k => $rec) {
                //$tolerance = (floatval($rec->tolerance) > 0) ? floatval($rec->tolerance)/100 : 0;

                foreach ($rec['quantity_json'] as $key => $value) {

                    $sum_soc_qty[$key] = $value + (array_key_exists($key, $sum_soc_qty) ? $sum_soc_qty[$key] : 0);
                }
            }
        } else {
            return response()->json(new stdClass());
        }

        // $packing_list_soc_consupmtion = PackingSocConsumption::select('packing_soc_consumptions.*')
        // ->join('packing_list_soc', 'packing_soc_consumptions.packing_list_soc_id', '=', 'packing_list_soc.id')
        // ->whereIn('packing_list_soc.soc_id', $socNo)
        // ->distinct()->get();

        //   foreach ($packing_list_soc_consupmtion as $rec) {
        //     foreach ($rec->qty_json as $key=>$value) {
        //       $amount = $value;
        //       if (!(is_null($value) || $value == 0)) {
        //         if (array_key_exists($key, $sum_soc_qty)) {
        //           $sum_soc_qty[$key] = $sum_soc_qty[$key] - $amount;
        //         } else {
        //         }
        //       }
        //    }
        //   }

        // $pack_ratio_sum = PackingListSoc::where(['packing_list_id' => $packing_list_id])->sum('pack_ratio');
        // if(!(intval($pack_ratio_sum)>0)){
        //   $pack_ratio_sum=1;
        // }

        $planned_json = CartonPackingListRepository::getPlannedTotals($packing_list_id);
        $pack_ratio_sum = PackingListSoc::where(['packing_list_id' => $packing_list_id])->sum('pack_ratio');
        if ($pack_ratio_sum == 0 ){
            $pack_ratio_sum = 1;
        }

        if (sizeof($planned_json) > 0) {
            foreach ($sum_soc_qty as $sockey => $socval) {
                $balc_soc_qty[$sockey] = $socval - (array_key_exists($sockey, $planned_json) ? $planned_json[$sockey]*$pack_ratio_sum : 0);
            }
        } else {
            $balc_soc_qty = $balc_soc_qty;
        }

        return $balc_soc_qty;
    }

    public function getCalculatedNoOfCartons($balance_json, $carton_json, $packing_list_id,$carton_id)
    {

        $pack_ratio_sum = PackingListSoc::where(['packing_list_id' => $packing_list_id])->sum('pack_ratio');
        if ($pack_ratio_sum == 0 ){
            $pack_ratio_sum = 1;
        }
        $min = 99999999999;

        // if(intval($carton_id) > 0){
        //   $carton_info = CartonPackingList::where('id', $carton_id)->first();
        //   foreach($carton_info->ratio_json as $key=>$value){
        //     $balance_json[$key] += $value;

        //   }
        // }
        foreach ($balance_json as $key => $value) {
            if (isset($carton_json[$key])) {
                if($carton_json[$key] > 0){
                    $val = floor($value / ($carton_json[$key] * $pack_ratio_sum));
                    if ($val < $min) {
                        $min = $val;
                    }
                }
            }
        }


        if ($min == 99999999999) {
            return response()->json(["status" => "success","no_of_ctn"=>"0","balance"=>$balance_json], 200);
            // return null;
        }
        // else if($min > 0){
        //   foreach($balance_json as $key=>$value){
        //     if($carton_json[$key] > 0){
        //       $balance_json[$key] -= ($carton_json[$key] * $pack_ratio_sum);
        //     }

        //   }
        // }
        return response()->json(["status" => "success","no_of_ctn"=>$min,"balance"=>$balance_json], 200);
    }

    public static function generatePackingListDetails($packing_list_id)
    {
        try {
            DB::beginTransaction();

            $authUser = Auth::user();
            User::where('id',$authUser->id)->lockForUpdate()->first();

            $numbering = PackingList::select('carton_number_format_id','status')->where('id', $packing_list_id)->get();
            $soc_qty_recs = PackingListSoc::select('quantity_json')->where('packing_list_id', $packing_list_id)->get();
            $carton_info = CartonPackingList::where('packing_list_id', $packing_list_id)->orderBy('id')->get();

            if (is_null($soc_qty_recs)) {
                throw new Exception("Incomplete information about Socs to be used.");
            }
            if (is_null($carton_info)) {
                throw new Exception("Incomplete information about Cartons to be used.");
            }

            $sum_soc_qty = [];
            foreach ($soc_qty_recs as $rec) {
                foreach ($rec['quantity_json'] as $key => $value) {
                    $sum_soc_qty[$key] = $value + (array_key_exists($key, $sum_soc_qty) ? $sum_soc_qty[$key] : 0);
                }
            }
            foreach($numbering as $key=>$value){
                $numberingID = $value['carton_number_format_id'];
                if($value['status'] != "Generated"){
                    DB::table('packing_lists')
                        ->where('id', $packing_list_id)
                        ->update(['status' => "Generated",'carton_number_format_id'=>$numberingID]);

                    self::simpleGeneration($carton_info, $packing_list_id,$numberingID);
                }
                else{
                    throw new Exception("This packing List already Generated");
                }
            }



            DB::commit();
            return response()->json(["status" => "success"], 200);
        } catch (Exception $e) {
            DB::rollBack();
            throw new \App\Exceptions\GeneralException($e->getMessage());
        }
    }

    private static function simpleGeneration($carton_param, $packing_list_id,$numberingID)
    {
        $box_no = 0;
        $flag = false;
        $total = 0;
        $qty_json = "";
        $str_box = "";

        $total_carton =0;

        foreach ($carton_param as $carton_data) {
            $total_carton += intval($carton_data['no_of_cartons']);
        }
        foreach ($carton_param as $carton_data) {
            $no_of_cartons = $carton_data['no_of_cartons'];
            $ratio_json = $carton_data['ratio_json'];

            if(!($numberingID ==1 || $numberingID ==2)){
                $box_no =0;
            }
            for ($i = 0; $i < $no_of_cartons; $i++) {
                if($numberingID == 1){
                    $box_no++;
                    $str_box = $box_no;
                }
                else if($numberingID == 2){
                    $box_no++;
                    $str_box = $box_no." of ".$total_carton;
                }
                else if($numberingID == 3){
                    $box_no++;
                    $str_box = $box_no;
                }
                else if($numberingID == 4){
                    $box_no++;
                    $str_box = $box_no." of ".$no_of_cartons;
                }

                // if ration json has only one
                if (self::_isJsonSingleElement($carton_data['ratio_json'])) {
                    $flag = true;
                    // $qty_json = $line['pcs_per_carton'];
                }
                // else {
                //   $qty_json = $line['ratio_json'];
                // }
                if ($flag) {
                    $total = $carton_data['pcs_per_carton'];
                } else {
                    $total = array_reduce($carton_data['ratio_json'], function ($carry, $item) {
                        $carry = $carry + $item;
                        return $carry;
                    }, 0);
                }
                $detail_rec = [
                    'packing_list_id' => $packing_list_id,
                    'carton_number' => $box_no,
                    'carton_packing_list_id' => $carton_data['id'],
                    'qty_json' => $carton_data['ratio_json'],
                    'total' => $total,
                    'carton_id' => $carton_data['carton_id'],
                    'carton_no2' => $str_box
                ];
                $model = PackingListDetailRepository::createRec($detail_rec);
                self::_createSocConsumption($packing_list_id, $model->id, $carton_param);
            }
        }
    }

    private static function _createSocConsumption($packing_list_id, $packing_list_detail_id, $carton_param)
    {
        foreach ($carton_param as $carton_data) {
            $no_of_cartons = $carton_data['no_of_cartons'];
            if ($no_of_cartons > 1) {
                foreach ($carton_data['ratio_json'] as $size => $value) {
                    if (!(is_null($value) || ($value == 0))) {
                        $line_total_size = $no_of_cartons * $carton_data['ratio_json'][$size];
                        $pack_ratio_sum = PackingListSoc::where(['packing_list_id' => $packing_list_id])->sum('pack_ratio');
                        $plss = PackingListSoc::where(['packing_list_id' => $packing_list_id])->get();
                        foreach ($plss as $pls) {
                            $soc_pack_ratio = $pls->pack_ratio;
                            $soc_consumption_size = $line_total_size * $soc_pack_ratio / $pack_ratio_sum;
                            $running_soc_size = $pls->quantity_json[$size];
                            if ($running_soc_size > $soc_consumption_size) {
                                PackingSocConsumptionRepository::createRec([
                                    'packing_list_detail_id' => $packing_list_detail_id,
                                    'packing_list_soc_id' => $pls->id,
                                    'qty_json' => [$size => $soc_consumption_size]
                                ]);
                                $running_soc_size -= $soc_consumption_size;
                            } else {
                                PackingSocConsumptionRepository::createRec([
                                    'packing_list_detail_id' => $packing_list_detail_id,
                                    'packing_list_soc_id' => $pls->id,
                                    'qty_json' => [$size => $pls->quantity_json[$size]]
                                ]);
                            }
                        }
                    }
                }
            } else {
                $pls_first = PackingListSoc::where(['packing_list_id' => $packing_list_id])->first();
                PackingSocConsumptionRepository::createRec([
                    'packing_list_detail_id' => $packing_list_detail_id,
                    'packing_list_soc_id' => $pls_first->id,
                    'qty_json' => $carton_data['ratio_json']
                ]);
            }
        }
    }

    private static function complexGeneration($packing_list_id, $sum_soc_qty, $carton_info)
    {
        foreach ($carton_info as $param) {
            $carton_params[$param->id] = ['size' => $param->pcs_per_carton, 'ratio' => $param->ratio_json, 'total' => $param->total_quantity];
        }

        //A = carton_id
        //size = pcs_per_carton
        //total = total_quantity;
        //ratio = ratio_json;

        // $carton_params = [
        // 'A' => [
        //   'size' => 100,
        //   'ratio' => ['S' => 100, 'M' => 0, 'L' => 0],
        //   'total' => 900
        // ],
        // 'B' => [
        //   'size' => 100,
        //   'ratio' => ['S' => 0, 'M' => 100, 'L' => 0],
        // ];
        //]
        // here A, B... = $param->id
        //$sum_soc_qty = ['S' => 10, 'M' => 20, 'L' => 30];

        $carton_index = 1;
        $carton_size_balance = '';
        $carton_type_balance = '';
        $soc_balance = $sum_soc_qty;
        $ratio = [];
        $carton_type = '';
        $packets = [];
        $boxes = [];

        foreach ($carton_params as $type => $type_props) {
            $carton_size_balance = $type_props['size'];
            $carton_type_balance = $type_props['total'];
            $ratio = $type_props['ratio'];
            self::_adjustRatio($ratio, $soc_balance);
            $carton_type = $type;
            self::_formulatePackingList($carton_type, $carton_index, $ratio, $soc_balance, $carton_size_balance,  $carton_type_balance, $packets);
        }
        $i = 1;
        foreach ($carton_params as $type => $value) {
            $box_size = $value['size'];
            $sum_of_qty_json = [];
            $sum_of_qtys = 0;
            foreach ($packets[$type] as $index => $qty_json) {
                $sum_of_qty_json = self::_add_jsons($sum_of_qty_json, $qty_json);
                $sum_of_qtys += array_reduce($qty_json, function ($carry, $item) {
                    $carry += $item;
                    return $carry;
                });
                if ($sum_of_qtys == $box_size) {
                    $boxes[$type][$i] = $sum_of_qty_json;
                    $sum_of_qtys = 0;
                    $sum_of_qty_json = [];
                    $i++;
                }
            }
        }

        // Saving Details to the Table
        $detail_rec = [];
        foreach ($boxes as $key => $box) {
            foreach ($box as $boxkey => $qty) {
                $detail_rec[] = [
                    'packing_list_id' => $packing_list_id,
                    'carton_number' => $boxkey,
                    'carton_packing_list_id' => $key,
                    'qty_json' => $qty,
                    'total' => array_reduce($qty, function ($carry, $item) {
                        $carry = $carry + $item;
                        return $carry;
                    })
                ];
            }
        }

        foreach ($detail_rec as $drec) {
            PackingListDetailRepository::createRec($drec);
        }

        // return ['packets' => $packets, 'boxes' => $boxes];

    }


    private static function _formulatePackingList($carton_type, &$carton_index, $ratio, &$soc_balance, &$carton_size_balance,  &$carton_type_balance, &$packets)
    {
        $carton_filled = false;

        while (!$carton_filled) {
            $ratio_sum = array_reduce($ratio, function ($carry, $item) {
                $carry += $item;
                return $carry;
            });

            $packets[$carton_type][$carton_index++] = $ratio;
            $carton_size_balance -= $ratio_sum;
            $carton_type_balance -= $ratio_sum;

            self::_adjustSocJson($soc_balance, $ratio);

            $carton_filled = ($carton_type_balance < $ratio_sum);
        }
        // $packets[$carton_type]['balance'] = $soc_balance;
    }

    private static function _adjustSocJson(&$first_arr, &$second_arr)
    {
        foreach ($second_arr as $key => $value) {
            $first_arr[$key] = $first_arr[$key] - $value;
            if ($first_arr[$key] < $value) {
                $second_arr[$key] = 0;
            }
        }
    }

    private static function _adjustRatio(&$ratio, $soc_balance)
    {
        foreach ($soc_balance as $key => $value) {
            if ($value == 0) {
                $ratio[$key] = 0;
            }
        }
    }

    private static function _add_jsons($sum_of_qty_json, $qty_json)
    {
        $ret = [];
        foreach ($qty_json as $key => $value) {
            $ret[$key] = (isset($sum_of_qty_json[$key]) ? $sum_of_qty_json[$key] : 0) + $qty_json[$key];
        }
        return $ret;
    }

    private static function _isJsonSingleElement($ratio_json)
    {

        $return_arr =  array_filter(
            $ratio_json,
            function ($v, $k) {
                return !(is_null($v) || ($v == 0 ? true : false));
            },
            ARRAY_FILTER_USE_BOTH
        );

        if (sizeof($return_arr) == 1) {
            return true;
        } else {
            return false;
        }
    }

    public function getPackingListLayOutReport($packing_list_id, $revision_no){

        try {
            DB::beginTransaction();


            $data = [];
            $data = ['ID' => $packing_list_id];

            $soc = DB::table('packing_list_soc')

                ->select('packing_list_soc.quantity_json','packing_list_soc.pack_ratio','socs.*','packing_lists.description','packing_lists.destination','packing_lists.vpo','packing_lists.carton_number_format_id','packing_lists.shipment_mode', 'packing_lists.status','packing_lists.packing_list_delivery_date','packing_lists.sorting_json', 'packing_lists.revision_no', 'packing_lists.current_vpo', 'styles.style_code')
                ->join('socs as socs', 'socs.id', '=', 'packing_list_soc.soc_id')
                ->join('packing_lists', 'packing_lists.id', '=', 'packing_list_soc.packing_list_id')
                ->join('styles', 'styles.id', '=', 'socs.style_id')
                ->where('packing_list_soc.packing_list_id', $packing_list_id)
                ->get();



            if($revision_no == ""){

                ////////////////////////////////   Check Packing List Status  /////////////////////////////

                foreach($soc as $rec){
                    if($rec->status != "Generated"){
                        throw new \App\Exceptions\GeneralException('Packing List is Not Generated !');
                    }
                }

                //////////////////////////////////////////////////////////////////////////////////////////

                $carton = DB::table('carton_packing_list')
                    ->select('carton_packing_list.*','cartons.carton_type','cartons.length','cartons.width','cartons.height')
                    ->join('cartons', 'cartons.id', '=', 'carton_packing_list.carton_id')
                    ->where('carton_packing_list.packing_list_id', $packing_list_id)
                    ->orderby('carton_packing_list.id')
                    ->get();
                

                $box = DB::table('packing_list_details')
                        ->where('packing_list_id','=',$packing_list_id)
                        ->orderby('carton_packing_list_id','ASC')
                        ->get();
                        $data = ['ID' => $packing_list_id,'soc'=>$soc,'carton'=>$carton, 'status'=>'Latest', 'revision_no'=>'','box'=>$box];

                        $count =0;
                        foreach($box as $rec){
                            $id = $rec->id;

                            $pk_in_status = DB::table('bundle_ticket_secondaries')
                              ->where('carton_id','=',$id)
                              ->get();

                              if(!is_null($pk_in_status)){
                                $box[$count]->PK_IN = "1";
                              }else{
                                $box[$count]->PK_IN = "0";
                              }
                            $count++;
                        }
            }else{
                $carton = DB::table('carton_packing_list_revision')
                    ->select('carton_packing_list_revision.*','cartons.carton_type','cartons.length','cartons.width','cartons.height')
                    ->join('cartons', 'cartons.id', '=', 'carton_packing_list_revision.carton_id')
                    ->where('carton_packing_list_revision.packing_list_id', $packing_list_id)
                    ->where('carton_packing_list_revision.revision_no', $revision_no)
                    ->orderby('carton_packing_list_revision.id')
                    ->get();
                $data = ['ID' => $packing_list_id,'soc'=>$soc,'carton'=>$carton, 'status'=>'InActive', 'revision_no'=>$revision_no];
            }

            // $carton = DB::table('carton_packing_list')
            // ->select('carton_packing_list.*')
            // ->where('carton_packing_list.packing_list_id', $packing_list_id)
            // ->orderby('id')
            // ->get();

            //return $data;
            $pdf = PDF::loadView('print.pllreport', $data);
            $pdf->setPaper('A4', 'landscape');

            DB::commit();

            return $pdf->stream('trims_report_' . date('Y_m_d_H_i_s') . '.pdf');
        } catch (Exception $e) {
            DB::rollBack();
            throw new \App\Exceptions\GeneralException($e->getMessage());
        }

    }

    public function updateBoxScanningOld($request){

        try {
            DB::beginTransaction();
            $data =  DB::table('packing_list_details')->select('*')->where('id', $request->box_id)->get();

            if(sizeof($data) ===0){
                return response()->json(["status" => "invalid_box"], 201);
            }


            $export = null;
            $intoWh = null;
            $exportTime = null;
            $intoWhTime = null;
            $teamID = null;
            $intoWh_shift = null;
            $intoWh_slot = null;
            $export_shift = null;
            $export_slot = null;
            $flag = true;
            $dailyShiftTeamId = null;
            //$job_card_no = null;

            $entryFlag = true;

            foreach($data as $rec){
                $export = $rec->export;
                $intoWh = $rec->into_wh;
                $exportTime = $rec->export_time;
                $intoWhTime = $rec->into_wh_time;
                $teamID = $rec->team_id;
                $intoWh_shift =$rec->into_wh_shift;
                $intoWh_slot =$rec->into_wh_slot;
                $export_shift =$rec->export_shift;
                $export_slot =$rec->export_slot;
                // $job_card_no = $rec->job_card_no;

            }
            $teamID = $request->team_id;
            $shiftIDWanted = $request->shift;
            $currentDate = null;
            $currentDay = null;
            if(Carbon::now('GMT')->addMinutes(330)->format('H:i:s') < '08:30:00' && $shiftIDWanted == 2){
                $currentDate = Carbon::now('GMT')->subMinutes(510)->format('y-m-d');
                $currentDay = Carbon::now('GMT')->subMinutes(510)->format('l');
            }
            else if(Carbon::now('GMT')->addMinutes(330)->format('H:i:s') < '06:30:00' && $shiftIDWanted == 1){
                $currentDate = Carbon::now('GMT')->subMinutes(390)->format('y-m-d');
                $currentDay = Carbon::now('GMT')->subMinutes(390)->format('l');
            }
            else if(Carbon::now('GMT')->addMinutes(330)->format('H:i:s') > '08:30:00' && $shiftIDWanted == 2){
                $currentDate = Carbon::now('GMT')->addMinutes(330)->format('y-m-d');
                $currentDay = Carbon::now('GMT')->addMinutes(330)->format('l');
            }
            else if(Carbon::now('GMT')->addMinutes(330)->format('H:i:s') > '06:30:00' && $shiftIDWanted == 1){
                $currentDate = Carbon::now('GMT')->addMinutes(330)->format('y-m-d');
                $currentDay = Carbon::now('GMT')->addMinutes(330)->format('l');
            }
            else{
                $currentDate = Carbon::now('GMT')->addMinutes(330)->format('y-m-d');
                $currentDay = Carbon::now('GMT')->addMinutes(330)->format('l');
            }


            try {
                $shiftDetailId = DB::table('shift_details')
                    ->select('id')
                    ->where('shift_id', $shiftIDWanted)
                    ->where('day', $currentDay)
                    ->first();
            }catch (Exception $e){
                return response()->json(["status" => "other","Msg"=>"Sorry! Data retrieving failed! - Table name -> Shift details"], 201);
            }

            try{
                $dailyShiftId = DB::table('daily_shifts')
                    ->select('id')
                    ->where(['shift_detail_id' => $shiftDetailId->id])
                    ->where('current_date', $currentDate)
                    ->first();
            }catch (Exception $e){
                return response()->json(["status" => "other","Msg"=>"Sorry! Data retrieving failed! - Table name -> Daily shifts"], 201);
            }

            try{
                $dailyShiftTeamId = DB::table('daily_shift_teams')
                    ->select('id')
                    ->where('team_id', $request->team_id)
                    ->where(['daily_shift_id' => $dailyShiftId->id])
                    ->where('current_date' , $currentDate)
                    ->first();
            }catch (Exception $e){
                return response()->json(["status" => "other","Msg"=>"Sorry! Data retrieving failed! - Table name -> Daily shift teams"], 201);
            }

            try{
                $dailyScanningSlotId = DB::table('daily_scanning_slots')
                    ->select('id')
                    ->where('seq_no', $request->slot)
                    ->where(['daily_shift_id' => $dailyShiftId->id])
                    ->first();
            }catch (Exception $e){
                return response()->json(["status" => "other","Msg"=>"Sorry! Data retrieving failed! - Table name -> daily_scanning_slots"], 201);
            }

            $dailyShiftTeamIdX = $dailyShiftTeamId->id;
            $dailyScanSlotIdX = $dailyScanningSlotId->id;

            if( $request->entry_type == "1"){
                if($export =="EXPORT"){
                    $entryFlag=false;
                }

                if($data[0]->export == "EXPORT"){
                    return response()->json(["status" => "other","Msg"=>"Box Already Scanned"], 201);
                }
                $export = "EXPORT";
                $exportTime = date("Y-m-d h:i:s");
                $export_shift =$request->shift;
                $export_slot =$request->slot;

                if(intval($request->team_id)>0){
                    $teamID = $request->team_id;
                }
                if($intoWh != "INTO WH"){
                    $flag = false;

                }
            }
            else if($request->entry_type == "2"){
                if($data[0]->into_wh == "INTO WH"){
                    return response()->json(["status" => "other","Msg"=>"Box Already Scanned"], 201);
                }
                if($intoWh =="INTO WH"){
                    $entryFlag=false;
                }
                $intoWh = "INTO WH";
                $intoWhTime = date("Y-m-d h:i:s");
                $teamID = $request->team_id;

                $intoWh_shift =$request->shift;
                $intoWh_slot =$request->slot;



                //////////////////////////////  Update Bundle   /////////////////////
                try {
                    $packing_list_id = DB::table('packing_list_details')
                        ->select('packing_list_id')
                        ->where('id', $request->box_id)
                        ->first();
                }
                catch (Exception $e){
                    return response()->json(["status" => "other","Msg"=>"Sorry! Data retrieving failed! - Table name -> packing_list_details"], 201);
                }

                $pack_ratio_sum = PackingListSoc::where(['packing_list_id' => $packing_list_id->packing_list_id])->sum('pack_ratio');
                if ($pack_ratio_sum == 0 ){
                    $pack_ratio_sum = 1;
                }

                try {
                    $qty_json = DB::table('packing_list_details')
                        ->select('qty_json')
                        ->where('id', $request->box_id)
                        ->first();
                }
                catch(Exception $e){
                    return response()->json(["status" => "other","Msg"=>"Sorry! Data retrieving failed! - Table name -> packing_list_details"], 201);
                }


                $qty_json=json_decode($qty_json->qty_json);

                foreach($qty_json as $key=>$value){
                    if(intval($value) > 0){
                        $bundle = DB::table('bundle_tickets')
                            ->select('bundle_tickets.direction','bundle_tickets.id', 'bundle_tickets.bundle_id','bundles.quantity as original_quantity', DB::raw('SUM(IFNULL(bundle_ticket_secondaries.scan_quantity, 0)) as scan_quantity '),'bundles.size')
                            ->join('bundle_ticket_secondaries', 'bundle_ticket_secondaries.bundle_ticket_id','=','bundle_tickets.id')
                            ->join('fpo_operations', 'fpo_operations.id','=','bundle_tickets.fpo_operation_id')
                            ->join('bundles', 'bundles.id','=','bundle_tickets.bundle_id')
                            ->join('routing_operations', 'routing_operations.id','=','fpo_operations.routing_operation_id')
							->groupByRaw('bundle_tickets.id')
                            ->where('bundle_ticket_secondaries.packing_list_id', $packing_list_id->packing_list_id)
                            //->Where('routing_operations.operation_code', 'like', '%' . 'PK' . '%')
                            ->where('fpo_operations.operation', 'PK')
                            ->where('bundle_tickets.direction', 'IN')
                            ->orderby('bundle_tickets.bundle_id','ASC')
                            ->orderby('bundle_tickets.direction','ASC')
                            ->get();


                        $val = $value*$pack_ratio_sum;

                        $qty =0;
                        foreach($bundle as $rec){

                            if($rec->size == $key){

                                if($rec->direction == 'IN' && $val > 0){
                                    /////////  Some time Qty Can be Revised  ///////////yyyy
                                    $qty = is_null($rec->scan_quantity) ? 0 : $rec->scan_quantity;
                                    //print_r($qty."/");
                                    $pk_ticket_id = 0;
                                    $pk_out_ticket = DB::table('bundle_tickets')
                                        ->select('bundle_tickets.id as ticket_id', 'bundle_ticket_secondaries.*')
                                        ->join('bundle_ticket_secondaries', 'bundle_ticket_secondaries.bundle_ticket_id','=','bundle_tickets.id')
                                        ->join('fpo_operations', 'fpo_operations.id','=','bundle_tickets.fpo_operation_id')
                                        ->join('bundles', 'bundles.id','=','bundle_tickets.bundle_id')
                                        ->join('routing_operations', 'routing_operations.id','=','fpo_operations.routing_operation_id')
                                        ->where('bundle_tickets.bundle_id', $rec->bundle_id)
                                        ->where('bundle_tickets.direction', 'OUT')
                                        //->where('bundle_ticket_secondaries.packing_list_id', $packing_list_id->packing_list_id)
                                        ->where('fpo_operations.operation', 'PK')
                                        ->orderby('bundle_tickets.bundle_id','ASC')
                                        ->orderby('bundle_tickets.direction','ASC')
                                        ->get();


                                    if($pk_out_ticket->count() > 0){
                                        foreach($pk_out_ticket as $rec_scan){
                                            $qty -= is_null($rec_scan->scan_quantity) ? 0 : $rec_scan->scan_quantity;
                                            $pk_ticket_id = $rec_scan->ticket_id;
                                            // print_r($pk_ticket_id."=".$qty.'/');
                                        }
                                    }
                                    else{
                                        $pk_out_ticket = DB::table('bundle_tickets')
                                            ->select('bundle_tickets.id as ticket_id')
                                            ->join('fpo_operations', 'fpo_operations.id','=','bundle_tickets.fpo_operation_id')
                                            ->join('bundles', 'bundles.id','=','bundle_tickets.bundle_id')
                                            ->join('routing_operations', 'routing_operations.id','=','fpo_operations.routing_operation_id')
                                            ->where('bundle_tickets.bundle_id', $rec->bundle_id)
                                            ->where('bundle_tickets.direction', 'OUT')
                                            ->where('fpo_operations.operation', 'PK')
                                            //  ->Where('routing_operations.operation_code', 'like', '%' . 'PK' . '%')
                                            ->first();

                                        $pk_ticket_id = $pk_out_ticket->ticket_id;
                                    }


                                    $new_scan = 0;
                                    if($qty >= $val && $val > 0){
                                        $new_scan = $val;
                                        $val =0;
                                    }
                                    else if($val > $qty){
                                        $new_scan = $qty;
                                        $val -= $qty;
                                    }

                                    //////////////   Update ticket table  /////////////////
                                    if($new_scan > 0){


                                        $update_ticket = BundleTicket::select('scan_quantity', 'original_quantity')
                                            ->where('id' , $pk_ticket_id)
                                            ->first();


                                        $qty = (is_null($update_ticket->scan_quantity) ? 0 : $update_ticket->scan_quantity) + $new_scan;
                                        if($qty <= $update_ticket->original_quantity){
                                            DB::table('bundle_tickets')
                                                ->where('id', $pk_ticket_id)
                                                ->update(['scan_quantity' => $qty, 'daily_shift_team_id' => $dailyShiftTeamIdX, 'scan_date_time' => now('Asia/Kolkata'), 'packing_list_id' => $packing_list_id->packing_list_id, 'daily_scanning_slot_id' => $dailyScanSlotIdX, 'carton_id' => $request->box_id,'updated_by' => $request->user_id]);

                                            DB::insert('insert into bundle_ticket_secondaries (bundle_id, original_quantity, scan_quantity, packing_list_id,bundle_ticket_id,scan_date_time,daily_scanning_slot_id,daily_shift_team_id,carton_id,updated_by, created_by, created_at, updated_at) values (?, ?, ?, ?,?, ?, ?, ?, ?,?,?,?,?)',
                                                [$rec->bundle_id, $rec->original_quantity, $new_scan, $packing_list_id->packing_list_id, $pk_ticket_id, now('Asia/Kolkata'), $dailyScanSlotIdX, $dailyShiftTeamIdX,$request->box_id, $request->user_id, $request->user_id, now('Asia/Kolkata'), now('Asia/Kolkata')]);

                                        }else{
                                            $val += $new_scan;
                                            //return response()->json(["status" => "Error", "Msg" => "exceed PK Out Qty For Bundle Ticket".$pk_ticket_id], 201);
                                        }
                                    }
                                }
                            }

                        }
                        if($val > 0){

                            DB::rollBack();
                            return response()->json(["status" => "Insufficient_Qty","Msg"=>"Insufficient Allocated Bundle Qty for Size ".$key." in Packing Scan Entry, Packing List Id ".$packing_list_id->packing_list_id.""], 201);
                        }

                    }
                }
            }

            if(!$entryFlag){
                DB::rollBack();
                return response()->json(["status" => "error"], 206);
            }

            else if($flag){
                DB::table('packing_list_details')
                    ->where('id', $request->box_id)
                    ->update(['into_wh' => $intoWh,'into_wh_time'=>$intoWhTime,'export'=>$export,'export_time'=>$exportTime,'team_id'=>$teamID,'into_wh_shift'=>$intoWh_shift,'into_wh_slot'=>$intoWh_slot,'export_shift'=>$export_shift,'export_slot'=>$export_slot, 'updated_by'=>$request->user_id]);
                DB::commit();
                return response()->json(["status" => "success"], 200);
            }
            else{
                DB::rollBack();
                return response()->json(["status" => "error"], 205);
            }
        } catch (Exception $e) {

            //throw new \App\Exceptions\GeneralException($e->getMessage());
            $matchFoundStart = str_starts_with($e->getMessage() , 'Trying to get property');
            $matchFoundEnd = str_ends_with($e->getMessage() , 'of non-object');
            if($matchFoundStart && $matchFoundEnd){

                return response()->json(["status" => "Error", "Msg" => "Data retrieving error. Please make sure that the scanned Entry type, Shift, Team, Slot & Box are correct and valid for today (" .Carbon::now()->format('d-m-y'). "). If those are correct & valid then contact a system administrator."], 201);
            }
            else {
                return response()->json(["status" => "Error", "Msg" => $e->getMessage()], 201);
            }
        }
    }

    public function updateBoxScanning($request){

        try {
            DB::beginTransaction();
            $data =  DB::table('packing_list_details')->select('*')->where('id', $request->box_id)->get();

            if(sizeof($data) ===0){
                return response()->json(["status" => "invalid_box"], 201);
            }


            $export = null;
            $intoWh = null;
            $exportTime = null;
            $intoWhTime = null;
            $teamID = null;
            $intoWh_shift = null;
            $intoWh_slot = null;
            $export_shift = null;
            $export_slot = null;
            $flag = true;
            $dailyShiftTeamId = null;
            //$job_card_no = null;

            $entryFlag = true;

            foreach($data as $rec){
                $export = $rec->export;
                $intoWh = $rec->into_wh;
                $exportTime = $rec->export_time;
                $intoWhTime = $rec->into_wh_time;
                $teamID = $rec->team_id;
                $intoWh_shift =$rec->into_wh_shift;
                $intoWh_slot =$rec->into_wh_slot;
                $export_shift =$rec->export_shift;
                $export_slot =$rec->export_slot;
            }
            $teamID = $request->team_id;
            $shiftIDWanted = $request->shift;
            $currentDate = null;
            $currentDay = null;
            if(Carbon::now('GMT')->addMinutes(330)->format('H:i:s') < '08:30:00' && $shiftIDWanted == 2){
                $currentDate = Carbon::now('GMT')->subMinutes(510)->format('y-m-d');
                $currentDay = Carbon::now('GMT')->subMinutes(510)->format('l');
            }
            else if(Carbon::now('GMT')->addMinutes(330)->format('H:i:s') < '06:30:00' && $shiftIDWanted == 1){
                $currentDate = Carbon::now('GMT')->subMinutes(390)->format('y-m-d');
                $currentDay = Carbon::now('GMT')->subMinutes(390)->format('l');
            }
            else if(Carbon::now('GMT')->addMinutes(330)->format('H:i:s') > '08:30:00' && $shiftIDWanted == 2){
                $currentDate = Carbon::now('GMT')->addMinutes(330)->format('y-m-d');
                $currentDay = Carbon::now('GMT')->addMinutes(330)->format('l');
            }
            else if(Carbon::now('GMT')->addMinutes(330)->format('H:i:s') > '06:30:00' && $shiftIDWanted == 1){
                $currentDate = Carbon::now('GMT')->addMinutes(330)->format('y-m-d');
                $currentDay = Carbon::now('GMT')->addMinutes(330)->format('l');
            }
            else{
                $currentDate = Carbon::now('GMT')->addMinutes(330)->format('y-m-d');
                $currentDay = Carbon::now('GMT')->addMinutes(330)->format('l');
            }


            try {
                $shiftDetailId = DB::table('shift_details')
                    ->select('id')
                    ->where('shift_id', $shiftIDWanted)
                    ->where('day', $currentDay)
                    ->first();
            }catch (Exception $e){
                return response()->json(["status" => "other","Msg"=>"Sorry! Data retrieving failed! - Table name -> Shift details"], 201);
            }

            try{
                $dailyShiftId = DB::table('daily_shifts')
                    ->select('id')
                    ->where(['shift_detail_id' => $shiftDetailId->id])
                    ->where('current_date', $currentDate)
                    ->first();
            }catch (Exception $e){
                return response()->json(["status" => "other","Msg"=>"Sorry! Data retrieving failed! - Table name -> Daily shifts"], 201);
            }

            try{
                $dailyShiftTeamId = DB::table('daily_shift_teams')
                    ->select('id')
                    ->where('team_id', $request->team_id)
                    ->where(['daily_shift_id' => $dailyShiftId->id])
                    ->where('current_date' , $currentDate)
                    ->first();
            }catch (Exception $e){
                return response()->json(["status" => "other","Msg"=>"Sorry! Data retrieving failed! - Table name -> Daily shift teams"], 201);
            }

            try{
                $dailyScanningSlotId = DB::table('daily_scanning_slots')
                    ->select('id')
                    ->where('seq_no', $request->slot)
                    ->where(['daily_shift_id' => $dailyShiftId->id])
                    ->first();
            }catch (Exception $e){
                return response()->json(["status" => "other","Msg"=>"Sorry! Data retrieving failed! - Table name -> daily_scanning_slots"], 201);
            }

            $dailyShiftTeamIdX = $dailyShiftTeamId->id;
            $dailyScanSlotIdX = $dailyScanningSlotId->id;

            if( $request->entry_type == "1"){
                if($export =="EXPORT"){
                    $entryFlag=false;
                }

                if($data[0]->export == "EXPORT"){
                    return response()->json(["status" => "other","Msg"=>"Box Already Scanned"], 201);
                }
                $export = "EXPORT";
                $exportTime = date("Y-m-d h:i:s");
                $export_shift =$request->shift;
                $export_slot =$request->slot;

                if(intval($request->team_id)>0){
                    $teamID = $request->team_id;
                }
                if($intoWh != "INTO WH"){
                    $flag = false;

                }
            }
            else if($request->entry_type == "2"){
                if($data[0]->into_wh == "INTO WH"){
                    return response()->json(["status" => "other","Msg"=>"Box Already Scanned"], 201);
                }
                if($intoWh =="INTO WH"){
                    $entryFlag=false;
                }
                $intoWh = "INTO WH";
                $intoWhTime = date("Y-m-d h:i:s");
                $teamID = $request->team_id;

                $intoWh_shift =$request->shift;
                $intoWh_slot =$request->slot;



                //////////////////////////////  Update Bundle   /////////////////////
                try {
                    $packing_list_id = DB::table('packing_list_details')
                        ->select('packing_list_id')
                        ->where('id', $request->box_id)
                        ->first();
                }
                catch (Exception $e){
                    return response()->json(["status" => "other","Msg"=>"Sorry! Data retrieving failed! - Table name -> packing_list_details"], 201);
                }

                $pack_ratio_sum = PackingListSoc::where(['packing_list_id' => $packing_list_id->packing_list_id])->sum('pack_ratio');
                if ($pack_ratio_sum == 0 ){
                    $pack_ratio_sum = 1;
                }

                try {
                    $qty_json = DB::table('packing_list_details')
                        ->select('qty_json')
                        ->where('id', $request->box_id)
                        ->first();
                }
                catch(Exception $e){
                    return response()->json(["status" => "other","Msg"=>"Sorry! Data retrieving failed! - Table name -> packing_list_details"], 201);
                }


                $qty_json=json_decode($qty_json->qty_json);

                foreach($qty_json as $key=>$value){
                    if(intval($value) > 0){
                        $bundle = DB::table('bundle_tickets')
                            ->select('bundle_tickets.direction', 'bundle_tickets.bundle_id', 'bundle_ticket_secondaries.*','bundles.size')
                            ->join('bundle_ticket_secondaries', 'bundle_ticket_secondaries.bundle_ticket_id','=','bundle_tickets.id')
                            ->join('fpo_operations', 'fpo_operations.id','=','bundle_tickets.fpo_operation_id')
                            ->join('bundles', 'bundles.id','=','bundle_tickets.bundle_id')
                            ->join('routing_operations', 'routing_operations.id','=','fpo_operations.routing_operation_id')
                            ->where('bundle_ticket_secondaries.packing_list_id', $packing_list_id->packing_list_id)
                            ->Where('routing_operations.operation_code', 'like', '%' . 'PK' . '%')
                            ->where('bundle_tickets.direction', 'IN')
                            ->orderby('bundle_tickets.bundle_id','ASC')
                            ->orderby('bundle_tickets.direction','ASC')
                            ->get();

                        $val = $value*$pack_ratio_sum;

                        $qty =0;
                        foreach($bundle as $rec){

                            if($rec->size == $key){

                                if($rec->direction == 'IN' && $val > 0){
                                    /////////  Some time Qty Can be Revised  ///////////yyyy
                                    $qty = is_null($rec->scan_quantity) ? 0 : $rec->scan_quantity;
                                    //print_r($qty."/");
                                    $pk_ticket_id = 0;
                                    $pk_out_ticket = DB::table('bundle_tickets')
                                        ->select('bundle_tickets.id as ticket_id', 'bundle_ticket_secondaries.*')
                                        ->join('bundle_ticket_secondaries', 'bundle_ticket_secondaries.bundle_ticket_id','=','bundle_tickets.id')
                                        ->join('fpo_operations', 'fpo_operations.id','=','bundle_tickets.fpo_operation_id')
                                        ->join('bundles', 'bundles.id','=','bundle_tickets.bundle_id')
                                        ->join('routing_operations', 'routing_operations.id','=','fpo_operations.routing_operation_id')
                                        ->where('bundle_tickets.bundle_id', $rec->bundle_id)
                                        ->where('bundle_tickets.direction', 'OUT')
                                        ->where('bundle_ticket_secondaries.packing_list_id', $packing_list_id->packing_list_id)
                                        ->Where('routing_operations.operation_code', 'like', '%' . 'PK' . '%')
                                        ->orderby('bundle_tickets.bundle_id','ASC')
                                        ->orderby('bundle_tickets.direction','ASC')
                                        ->get();


                                    if($pk_out_ticket->count() > 0){
                                        foreach($pk_out_ticket as $rec_scan){
                                            $qty -= is_null($rec_scan->scan_quantity) ? 0 : $rec_scan->scan_quantity;
                                            $pk_ticket_id = $rec_scan->ticket_id;
                                            // print_r($pk_ticket_id."=".$qty.'/');
                                        }
                                    }
                                    else{
                                        $pk_out_ticket = DB::table('bundle_tickets')
                                            ->select('bundle_tickets.id as ticket_id')
                                            ->join('fpo_operations', 'fpo_operations.id','=','bundle_tickets.fpo_operation_id')
                                            ->join('bundles', 'bundles.id','=','bundle_tickets.bundle_id')
                                            ->join('routing_operations', 'routing_operations.id','=','fpo_operations.routing_operation_id')
                                            ->where('bundle_tickets.bundle_id', $rec->bundle_id)
                                            ->where('bundle_tickets.direction', 'OUT')
                                            ->Where('routing_operations.operation_code', 'like', '%' . 'PK' . '%')
                                            ->first();

                                        $pk_ticket_id = $pk_out_ticket->ticket_id;
                                    }


                                    $new_scan = 0;
                                    if($qty >= $val && $val > 0){
                                        $new_scan = $val;
                                        $val =0;
                                    }
                                    else if($val > $qty){
                                        $new_scan = $qty;
                                        $val -= $qty;
                                    }

                                    //////////////   Update ticket table  /////////////////
                                    if($new_scan > 0){


                                        $update_ticket = BundleTicket::select('scan_quantity', 'original_quantity')
                                            ->where('id' , $pk_ticket_id)
                                            ->first();


                                        $qty = (is_null($update_ticket->scan_quantity) ? 0 : $update_ticket->scan_quantity) + $new_scan;
                                        if($qty <= $update_ticket->original_quantity){
                                            DB::table('bundle_tickets')
                                                ->where('id', $pk_ticket_id)
                                                ->update(['scan_quantity' => $qty, 'daily_shift_team_id' => $dailyShiftTeamIdX, 'scan_date_time' => now('Asia/Kolkata'), 'packing_list_id' => $packing_list_id->packing_list_id, 'daily_scanning_slot_id' => $dailyScanSlotIdX, 'carton_id' => $request->box_id,'updated_by' => $request->user_id]);

                                            DB::insert('insert into bundle_ticket_secondaries (bundle_id, original_quantity, scan_quantity, packing_list_id,bundle_ticket_id,scan_date_time,daily_scanning_slot_id,daily_shift_team_id,carton_id,updated_by, created_by, created_at, updated_at) values (?, ?, ?, ?,?, ?, ?, ?, ?,?,?,?,?)',
                                                [$rec->bundle_id, $rec->original_quantity, $new_scan, $packing_list_id->packing_list_id, $pk_ticket_id, now('Asia/Kolkata'), $dailyScanSlotIdX, $dailyShiftTeamIdX,$request->box_id, $request->user_id, $request->user_id, now('Asia/Kolkata'), now('Asia/Kolkata')]);

                                        }else{
                                            $val += $new_scan;
                                            //return response()->json(["status" => "Error", "Msg" => "exceed PK Out Qty For Bundle Ticket".$pk_ticket_id], 201);
                                        }
                                    }
                                }
                            }

                        }
                        if($val > 0){

                            DB::rollBack();
                            return response()->json(["status" => "Insufficient_Qty","Msg"=>"Insufficient Allocated Bundle Qty for Size ".$key." in Packing Scan Entry, Packing List Id ".$packing_list_id->packing_list_id.""], 201);
                        }

                    }
                }
            }

            if(!$entryFlag){
                DB::rollBack();
                return response()->json(["status" => "error"], 206);
            }

            else if($flag){
                DB::table('packing_list_details')
                    ->where('id', $request->box_id)
                    ->update(['into_wh' => $intoWh,'into_wh_time'=>$intoWhTime,'export'=>$export,'export_time'=>$exportTime,'team_id'=>$teamID,'into_wh_shift'=>$intoWh_shift,'into_wh_slot'=>$intoWh_slot,'export_shift'=>$export_shift,'export_slot'=>$export_slot, 'updated_by'=>$request->user_id]);
                DB::commit();
                return response()->json(["status" => "success"], 200);
            }
            else{
                DB::rollBack();
                return response()->json(["status" => "error"], 205);
            }
        } catch (Exception $e) {

            //throw new \App\Exceptions\GeneralException($e->getMessage());
            $matchFoundStart = str_starts_with($e->getMessage() , 'Trying to get property');
            $matchFoundEnd = str_ends_with($e->getMessage() , 'of non-object');
            if($matchFoundStart && $matchFoundEnd){

                return response()->json(["status" => "Error", "Msg" => "Data retrieving error. Please make sure that the scanned Entry type, Shift, Team, Slot & Box are correct and valid for today (" .Carbon::now()->format('d-m-y'). "). If those are correct & valid then contact a system administrator."], 201);
            }
            else {
                return response()->json(["status" => "Error", "Msg" => $e->getMessage()], 201);
            }
        }
    }

    public function getFgScanningListOld($request){
        $List=[];
        if($request->entry_type == 2){

            $List = DB::table('packing_list_details')
                ->select('id','packing_list_details.qty_json')
                ->where('packing_list_details.into_wh_time','>', date('Y-m-d'))
                ->where('packing_list_details.into_wh_shift', $request->shift)
                //->where('packing_list_details.into_wh_slot', $request->slot)
                ->where('packing_list_details.team_id', $request->team_id)
                ->orderby('packing_list_details.into_wh_time','desc')
                ->get();
        }
        else if($request->entry_type == 1){
            $List = DB::table('packing_list_details')
                ->select('id','packing_list_details.qty_json')
                ->where('packing_list_details.into_wh_time','>', date("Y-m-d"))
                ->where('packing_list_details.export_shift', $request->shift)
                ->where('packing_list_details.export_slot', $request->slot)
                // ->where('packing_list_details.team_id', $request->team_id)
                ->orderby('packing_list_details.into_wh_time','desc')
                ->get();
        }

        $array = [];
        $data=[];
        foreach($List as $rec){

            array_push($data,['json'=>json_decode($rec->qty_json),'id'=>$rec->id]);
        }

        return $data;
    }


    public function getFgScanningList($request){
        $List=[];
        if($request->entry_type == 2){

            $List = DB::table('packing_list_details')
                ->select('id','packing_list_details.qty_json')
                ->where('packing_list_details.into_wh_time','>', date('Y-m-d'))
                ->where('packing_list_details.into_wh_shift', $request->shift)
                //->where('packing_list_details.into_wh_slot', $request->slot)
                ->where('packing_list_details.team_id', $request->team_id)
                ->orderby('packing_list_details.into_wh_time','desc')
                ->get();
        }
        else if($request->entry_type == 1){
            $List = DB::table('packing_list_details')
                ->select('id','packing_list_details.qty_json')
                ->where('packing_list_details.into_wh_time','>', date("Y-m-d"))
                ->where('packing_list_details.export_shift', $request->shift)
                ->where('packing_list_details.export_slot', $request->slot)
                // ->where('packing_list_details.team_id', $request->team_id)
                ->orderby('packing_list_details.into_wh_time','desc')
                ->get();
        }

        $array = [];
        $data=[];
        foreach($List as $rec){

            array_push($data,['json'=>json_decode($rec->qty_json),'id'=>$rec->id]);
        }

        return $data;
    }

    public function deleteFgScanningListOld($request){
        try {
            DB::beginTransaction();
            if($request->entry_type == 1){
                DB::table('packing_list_details')
                    ->where('id', $request->id)
                    ->update(["export_time"=>null,"export_shift"=>null,"export_slot"=>null,"export"=>null]);
                DB::commit();
                return response()->json(["status" => "success"], 200);
            }
            else if($request->entry_type == 2){
                DB::table('packing_list_details')
                    ->where('id', $request->id)
                    ->update(["into_wh_time"=>null,"into_wh_shift"=>null,"into_wh"=>null,"into_wh_slot"=>null,"team_id"=>null]);

                ///////////////////////  Update Bundle Tickets PK Out  ////////////////////



                // $packing_list_id = DB::table('packing_list_details')
                // ->select('packing_list_id')
                // ->where('id', $request->id)
                // ->first();

                // $pack_ratio_sum = PackingListSoc::where(['packing_list_id' => $packing_list_id->packing_list_id])->sum('pack_ratio');
                // if ($pack_ratio_sum == 0 ){
                //   $pack_ratio_sum = 1;
                // }

                // $qty_json = DB::table('packing_list_details')
                // ->select('qty_json')
                // ->where('id', $request->id)
                // ->first();


                // $qty_json=json_decode($qty_json->qty_json);

                // foreach($qty_json as $key=>$value){
                //   if(intval($value) > 0){
                //     $bundle = DB::table('bundle_tickets')
                //     ->select('bundle_tickets.*','bundles.size')
                //     ->join('fpo_operations', 'fpo_operations.id','=','bundle_tickets.fpo_operation_id')
                //     ->join('bundles', 'bundles.id','=','bundle_tickets.bundle_id')
                //     ->join('routing_operations', 'routing_operations.id','=','fpo_operations.routing_operation_id')
                //     ->where('bundle_tickets.packing_list_id', $packing_list_id->packing_list_id)
                //     ->where('bundle_tickets.direction', 'OUT')
                //     //->like('routing_operations.operation_code', 'PK')
                //     ->Where('routing_operations.operation_code', 'like', '%' . 'PK' . '%')
                //     ->orderby('bundle_tickets.bundle_id','ASC')
                //     ->get();


                //     $val = $value*$pack_ratio_sum;

                //     foreach($bundle as $rec){
                //       if($rec->size == $key){
                //         $scan_qty = is_null($rec->scan_quantity) ? 0 : $rec->scan_quantity;
                //         if($val > 0 ){

                //           $new_scan = 0;
                //           if(($scan_qty-$val) >= 0){
                //             $new_scan = ($scan_qty-$val);
                //             $val = 0;
                //           }
                //           else{
                //             $new_scan = 0;
                //             $val = $val-$scan_qty;

                //           }

                //           DB::table('bundle_tickets')
                //           ->where('id', $rec->id)
                //           ->update(['scan_quantity' => $new_scan,'scan_date_time' => Carbon::now()]);

                //         // try {
                //         //     $bundleTicketSecondary = DB::table('bundle_ticket_secondaries')
                //         //         ->select('bundle_ticket_secondaries.*')
                //         //         ->where('bundle_ticket_secondaries.packing_list_id', $packing_list_id->packing_list_id)
                //         //         ->Where('bundle_ticket_secondaries.bundle_ticket_id', $rec->id)
                //         //         ->get();

                //         //     foreach ($bundleTicketSecondary as $recSec) {
                //         //         DB::table('bundle_ticket_secondaries')
                //         //             ->where('id', $recSec->id)
                //         //             ->update(['scan_quantity' => $new_scan,'scan_date_time' => Carbon::now()]);
                //         //     }
                //         // }catch (Exception $e){
                //         //     return response()->json(["status" => "other","Msg"=>"Sorry! Table updating failed! - Table name -> Bundle Ticket Secondaries"], 201);
                //         // }
                //         }
                //       }
                //     }
                //   }
                // }
                $out_ticket = DB::table('bundle_ticket_secondaries')
                    ->select('*')
                    ->where('carton_id', $request->id)
                    ->get();

                foreach($out_ticket as $rec) {
                    $ticket = DB::table('bundle_tickets')
                        ->select('*')
                        ->where('id', $rec->bundle_ticket_id)
                        ->first();
                    if(strcmp($ticket->created_by, $request->username) != 0){
//                        throw new Exception("Deleting is not allowed for the user - ".$request->username."");
                    }
                }

                foreach($out_ticket as $rec){
                    $ticket = DB::table('bundle_tickets')
                        ->select('*')
                        ->where('id', $rec->bundle_ticket_id)
                        ->first();

                    $qty = (is_null($ticket->scan_quantity) ? 0 : $ticket->scan_quantity) - intval($rec->scan_quantity);
                    DB::table('bundle_tickets')
                        ->where('id', $rec->bundle_ticket_id)
                        ->update(['scan_quantity' => $qty]);
                }

                DB::table('bundle_ticket_secondaries')->where('carton_id', $request->id)->delete();
                DB::commit();

                return response()->json(["status" => "success"], 200);
            }


        } catch (Exception $e) {
            DB::rollBack();
            throw new \App\Exceptions\GeneralException($e->getMessage());
        }

    }

    public function deleteFgScanningList($request){
        try {
            DB::beginTransaction();
            if($request->entry_type == 1){
                DB::table('packing_list_details')
                    ->where('id', $request->id)
                    ->update(["export_time"=>null,"export_shift"=>null,"export_slot"=>null,"export"=>null]);
                DB::commit();
                return response()->json(["status" => "success"], 200);
            }
            else{
                DB::table('packing_list_details')
                    ->where('id', $request->id)
                    ->update(["into_wh_time"=>null,"into_wh_shift"=>null,"into_wh"=>null,"into_wh_slot"=>null,"team_id"=>null]);

                $out_ticket = DB::table('bundle_ticket_secondaries')
                    ->select('bundle_ticket_secondaries.*')
                    ->join('bundle_tickets', 'bundle_tickets.id', '=', 'bundle_ticket_secondaries.bundle_ticket_id')
                    ->join('fpo_operations', 'fpo_operations.id', '=', 'bundle_tickets.fpo_operation_id')
                    ->where('bundle_ticket_secondaries.carton_id', $request->id)
                    ->where('fpo_operations.operation', "PK")
                    ->where('bundle_tickets.direction' , 'OUT')
                    ->get();

                $ouArr = DB::table('bundle_ticket_secondaries')
                    ->join('bundle_tickets', 'bundle_tickets.id', '=', 'bundle_ticket_secondaries.bundle_ticket_id')
                    ->join('fpo_operations', 'fpo_operations.id', '=', 'bundle_tickets.fpo_operation_id')
                    ->where('bundle_ticket_secondaries.carton_id', $request->id)
                    ->where('fpo_operations.operation', "PK")
                    ->where('bundle_tickets.direction' , 'OUT')
                    ->pluck('bundle_ticket_secondaries.id')
                    ->toArray();


                foreach($out_ticket as $rec) {
                    $ticket = DB::table('bundle_tickets')
                        ->select('*')
                        ->where('id', $rec->bundle_ticket_id)
                        ->first();
                    if(strcmp($ticket->created_by, $request->username) != 0){
//                        throw new Exception("Deleting is not allowed for the user - ".$request->username."");
                    }
                }

                foreach($out_ticket as $rec){
//                    return $rec->bundle_ticket_id;
                    $ticket = DB::table('bundle_tickets')
                        ->select('*')
                        ->where('id', $rec->bundle_ticket_id)
                        ->first();

                    $job_card = JobCard::select('job_cards.*')
                        ->join('job_card_bundles', 'job_card_bundles.job_card_id', '=', 'job_cards.id')
                        ->join('bundles', 'job_card_bundles.bundle_id', '=', 'bundles.id')
                        ->join('bundle_tickets', 'bundle_tickets.bundle_id', '=', 'bundles.id')
                        ->where('bundle_tickets.id', $ticket->id)
                        ->first();

                    $jj = JobCard::find($job_card->id);

                    if($ticket->UsedTOWFX == "Y"){
                        DB::rollBack();
                        throw new \App\Exceptions\GeneralException("Cannot delete hence PK - OUT tickets data have been uploaded to WFX");
                    }

                    $qty = (is_null($ticket->scan_quantity) ? null : $ticket->scan_quantity) - intval($rec->scan_quantity);
                    $xs = ($qty == 0) ?  null : $qty;

                    if(($job_card->status == 'Complete')){
                        $jj->update([
                            'status' => 'Ready'
                        ]);
                    }

                    DB::table('bundle_tickets')
                        ->where('id', $rec->bundle_ticket_id)
                        ->update(['scan_quantity' => $xs, 'scan_date_time' => null, 'daily_scanning_slot_id'=>null, 'daily_shift_team_id'=>null]);
                }

                //DB::table('bundle_ticket_secondaries')->where('carton_id', $request->id)->delete();
                BundleTicketSecondaryRepository::deleteRecs($ouArr);

                DB::commit();

                return response()->json(["status" => "success"], 200);
            }


        } catch (Exception $e) {
            DB::rollBack();
            throw new \App\Exceptions\GeneralException($e->getMessage());
        }

    }

    public function updateCurrentVPO($request){
        try {
            DB::beginTransaction();
            DB::table('packing_lists')
                ->where('id', $request->packing_list_id)
                ->update(["current_vpo"=>$request->current_vpo]);
            DB::commit();
            return response()->json(["status" => "success"], 200);

        } catch (Exception $e) {
            DB::rollBack();
            throw new \App\Exceptions\GeneralException($e->getMessage());
        }
    }

    public function reopenPackingList($request){
        try{
            DB::beginTransaction();
            $model = PackingList::findOrFail($request->packing_list_id);

            $bundle_tickets = DB::table('bundle_ticket_secondaries')
                ->select('*')
                ->where('packing_list_id', $request->packing_list_id)
                ->get();

            if($bundle_tickets->count() > 0){
                throw new \App\Exceptions\GeneralException("Can't Reopen, Bundle already scanned for this packing list.");
            }else{
                $model->update([
                    'status' => $request->status
                ]);
                $box = DB::table('packing_list_details')->where('packing_list_details.packing_list_id', $request->packing_list_id)->pluck('id')->toArray();

                $packing_soc_consumptions_delete = DB::table('packing_soc_consumptions')->whereIn('packing_soc_consumptions.packing_list_detail_id', $box)->delete();
                $box_delete = DB::table('packing_list_details')->where('packing_list_details.packing_list_id', $request->packing_list_id)->delete();
            }

            DB::commit();
            return response()->json(["status" => "success"], 200);
        } catch (Exception $e) {
            DB::rollBack();
            throw new \App\Exceptions\GeneralException($e->getMessage());
        }

    }

    public function revisePackingList($request){
        try{
            DB::beginTransaction();
            $model = PackingList::findOrFail($request->packing_list_id);

            $revision_no = (is_null($model->revision_no) ? 0 : $model->revision_no);
            if($model->status == "Generated"){
                $result = self::updateRec($request->packing_list_id,['revision_no' => ++$revision_no, 'status' => $request->status, 'updated_at' => $request->updated_at]);
            }else{
                throw new \App\Exceptions\GeneralException("Packing List not Generated");
            }

            //////////////////////////////  Backup Packing List Soc Table    ////////////////////////////////
            $pl_soc = PackingListSoc::select('soc_id','packing_list_id','quantity_json','quantity_json_order','pack_ratio')->where('packing_list_id', $request->packing_list_id)->get();
            foreach($pl_soc as $rec){

                $soc_revision = DB::table('packing_list_soc_revision')->insert([
                    'revision_no' => $revision_no,
                    'created_at' => now(),
                    'updated_at' => now(),
                    'soc_id' => $rec->soc_id,
                    'packing_list_id' => $request->packing_list_id,
                    'quantity_json' => json_encode($rec->quantity_json),
                    'quantity_json_order' => json_encode($rec->quantity_json_order),
                    'pack_ratio' => $rec->pack_ratio,
                ]);
            }

            //////////////////////////////// Backup Carton packing List Table  ////////////////////
            $carton_pl = CartonPackingList::select('*')->where('packing_list_id', $request->packing_list_id)->get();
            foreach($carton_pl as $rec){

                $carton_pl_revision = DB::table('carton_packing_list_revision')->insert([
                    'revision_no' => $revision_no,
                    'created_at' => now(),
                    'updated_at' => now(),
                    'carton_id' => $rec->carton_id,
                    'packing_list_id' => $request->packing_list_id,
                    'ratio_json' => json_encode($rec->ratio_json),
                    'total_quantity' => $rec->total_quantity,
                    'pcs_per_carton' => $rec->pcs_per_carton,

                    'calculated_no_of_cartons' => $rec->calculated_no_of_cartons,
                    'no_of_cartons' => $rec->no_of_cartons,
                    'customer_size_code' => $rec->customer_size_code,
                    'weight_per_piece' => $rec->weight_per_piece,
                ]);
            }

            //////////////////////////////// Backup Carton packing List Table  ////////////////////
            $pl_detail = PackingListDetail::select('*')->where('packing_list_id', $request->packing_list_id)->get();
            foreach($pl_detail as $rec){

                $pl_detail_revision = DB::table('packing_list_details_revision')->insert([
                    'revision_no' => $revision_no,
                    'created_at' => now(),
                    'updated_at' => now(),
                    'carton_number' => $rec->carton_number,
                    'packing_list_id' => $request->packing_list_id,
                    'qty_json' => json_encode($rec->qty_json),
                    'total' => $rec->total,
                    'manually_modified' => $rec->manually_modified,

                    'carton_packing_list_id' => $rec->carton_packing_list_id,
                    'carton_id' => $rec->carton_id,
                    'carton_no2' => $rec->carton_no2,
                    'into_wh' => $rec->into_wh,
                    'into_wh_time' => $rec->into_wh_time,
                    'export' => $rec->export,
                    'export_time' => $rec->export_time,
                    'team_id' => $rec->team_id,

                    'into_wh_shift' => $rec->into_wh_shift,
                    'into_wh_slot' => $rec->into_wh_slot,
                    'export_shift' => $rec->export_shift,
                    'export_slot' => $rec->export_slot,
                    'job_card_no' => $rec->job_card_no,
                    'UsedTOWFX' => $rec->UsedTOWFX,
                    'UpdateToReportDB' => $rec->UpdateToReportDB
                ]);
            }

            //////////////////////////////  Backup Packing List Soc Table    ////////////////////////////////
            $pl_soc_consumption = PackingSocConsumption ::select('packing_soc_consumptions.*')
                ->join('packing_list_details', 'packing_list_details.id','=','packing_soc_consumptions.packing_list_detail_id')
                ->where('packing_list_details.packing_list_id', $request->packing_list_id)->get();
            foreach($pl_soc_consumption as $rec){

                $pl_soc_consumption_revision = DB::table('packing_soc_consumptions_revision')->insert([
                    'revision_no' => $revision_no,
                    'created_at' => now(),
                    'updated_at' => now(),
                    'packing_list_soc_id' => $rec->packing_list_soc_id,
                    'packing_list_detail_id' => $rec->packing_list_detail_id,
                    'qty_json' => json_encode($rec->qty_json)

                ]);
            }

            DB::commit();
            return response()->json(["status" => "success", "data" => $pl_soc], 200);
        } catch (Exception $e) {
            DB::rollBack();
            throw new \App\Exceptions\GeneralException($e->getMessage());
        }
    }

    public function get_scan_box($request){
        $array = $request->carton_packing_list_id;
        $update = array();
        for($i=0; $i < count($array); $i++){

            $update_box = CartonPackingList::select('packing_list_details.id')
                ->join('packing_list_details', 'packing_list_details.carton_packing_list_id', '=', 'carton_packing_list.id')
                ->join('bundle_ticket_secondaries', 'bundle_ticket_secondaries.carton_id', '=', 'packing_list_details.id')
                ->distinct('packing_list_details.id')
                ->where('carton_packing_list.id', $array[$i])

                //->orderBy('carton_packing_list.id')
                ->get();



            if(count($update_box) > 0){
                $no_of_box = 0;
                foreach($update_box as $rec){
                    $no_of_box++;
                }
                $update = self::array_push_assoc($update,$array[$i],$no_of_box);
                $array[$i] =0;

            }

        }
        //print_r($update);
        return response()->json(["status" => "success", "data" => $array, "updBox"=> $update], 200);



    }
    private static function array_push_assoc($course, $courseCode, $courseName)
    {
        $course[$courseCode] = $courseName;
        //$counter++;
        return $course;
    }

    public function getPackingListBySoc($request){
        $data = PackingList::select('packing_lists.*','socs.wfx_soc_no')
            ->join('packing_list_soc', 'packing_list_soc.packing_list_id', '=', 'packing_lists.id')
            ->join('socs', 'socs.id', '=', 'packing_list_soc.soc_id')
            //->where('packing_lists.id', $request->id)
            ->where('packing_lists.id', 'LIKE', '%' . $request->id . '%')
            ->where('packing_lists.customer_style_ref', 'LIKE', '%' . $request->customer_style_ref . '%')
            // ->where('packing_lists.shipment_mode', 'LIKE', '%' . $request->shipment_mode . '%')
            ->where('socs.wfx_soc_no', 'LIKE', '%' . $request->soc_no_search . '%')
            ->where('packing_lists.vpo', 'LIKE', '%' . $request->vpo . '%')
            ->orderBy('packing_lists.id',"desc")
            ->get();

        return response()->json(["status" => "success", "data" => $data], 200);
    }

    public function removeBox($request){
        try{
            DB::beginTransaction();
            $update_box = CartonPackingList::select('packing_list_details.id','packing_lists.status','packing_lists.id as packing_list_id','packing_lists.carton_number_format_id as numberingID','carton_packing_list.no_of_cartons')
                ->join('packing_list_details', 'packing_list_details.carton_packing_list_id', '=', 'carton_packing_list.id')
                ->join('packing_lists', 'packing_lists.id', '=', 'carton_packing_list.packing_list_id')
                ->where('carton_packing_list.id', $request->carton_packing_list_id)
                ->orderBy('packing_list_details.id', 'desc')
                ->get();

            foreach($update_box as $rec){
                if($rec->status !== "Revision"){
                    throw new \App\Exceptions\GeneralException('This Feature Only for Revise Packing List');
                }
                $found =false;
                $box_scan_status = CartonPackingList::select('packing_list_details.id')
                    ->join('packing_list_details', 'packing_list_details.carton_packing_list_id', '=', 'carton_packing_list.id')
                    ->join('bundle_ticket_secondaries', 'bundle_ticket_secondaries.carton_id', '=', 'packing_list_details.id')
                    ->where('packing_list_details.id', $rec->id)
                    ->orderBy('carton_packing_list.id')
                    ->get();

                foreach($box_scan_status as $r){
                    $found = true;
                }

                if(!$found){
                    $packing_soc_consumptions_delete = DB::table('packing_soc_consumptions')->where('packing_soc_consumptions.packing_list_detail_id', $rec->id)->delete();
                    $box_delete = DB::table('packing_list_details')->where('packing_list_details.id', $rec->id)->delete();

                    $no_box = ($rec->no_of_cartons)-1;

                    DB::table('carton_packing_list')
                        ->where('id', $request->carton_packing_list_id)
                        ->update(['no_of_cartons' => $no_box]);

                    self::update_carton_numbering($rec->packing_list_id,$rec->numberingID);
                    DB::commit();
                    return response()->json(["status" => "success", "data" => $rec->id], 200);

                }


            }
            throw new \App\Exceptions\GeneralException('No Available Box Found for Remove');

        } catch (Exception $e) {
            DB::rollBack();
            throw new \App\Exceptions\GeneralException($e->getMessage());
        }

    }
    public function getBoxDetailsBS($boxId){
        try{
            $packingListDetails = PackingListDetail::find($boxId);
            if($packingListDetails == null){
                throw new \App\Exceptions\GeneralException("Invalid Box Id. Please check new box for the packing list.");
            }

            $packingListSoc = DB::table('packing_list_soc')
                ->select('packing_list_soc.*', 'socs.wfx_soc_no')
                ->join('socs', 'socs.id', '=', 'packing_list_soc.soc_id')
                ->where('packing_list_soc.packing_list_id', '=', $packingListDetails->packing_list_id)
                ->get();

            $pack_ratio_sum = PackingListSoc::where(['packing_list_id' => $packingListDetails->packing_list_id])->sum('pack_ratio');
            if ($pack_ratio_sum == 0 ){
                $pack_ratio_sum = 1;
            }

            $sizes = [];
            $i = 0;
            foreach($packingListDetails->qty_json as $key=>$value){
                if($value != null){
                    $x = [];
                    $x["Size"] = $key;
                    $x["Qty"] = $value * $pack_ratio_sum;
                    $sizes[$i] = $x;
                    $i++;
                }
            }

            $bundle = DB::table('bundle_ticket_secondaries')
                ->select('bundle_ticket_secondaries.*','bundles.size', 'socs.wfx_soc_no', 'fpos.wfx_fpo_no', 'teams.code')
                ->join('bundle_tickets', 'bundle_tickets.id','=','bundle_ticket_secondaries.bundle_ticket_id')
                ->join('fpo_operations', 'fpo_operations.id','=','bundle_tickets.fpo_operation_id')
                ->join('bundles', 'bundles.id','=','bundle_tickets.bundle_id')
                ->join('routing_operations', 'routing_operations.id','=','fpo_operations.routing_operation_id')
                ->join('fpos', 'fpos.id', '=', 'fpo_operations.fpo_id')
                ->join('socs' , 'socs.id', '=', 'fpos.soc_id')
                ->join('daily_shift_teams' , 'daily_shift_teams.id', '=', 'bundle_tickets.daily_shift_team_id')
                ->join('teams', 'teams.id', '=', 'daily_shift_teams.team_id')
                ->where('bundle_ticket_secondaries.carton_id', $boxId)
                ->Where('routing_operations.operation_code', 'like', '%' . 'PK' . '%')
                ->where('bundle_tickets.direction', 'IN')
                ->orderby('bundle_tickets.direction','ASC')
                ->groupBy('bundle_ticket_secondaries.id')
                ->get();

            $i =0;
            $bundle2 = [];
            foreach ($bundle as $item) {
                $pkOut = DB::table('bundle_tickets')
                    ->select('bundle_tickets.id as ticket_id', 'bundle_tickets.scan_quantity as sQty')
                    ->join('fpo_operations', 'fpo_operations.id', '=', 'bundle_tickets.fpo_operation_id')
                    ->join('bundles', 'bundles.id', '=', 'bundle_tickets.bundle_id')
                    ->join('routing_operations', 'routing_operations.id', '=', 'fpo_operations.routing_operation_id')
                    ->where('bundle_tickets.bundle_id', $item->bundle_id)
                    ->where('bundle_tickets.direction', 'OUT')
                    ->Where('routing_operations.operation_code', 'like', '%' . 'PK' . '%')
                    ->orderby('bundle_tickets.bundle_id', 'ASC')
                    ->orderby('bundle_tickets.direction', 'ASC')
                    ->get();

                $xx = $pkOut[0]->sQty == null ? 0:$pkOut[0]->sQty;
                $item->pkSc = $xx;
                $bundle2[$i] = $item;
                $i++;
            }

            $return = [];

            $l = 0;
            foreach ($sizes as $s){
                $pushObj = [];
                $pushObj['realSize'] = $s['Size'];
                $pushObj['originalQty'] = $s['Qty'];
                $pushObj['packingListId'] = $packingListDetails->packing_list_id;
                $pushObj['packingInDetails'] = $bundle2;
                foreach($packingListSoc as $pls){
                    foreach(json_decode($pls->quantity_json) as $key=>$value){
                        if($key == $s['Size'] && $value > 0){
                            if(isset($pushObj['socs'])){
                                $pushObj['socs'] = $pushObj['socs'].",\n".$pls->wfx_soc_no;
                            }
                            else{
                                $pushObj['socs'] = $pls->wfx_soc_no;
                            }
                        }
                    }
                }

                foreach($bundle as $pko){
                    if($pko->size == $s['Size'] && $pko->scan_quantity > 0 && $pko->carton_id == $boxId){
//                        print_r($pko->size);
//                        print_r(" - ");
//                        print_r($s['Size']);
//                        print_r(" - ");
//                        print_r($pko->scan_quantity);
//                        print_r(" - ");
//                        print_r($pko->carton_id);
                        if(isset($pushObj['scannedQty'])){
                            $pushObj['scannedQty'] = ((int)$pushObj['scannedQty']) + ((int)$pko->scan_quantity);
                        }
                        else{
                            $pushObj['scannedQty'] = (int)$pko->scan_quantity;
                        }
                    }
                }

                $return[$l] = $pushObj;
                $l++;
            }

            return $return;
        }
        catch (Exception $e) {
            throw new \App\Exceptions\GeneralException($e->getMessage());
        }
    }


    public function getBoxDetailsFG($boxId){
        try{
            $packingListDetails = PackingListDetail::find($boxId);
            if($packingListDetails == null){
                throw new \App\Exceptions\GeneralException("Invalid Box Id. Please check new box for the packing list.");
            }

            $pk_out_ticket = DB::table('bundle_ticket_secondaries')
                ->select('bundle_ticket_secondaries.scan_quantity', 'bundle_ticket_secondaries.carton_id','bundle_ticket_secondaries.id', 'bundle_ticket_secondaries.bundle_ticket_id' ,'bundles.size', 'socs.wfx_soc_no', 'fpos.wfx_fpo_no', 'teams.code')
                ->join('bundle_tickets', 'bundle_tickets.id','=','bundle_ticket_secondaries.bundle_ticket_id')
                ->join('fpo_operations', 'fpo_operations.id','=','bundle_tickets.fpo_operation_id')
                ->join('bundles', 'bundles.id','=','bundle_tickets.bundle_id')
                ->join('routing_operations', 'routing_operations.id','=','fpo_operations.routing_operation_id')
                ->join('fpos', 'fpos.id', '=', 'fpo_operations.fpo_id')
                ->join('socs' , 'socs.id', '=', 'fpos.soc_id')
                ->join('daily_shift_teams' , 'daily_shift_teams.id', '=', 'bundle_tickets.daily_shift_team_id')
                ->join('teams', 'teams.id', '=', 'daily_shift_teams.team_id')
                ->where('bundle_ticket_secondaries.carton_id', $boxId)
                ->Where('routing_operations.operation_code', 'like', '%' . 'PK' . '%')
                ->where('bundle_tickets.direction', 'OUT')
                ->orderby('bundle_tickets.direction','ASC')
//                ->groupBy('bundle_ticket_secondaries.carton_id')
//                ->groupBy('bundle_ticket_secondaries.bundle_ticket_id')
                ->get();


//            return $pk_out_ticket;

            $packingListSoc = DB::table('packing_list_soc')
                ->select('packing_list_soc.*', 'socs.wfx_soc_no')
                ->join('socs', 'socs.id', '=', 'packing_list_soc.soc_id')
                ->where('packing_list_soc.packing_list_id', '=', $packingListDetails->packing_list_id)
                ->get();

            $pack_ratio_sum = PackingListSoc::where(['packing_list_id' => $packingListDetails->packing_list_id])->sum('pack_ratio');
            if ($pack_ratio_sum == 0 ){
                $pack_ratio_sum = 1;
            }

            $sizes = [];
            $i = 0;
            foreach($packingListDetails->qty_json as $key=>$value){
                if($value != null){
                    $x = [];
                    $x["Size"] = $key;
                    $x["Qty"] = $value * $pack_ratio_sum;
                    $sizes[$i] = $x;
                    $i++;
                }
            }

            $bundle = DB::table('bundle_ticket_secondaries')
                ->select('bundle_ticket_secondaries.*','bundles.size', 'socs.wfx_soc_no', 'fpos.wfx_fpo_no', 'teams.code')
                ->join('bundle_tickets', 'bundle_tickets.id','=','bundle_ticket_secondaries.bundle_ticket_id')
                ->join('fpo_operations', 'fpo_operations.id','=','bundle_tickets.fpo_operation_id')
                ->join('bundles', 'bundles.id','=','bundle_tickets.bundle_id')
                ->join('routing_operations', 'routing_operations.id','=','fpo_operations.routing_operation_id')
                ->join('fpos', 'fpos.id', '=', 'fpo_operations.fpo_id')
                ->join('socs' , 'socs.id', '=', 'fpos.soc_id')
                ->join('daily_shift_teams' , 'daily_shift_teams.id', '=', 'bundle_tickets.daily_shift_team_id')
                ->join('teams', 'teams.id', '=', 'daily_shift_teams.team_id')
                ->where('bundle_ticket_secondaries.carton_id', $boxId)
                ->Where('routing_operations.operation_code', 'like', '%' . 'PK' . '%')
                ->where('bundle_tickets.direction', 'IN')
                ->orderby('bundle_tickets.direction','ASC')
                ->groupBy('bundle_ticket_secondaries.id')
                ->get();

            $i =0;
            $bundle2 = [];
            foreach ($bundle as $item) {

                $exception = DB::table('bundle_ticket_secondaries')
                    ->select('bundle_ticket_secondaries.bundle_ticket_id as ticket_id','bundle_ticket_secondaries.carton_id as carton_id', 'bundle_ticket_secondaries.scan_quantity as sQty', 'bundle_ticket_secondaries.pk_in_sec_id as pk_in_sec_id')
                    ->join('bundle_tickets','bundle_tickets.id' , '=', 'bundle_ticket_secondaries.bundle_ticket_id')
                    ->join('fpo_operations', 'fpo_operations.id', '=', 'bundle_tickets.fpo_operation_id')
                    ->join('bundles', 'bundles.id', '=', 'bundle_ticket_secondaries.bundle_id')
                    ->join('routing_operations', 'routing_operations.id', '=', 'fpo_operations.routing_operation_id')
                    ->where('bundle_ticket_secondaries.bundle_id', $item->bundle_id)
                    ->where('bundle_ticket_secondaries.carton_id', '=', $boxId)
                    ->where('bundle_tickets.direction', 'OUT')
                    ->Where('routing_operations.operation_code', 'like', '%' . 'PK' . '%')
                    ->orderby('bundle_tickets.bundle_id', 'ASC')
                    ->orderby('bundle_tickets.direction', 'ASC')
                    ->get();

                foreach ($exception as $ex){
                    if(is_null($ex->pk_in_sec_id)) {

                        throw new \App\Exceptions\GeneralException("PK IN - PK OUT mapping has been missed. Please contact a system administrator!");
                    }
                }

                $pkOut = DB::table('bundle_ticket_secondaries')
                    ->select('bundle_ticket_secondaries.bundle_ticket_id as ticket_id','bundle_ticket_secondaries.carton_id as carton_id', 'bundle_ticket_secondaries.scan_quantity as sQty')
                    ->join('bundle_tickets','bundle_tickets.id' , '=', 'bundle_ticket_secondaries.bundle_ticket_id')
                    ->join('fpo_operations', 'fpo_operations.id', '=', 'bundle_tickets.fpo_operation_id')
                    ->join('bundles', 'bundles.id', '=', 'bundle_ticket_secondaries.bundle_id')
                    ->join('routing_operations', 'routing_operations.id', '=', 'fpo_operations.routing_operation_id')
                    ->where('bundle_ticket_secondaries.bundle_id', $item->bundle_id)
                    ->where('bundle_ticket_secondaries.pk_in_sec_id', $item->id)
                    ->where('bundle_ticket_secondaries.carton_id', '=', $boxId)
                    ->where('bundle_tickets.direction', 'OUT')
                    ->Where('routing_operations.operation_code', 'like', '%' . 'PK' . '%')
                    ->orderby('bundle_tickets.bundle_id', 'ASC')
                    ->orderby('bundle_tickets.direction', 'ASC')
                    ->get();

                $pkInTwo = DB::table('bundle_ticket_secondaries')
                    ->select('bundle_ticket_secondaries.bundle_ticket_id as ticket_id','bundle_ticket_secondaries.carton_id as carton_id', 'bundle_ticket_secondaries.scan_quantity as sQty')
                    ->join('bundle_tickets','bundle_tickets.id' , '=', 'bundle_ticket_secondaries.bundle_ticket_id')
                    ->join('fpo_operations', 'fpo_operations.id', '=', 'bundle_tickets.fpo_operation_id')
                    ->join('bundles', 'bundles.id', '=', 'bundle_ticket_secondaries.bundle_id')
                    ->join('routing_operations', 'routing_operations.id', '=', 'fpo_operations.routing_operation_id')
                    ->where('bundle_ticket_secondaries.bundle_id', $item->bundle_id)
//                    ->where('bundle_ticket_secondaries.carton_id', '=', $boxId)
//                    ->where('bundle_ticket_secondaries.scan_quantity', '=', $item->scan_quantity)
                    ->where('bundle_tickets.direction', 'IN')
                    ->Where('routing_operations.operation_code', 'like', '%' . 'PK' . '%')
                    ->orderby('bundle_tickets.bundle_id', 'ASC')
                    ->orderby('bundle_tickets.direction', 'ASC')
                    ->get();

                $availableQty = $item->original_quantity;
                $availQtyFGIn = 0;

                foreach ($pkInTwo as $pit){
                    $availableQty = $availableQty - $pit->sQty;
                }

                $item->availQty = $availableQty;

                if(sizeof($pkOut) > 0) {
                    $xx = $pkOut[0]->sQty == null ? 0 : $pkOut[0]->sQty;
                    $item->pkSc = $xx;
                    $bundle2[$i] = $item;
                }
                else{
                    $item->pkSc = 0;
                    $bundle2[$i] = $item;
                }
                $i++;
            }
            $return = [];

            $l = 0;
            foreach ($sizes as $s){
                $pushObj = [];
                $pushObj['realSize'] = $s['Size'];
                $pushObj['originalQty'] = $s['Qty'];
                $pushObj['packingListId'] = $packingListDetails->packing_list_id;
                $pushObj['packingInDetails'] = $bundle2;
                foreach($packingListSoc as $pls){
                    foreach(json_decode($pls->quantity_json) as $key=>$value){
                        if($key == $s['Size'] && $value > 0){
                            if(isset($pushObj['socs'])){
                                $pushObj['socs'] = $pushObj['socs'].",\n".$pls->wfx_soc_no;
                            }
                            else{
                                $pushObj['socs'] = $pls->wfx_soc_no;
                            }
                        }
                    }
                }

                foreach($pk_out_ticket as $pko){
                    if($pko->size == $s['Size'] && $pko->scan_quantity > 0){
                        if(isset($pushObj['scannedQty'])){
                            $pushObj['scannedQty'] = (int)$pushObj['scannedQty'] + (int)$pko->scan_quantity;
                        }
                        else{
                            $pushObj['scannedQty'] = (int)$pko->scan_quantity;
                        }
                    }
                }

                $return[$l] = $pushObj;
                $l++;
            }

            return $return;
        }
        catch (Exception $e) {
            throw new \App\Exceptions\GeneralException($e->getMessage());
        }
    }

    public function getAllBoxIds($request){
        try{
            $shiftIDWanted = $request->shift;
            $currentDate = null;
            $currentDay = null;
            if(Carbon::now('GMT')->addMinutes(330)->format('H:i:s') < '08:30:00' && $shiftIDWanted == 2){
                $currentDate = Carbon::now('GMT')->subMinutes(510)->format('y-m-d');
                $currentDay = Carbon::now('GMT')->subMinutes(510)->format('l');
            }
            else if(Carbon::now('GMT')->addMinutes(330)->format('H:i:s') < '06:30:00' && $shiftIDWanted == 1){
                $currentDate = Carbon::now('GMT')->subMinutes(390)->format('y-m-d');
                $currentDay = Carbon::now('GMT')->subMinutes(390)->format('l');
            }
            else if(Carbon::now('GMT')->addMinutes(330)->format('H:i:s') > '08:30:00' && $shiftIDWanted == 2){
                $currentDate = Carbon::now('GMT')->addMinutes(330)->format('y-m-d');
                $currentDay = Carbon::now('GMT')->addMinutes(330)->format('l');
            }
            else if(Carbon::now('GMT')->addMinutes(330)->format('H:i:s') > '06:30:00' && $shiftIDWanted == 1){
                $currentDate = Carbon::now('GMT')->addMinutes(330)->format('y-m-d');
                $currentDay = Carbon::now('GMT')->addMinutes(330)->format('l');
            }
            else{
                $currentDate = Carbon::now('GMT')->addMinutes(330)->format('y-m-d');
                $currentDay = Carbon::now('GMT')->addMinutes(330)->format('l');
            }
//            $currentDay = "Wednesday";
//            $currentDate = '2022-06-07';
            try {
                $shiftDetailId = DB::table('shift_details')
                    ->select('id')
                    ->where('shift_id', $shiftIDWanted)
                    ->where('day', $currentDay)
                    ->first();
            }catch (Exception $e){
                return response()->json(["status" => "other","Msg"=>"Sorry! Data retrieving failed! - Table name -> Shift details"], 201);
            }

            try{
                $dailyShiftId = DB::table('daily_shifts')
                    ->select('id')
                    ->where(['shift_detail_id' => $shiftDetailId->id])
                    ->where('current_date', $currentDate)
                    ->first();
            }catch (Exception $e){
                return response()->json(["status" => "other","Msg"=>"Sorry! Data retrieving failed! - Table name -> Daily shifts"], 201);
            }

            try{
                $dailyScanningSlotId = DB::table('daily_scanning_slots')
                    ->select('id')
                    ->where('seq_no', $request->slot)
                    ->where(['daily_shift_id' => $dailyShiftId->id])
                    ->first();
            }catch (Exception $e){
                return response()->json(["status" => "other","Msg"=>"Sorry! Data retrieving failed! - Table name -> daily_scanning_slots"], 201);
            }
//            print_r($dailyScanningSlotId);
//            $dailyShiftTeamIdX = $dailyShiftTeamId->id;
            $dailyScanSlotIdX = $dailyScanningSlotId->id;

            $pkOutTickets = DB::table('bundle_ticket_secondaries')
                ->select('bundle_ticket_secondaries.carton_id', 'bundle_ticket_secondaries.scan_date_time')
                ->join('bundle_tickets', 'bundle_tickets.id', '=', 'bundle_ticket_secondaries.bundle_ticket_id')
                ->join('fpo_operations', 'fpo_operations.id', '=', 'bundle_tickets.fpo_operation_id')
                ->where('fpo_operations.operation', 'PK')
                ->where('bundle_tickets.direction', 'OUT')
                ->where('bundle_ticket_secondaries.daily_scanning_slot_id' , $dailyScanSlotIdX)
                ->distinct('bundle_ticket_secondaries.carton_id')
//                ->groupBy('bundle_ticket_secondaries.bundle_id')
                ->orderby('bundle_ticket_secondaries.scan_date_time','DESC')
                ->get();

            $array = [];
            $data=[];
            foreach($pkOutTickets as $rec){
                if(!in_array(['id' => $rec->carton_id], $data)) {
//                    print_r($rec->carton_id);
                    array_push($data, ['id' => $rec->carton_id]);
                }
            }

            $finalReturn = [];
            $ij = 0;
            foreach ($data as $de) {
                $boxId = $de['id'];
                $finalReturn[$ij]['box_id'] = $boxId;
                $packingListDetails = PackingListDetail::find($boxId);
                if ($packingListDetails == null) {
                    throw new \App\Exceptions\GeneralException("Invalid Box Id. Please check new box for the packing list.");
                }

                $pk_out_ticket = DB::table('bundle_ticket_secondaries')
                    ->select('bundle_ticket_secondaries.*','bundles.size', 'socs.id as soc_id', 'fpos.wfx_fpo_no', 'teams.code')
                    ->join('bundle_tickets', 'bundle_tickets.id','=','bundle_ticket_secondaries.bundle_ticket_id')
                    ->join('fpo_operations', 'fpo_operations.id','=','bundle_tickets.fpo_operation_id')
                    ->join('bundles', 'bundles.id','=','bundle_tickets.bundle_id')
                    ->join('routing_operations', 'routing_operations.id','=','fpo_operations.routing_operation_id')
                    ->join('fpos', 'fpos.id', '=', 'fpo_operations.fpo_id')
                    ->join('socs' , 'socs.id', '=', 'fpos.soc_id')
                    ->join('daily_shift_teams' , 'daily_shift_teams.id', '=', 'bundle_tickets.daily_shift_team_id')
                    ->join('teams', 'teams.id', '=', 'daily_shift_teams.team_id')
                    ->where('bundle_ticket_secondaries.carton_id', $boxId)
                    ->Where('routing_operations.operation_code', 'like', '%' . 'PK' . '%')
                    ->where('bundle_tickets.direction', 'OUT')
                    ->orderby('bundle_tickets.direction','ASC')
                    ->orderby('bundle_ticket_secondaries.scan_date_time','DESC')
                    ->groupBy('bundle_ticket_secondaries.id')
                    ->get();


//            return $pk_out_ticket;

                $packingListSoc = DB::table('packing_list_soc')
                    ->select('packing_list_soc.*', 'socs.wfx_soc_no')
                    ->join('socs', 'socs.id', '=', 'packing_list_soc.soc_id')
                    ->where('packing_list_soc.packing_list_id', '=', $packingListDetails->packing_list_id)
                    ->get();

                $pack_ratio_sum = PackingListSoc::where(['packing_list_id' => $packingListDetails->packing_list_id])->sum('pack_ratio');
                if ($pack_ratio_sum == 0) {
                    $pack_ratio_sum = 1;
                }

                $sizes = [];
                $i = 0;
                foreach ($packingListDetails->qty_json as $key => $value) {
                    if ($value != null) {
                        $x = [];
                        $x["Size"] = $key;
                        $x["Qty"] = $value * $pack_ratio_sum;
                        $sizes[$i] = $x;
                        $i++;
                    }
                }

                $return = [];

                $l = 0;
                foreach ($sizes as $s) {
                    $pushObj = [];
                    $teams = [];
                    $pushObj['realSize'] = $s['Size'];
                    $pushObj['originalQty'] = $s['Qty'];
                    $pushObj['packingListId'] = $packingListDetails->packing_list_id;
                    foreach ($packingListSoc as $pls) {
                        foreach (json_decode($pls->quantity_json) as $key => $value) {
                            if ($key == $s['Size'] && $value > 0) {
                                if (isset($pushObj['socs'])) {
                                    $pushObj['socs'] = $pushObj['socs'] . ",\n" . $pls->wfx_soc_no;
                                } else {
                                    $pushObj['socs'] = $pls->wfx_soc_no;
                                }
                            }
                        }
                    }

                    foreach ($pk_out_ticket as $pko) {
                        if ($pko->size == $s['Size'] && $pko->scan_quantity > 0) {
                            if (isset($pushObj['scannedQty'])) {
                                $pushObj['scannedQty'] = (int)$pushObj['scannedQty'] + (int)$pko->scan_quantity;
                            } else {
                                $pushObj['scannedQty'] = (int)$pko->scan_quantity;
                            }
                            if(!in_array($pko->code, $teams)){
                                array_push($teams, $pko->code);
                            }
                        }
                    }

                    $pushObj["teams"] = $teams;
                    $return[$l] = $pushObj;
                    $l++;
//                    if(!in_array($pushObj , $return)) {
//                        $return[$l] = $pushObj;
//                        $l++;
//                    }
//                    else{
//                        print_r("dfsf");
//                    }
                }
                $finalReturn[$ij]['data'] = $return;
                $ij++;
            }

            return $finalReturn;
        }
        catch (Exception $e) {
            throw new \App\Exceptions\GeneralException($e->getMessage());
        }
    }


    public function fgScanFinalSave($request) {
        try {
            DB::beginTransaction();
            $export = null;
            $intoWh = null;
            $exportTime = null;
            $export_shift = null;
            $export_slot = null;

            $date = $request->date;
            $slotId = $request->slotId;
            $boxId = $request->boxId;
            $entryType = $request->entryType;
            $pkInDetails = $request->pkInDetails;
            $userId = $request->userId;
            $shift = $request->shift;

            $currentDate = null;
            $currentDay = null;
            if (Carbon::now('GMT')->addMinutes(330)->format('H:i:s') < '08:30:00' && $shift == 2) {
                $currentDate = Carbon::now('GMT')->subMinutes(510)->format('y-m-d');
                $currentDay = Carbon::now('GMT')->subMinutes(510)->format('l');
            } else if (Carbon::now('GMT')->addMinutes(330)->format('H:i:s') < '06:30:00' && $shift == 1) {
                $currentDate = Carbon::now('GMT')->subMinutes(390)->format('y-m-d');
                $currentDay = Carbon::now('GMT')->subMinutes(390)->format('l');
            } else if (Carbon::now('GMT')->addMinutes(330)->format('H:i:s') > '08:30:00' && $shift == 2) {
                $currentDate = Carbon::now('GMT')->addMinutes(330)->format('y-m-d');
                $currentDay = Carbon::now('GMT')->addMinutes(330)->format('l');
            } else if (Carbon::now('GMT')->addMinutes(330)->format('H:i:s') > '06:30:00' && $shift == 1) {
                $currentDate = Carbon::now('GMT')->addMinutes(330)->format('y-m-d');
                $currentDay = Carbon::now('GMT')->addMinutes(330)->format('l');
            } else {
                $currentDate = Carbon::now('GMT')->addMinutes(330)->format('y-m-d');
                $currentDay = Carbon::now('GMT')->addMinutes(330)->format('l');
            }

            try {
                $shiftDetailId = DB::table('shift_details')
                    ->select('id')
                    ->where('shift_id', $shift)
                    ->where('day', $currentDay)
                    ->first();
            } catch (Exception $e) {
                throw new \App\Exceptions\GeneralException($e->getMessage());
            }

            try {
                $dailyShiftId = DB::table('daily_shifts')
                    ->select('id')
                    ->where(['shift_detail_id' => $shiftDetailId->id])
                    ->where('current_date', $currentDate)
                    ->first();
            } catch (Exception $e) {
                throw new \App\Exceptions\GeneralException($e->getMessage());
            }



            try {
                $dailyScanningSlotId = DB::table('daily_scanning_slots')
                    ->select('id')
                    ->where('seq_no', $slotId)
                    ->where(['daily_shift_id' => $dailyShiftId->id])
                    ->first();
            } catch (Exception $e) {
                throw new \App\Exceptions\GeneralException($e->getMessage());
            }

            $dailyScanSlotIdX = $dailyScanningSlotId->id;

            $packing_list_details = PackingListDetail::select('*')
                ->where('id', $boxId)
                ->first();

            if($packing_list_details->into_wh == 'INTO WH'){
                throw new \App\Exceptions\GeneralException("Box already scanned!");
            }

            $pack_ratio_sum = PackingListSoc::where(['packing_list_id' => $packing_list_details->packing_list_id])->sum('pack_ratio');
            if ($pack_ratio_sum == 0) {
                $pack_ratio_sum = 1;
            }

            $actual_box_qty = 0;
            foreach ($packing_list_details->qty_json as $key => $value) {
                if (intval($value) > 0) {
                    $actual_box_qty += intval($value) * $pack_ratio_sum;
                }
            }
//            return $actual_box_qty;
            if( $entryType == "1"){
                $export = "EXPORT";
                $exportTime = date("Y-m-d h:i:s");
                $export_shift =$shift;
                $export_slot =$slotId;
            }
            else if($entryType == "2"){
                $intoWh = "INTO WH";
                $intoWhTime = date("Y-m-d h:i:s");

                $intoWh_shift =$shift;
                $intoWh_slot =$slotId;

                $pkOutTicketsOfPkIn = [];
                $i = 0;
                foreach ($pkInDetails as $pk) {
                    $pkInTicket = DB::table('bundle_tickets')
                        ->where('bundle_tickets.id', '=', $pk["pkInTicket"])
                        ->get();
                    if(1 ==1) { //(int)$pk['qty'] > 0
                        $pkOut = DB::table('bundle_tickets')
                            ->select('bundle_tickets.id as ticket_id', 'bundle_tickets.scan_quantity as sQty')
                            ->join('fpo_operations', 'fpo_operations.id', '=', 'bundle_tickets.fpo_operation_id')
                            ->join('bundles', 'bundles.id', '=', 'bundle_tickets.bundle_id')
                            ->join('routing_operations', 'routing_operations.id', '=', 'fpo_operations.routing_operation_id')
                            ->where('bundle_tickets.bundle_id', $pkInTicket[0]->bundle_id)
                            ->where('bundle_tickets.direction', 'OUT')
                            ->Where('routing_operations.operation_code', 'like', '%' . 'PK' . '%')
                            ->orderby('bundle_tickets.bundle_id', 'ASC')
                            ->orderby('bundle_tickets.direction', 'ASC')
                            ->get();

                        $teamOfPkIn = DB::table('daily_shift_teams')
                            ->select('team_id')
                            ->where('id', '=', $pkInTicket[0]->daily_shift_team_id)
                            ->get();

                        $pkInTeamDescription = DB::table('teams')
                            ->select('code')
                            ->where('id', $teamOfPkIn[0]->team_id)
                            ->get();

                        $dailyShiftTeamId = DB::table('daily_shift_teams')
                            ->select('id')
                            ->where('team_id', $teamOfPkIn[0]->team_id)
                            ->where(['daily_shift_id' => $dailyShiftId->id])
                            ->where('current_date', $currentDate)
                            ->first();

                        if ($dailyShiftTeamId == []) {
                            $teamValue = DB::table('teams')
                                ->select('common_shift_value')
                                ->where('id', '=', $teamOfPkIn[0]->team_id)
                                ->get();

                            //if (is_null($teamValue[0]->common_shift_value)) {
                                //DB::rollBack();
                                //throw new \App\Exceptions\GeneralException("Please ask a SYSTEM ADMINISTRATOR to fill the common shift value for the team " . $pkInTeamDescription[0]->code . " and for the other related team.");
                            //}

                            $relatedTeams = DB::table('teams')
                                ->select('*')
                                ->where('common_shift_value', $teamValue[0]->common_shift_value)
                                ->get();

                            if (sizeof($relatedTeams) <= 1) {
                                DB::rollBack();
                                throw new \App\Exceptions\GeneralException("PK - OUT ticket - " . $pkOut[0]->ticket_id . " cannot be scanned hence the daily team mapping has not been done on today for the team " . $pkInTeamDescription[0]->code . ". Please map it and scan again.");
                            }

                            $t = "Team Not Found";
                            $dailyShiftTeamId2 = [];
                            foreach ($relatedTeams as $rt) {
                                if ($rt->id != $teamOfPkIn[0]->team_id) {
                                    $dailyShiftTeamId2 = DB::table('daily_shift_teams')
                                        ->select('*')
                                        ->where('team_id', $rt->id)
                                        ->where(['daily_shift_id' => $dailyShiftId->id])
                                        ->where('current_date', $currentDate)
                                        ->get();

                                    $t = $rt->code;
                                }
                                if (sizeof($dailyShiftTeamId2) > 0) {
                                    break;
                                }
                            }

                            if (is_null($dailyShiftTeamId2) || sizeof($dailyShiftTeamId2) == 0) {
                                DB::rollBack();
                                throw new \App\Exceptions\GeneralException("PK - OUT ticket - " . $pkOut[0]->ticket_id . " cannot be scanned hence the daily team mapping has not been done on today for the teams " . $pkInTeamDescription[0]->code . " or " . $t . ". Please map it for the applicable team and scan again.");
                            } else {
                                $dailyShiftTeamIdX = $dailyShiftTeamId2[0]->id;
                            }
                        } else {
                            $dailyShiftTeamIdX = $dailyShiftTeamId->id;
                        }

                        $xx = 0;
                        if (sizeof($pkOut) > 0) {
                            $xx = $pkOut[0]->sQty == null ? 0 : $pkOut[0]->sQty;
                        }
                        if ($xx < $pkInTicket[0]->scan_quantity) {
                            $pkOutTicketsOfPkIn[$i]['pk_in_qty'] = $pkInTicket[0]->scan_quantity;
                            $pkOutTicketsOfPkIn[$i]['bundle_ticket_id'] = $pkOut[0]->ticket_id;
                            $pkOutTicketsOfPkIn[$i]['scan_quantity'] = (int)$pk['qty'];
                            $pkOutTicketsOfPkIn[$i]['daily_shift_team_id'] = $dailyShiftTeamIdX;
                            $pkOutTicketsOfPkIn[$i]['pk_in_sec_id'] = $pk['secId'];
                        }
                        $i++;
                    }
                }

//                return $pkOutTicketsOfPkIn;

                $totalScannedQuantity = 0;

                foreach ($pkOutTicketsOfPkIn as $item) {
                    $bundle_ticket_pk_out = BundleTicket::find($item['bundle_ticket_id']);
                    $previousScannedQty = ($bundle_ticket_pk_out->scan_quantity == null ? 0 : $bundle_ticket_pk_out->scan_quantity);
                    $bundleSec = DB::table('bundle_ticket_secondaries')
                        ->select('*')
                        ->where('bundle_ticket_secondaries.bundle_ticket_id', '=', $item['bundle_ticket_id'])
                        ->where('bundle_ticket_secondaries.pk_in_sec_id', '=', $item['pk_in_sec_id'])
//                        ->where('bundle_ticket_secondaries.carton_id', '=' , $boxId)
                        ->get();

                    $previousScannedQtyForBox = 0;

                    foreach ($bundleSec as $b){
                        if($b->carton_id == $boxId){
                            $previousScannedQtyForBox = $previousScannedQtyForBox + $b->scan_quantity;
                        }
                    }
//                    print_r( $previousScannedQtyForBox);
                    $totalScannedQuantity += $item['scan_quantity'] + $previousScannedQtyForBox;
                }

//                return $totalScannedQuantity;
                if ($totalScannedQuantity > $actual_box_qty) {
                    DB::rollBack();
                    throw new \App\Exceptions\GeneralException("Insufficient allocated bundle quantity.");
                }
                else{
                    foreach ($pkOutTicketsOfPkIn as $item) {
                        if ($item['scan_quantity'] > 0) {
                            $bundle_ticket_pk_out = BundleTicket::find($item['bundle_ticket_id']);
                            $previousScannedQty = ($bundle_ticket_pk_out->scan_quantity == null ? 0 : $bundle_ticket_pk_out->scan_quantity);
//                        if ($previousScannedQty < $item['pk_in_qty']) {
                            $bundle_ticket_pk_out->update([
                                'scan_quantity' => $item['scan_quantity'] + $previousScannedQty,
                                'packing_list_id' => $packing_list_details->packing_list_id,
                                'scan_date_time' => now('Asia/Kolkata'),
                                'daily_shift_team_id' => $item['daily_shift_team_id'],
                                'daily_scanning_slot_id' => $dailyScanSlotIdX,
                                'updated_by' => $userId,
                                'carton_id' => $boxId
                            ]);

                            $bt_secondary = BundleTicketSecondary::insert([
                                'bundle_ticket_id' => $bundle_ticket_pk_out->id,
                                'daily_scanning_slot_id' => $dailyScanSlotIdX,
                                'daily_shift_team_id' => $item['daily_shift_team_id'],
                                'packing_list_id' => $packing_list_details->packing_list_id,
                                'bundle_id' => $bundle_ticket_pk_out->bundle_id,
                                'original_quantity' => $bundle_ticket_pk_out->original_quantity,
                                "scan_quantity" => $item['scan_quantity'],
                                'carton_id' => $boxId,
                                "scan_date_time" => now('Asia/Kolkata'),
                                'created_by' => $userId,
                                'updated_by' => $userId,
                                'created_at' => now('Asia/Kolkata'),
                                'updated_at' => now('Asia/Kolkata'),
                                'pk_in_sec_id' => $item['pk_in_sec_id']
                            ]);
//                        }
                        }
                    }
                }

                if($request->type == "NotChecked"){
                    if ($totalScannedQuantity < $actual_box_qty) {
                        DB::rollBack();
                        throw new \App\Exceptions\GeneralException("Full box quantity (".$actual_box_qty. ") should be scanned. Please scan the balance quantity - " .($actual_box_qty - $totalScannedQuantity).".");
                    }
                }

                if ($totalScannedQuantity == $actual_box_qty) {
                    DB::table('packing_list_details')
                        ->where('id', $boxId)
                        ->update(['into_wh' => $intoWh, 'into_wh_time' => now('Asia/Kolkata'), 'export' => $export, 'export_time' => $exportTime, 'into_wh_shift' => $intoWh_shift, 'into_wh_slot' => $intoWh_slot, 'export_shift' => $export_shift, 'export_slot' => $export_slot, 'updated_by' => $userId]);

                }
            }
            else{
                throw new \App\Exceptions\GeneralException("Please select a valid entry type.");
            }

            DB::commit();
            return response()->json(["status" => "success"], 200);
        }
        catch (Exception $en){
            DB::rollBack();
            throw new \App\Exceptions\GeneralException($en->getMessage());
        }

    }
    public function getDailyTransfer($request){
        $location = $request->location;
        if($location != "NaN"){

            $daily_transfer = PackingListDetail::select('packing_list_details.id','packing_list_details.updated_at','packing_list_details.qty_json','locations.location_name')
            ->join('locations', 'locations.id', '=', 'packing_list_details.location_id')
            ->join('daily_shifts', 'daily_shifts.id', '=', 'packing_list_details.daily_shift_id')
            ->where('daily_shifts.current_date',date('Y-m-d'))
            ->where('locations.id',$location)
            ->orderby('packing_list_details.updated_at','DESC')
            ->limit(10)
            ->get();

            $data=[];
            $index =0;
            foreach($daily_transfer as $rec){
                
                $qty = [];
              //  print_r($rec->qty_json);
                $qty_json = $rec->qty_json;
                
                foreach($qty_json as $k =>$v){
                    
                    if($v > 0){
                        $qty[$k] = $v;
                    }
                }
                $daily_transfer[$index]['size'] = array_keys($qty);
                $daily_transfer[$index]['value'] = array_values($qty);
                $index++;
            }

            $data =$daily_transfer; 
            return response()->json(['status' => 'success', 'data' => $data], 200);
        }else{
            $daily_transfer = PackingListDetail::select('packing_list_details.id','packing_list_details.updated_at','packing_list_details.qty_json','locations.location_name')
            ->join('locations', 'locations.id', '=', 'packing_list_details.location_id')
            ->join('daily_shifts', 'daily_shifts.id', '=', 'packing_list_details.daily_shift_id')
            ->where('daily_shifts.current_date',date('Y-m-d'))
            ->orderby('packing_list_details.updated_at','DESC')
            ->limit(10)
            ->get();

            $data=[];
            $index =0;
            foreach($daily_transfer as $rec){
                
                $qty = [];
              //  print_r($rec->qty_json);
                $qty_json = $rec->qty_json;
                
                foreach($qty_json as $k =>$v){
                    
                    if($v > 0){
                        $qty[$k] = $v;
                    }
                }
                $daily_transfer[$index]['size'] = array_keys($qty);
                $daily_transfer[$index]['value'] = array_values($qty);
                $index++;
            }

            $data =$daily_transfer; 
            return response()->json(['status' => 'success', 'data' => $data], 200);
        }
    
        
    }

    public function updateCartonLocation($request){
        try {
            DB::beginTransaction();

            date_default_timezone_set("Asia/Calcutta"); 
            $dateTime = date("Y-m-d H:i:s");
          
            $shift = DailyShift::select('daily_shifts.id')
            ->join('shift_details', 'shift_details.id', '=', 'daily_shifts.shift_detail_id')
            ->join('shifts', 'shift_details.shift_id', '=', 'shifts.id')
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

            $model = PackingListDetail::where('id',$request->id)->update(['location_id'=>$request->location,'daily_shift_id'=>$shift->id,'location_change_by'=>auth()->user()->id]);

            DB::commit();
            return response()->json(["status" => "success"], 200);
        }
        catch (Exception $en){
            DB::rollBack();
            throw new \App\Exceptions\GeneralException($en->getMessage());
        }
    }

}
