<?php

namespace App\Http\Repositories;

use App\Buyer;
use App\Carton;
use App\CartonNumberFormat;
use App\CartonPackingList;
use Illuminate\Http\Request;
use App\PackingListDetail;
use App\Http\Resources\PackingListDetailResource;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use App\Http\Resources\PackingListDetailWithParentsResource;
use App\Http\Resources\PackingSocConsumptionResource;
use Illuminate\Validation\Rule;
use Exception;

use App\Http\Validators\PackingListDetailCreateValidator;
use App\Http\Validators\PackingListDetailUpdateValidator;
use App\PackingList;
use App\PackingListSoc;
use App\PackingSocConsumption;
use App\Soc;
use Illuminate\Support\Facades\Log;
use PDF;

class PackingListDetailRepository
{
  public function show(PackingListDetail $packinglistdetail)
  {
    return response()->json(
      [
        'status' => 'success',
        'data' => new PackingListDetailWithParentsResource($packinglistdetail),
      ],
      200
    );
  }

  public static function createRec(array $rec)
  {
    $validator = Validator::make(
      $rec,
      PackingListDetailCreateValidator::getCreateRules()
    );
    if ($validator->fails()) {
      throw new Exception($validator->errors());
    }
    try {
      $model = PackingListDetail::create($rec);
    } catch (Exception $e) {
      throw new \App\Exceptions\GeneralException($e->getMessage());
    }
    return $model;
  }

  public static function updateRec($model_id, array $rec)
  {

    $model = PackingListDetail::findOrFail($model_id);

    if (!$model->updated_at->eq(\Carbon\Carbon::parse($rec['updated_at']))) {
      $entity = (new \ReflectionClass($model))->getShortName();
      throw new \App\Exceptions\ConcurrencyCheckFailedException($entity);
    }
    Utilities::hydrate($model, $rec);
    $validator = Validator::make(
      $rec,
      PackingListDetailUpdateValidator::getUpdateRules($model_id)
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
    PackingListDetail::destroy($recs);
  }

  public static function getFullDetails($packing_list_id)
  {

    $cbm = 0;
    $volume_weight = 0;

    $packing_list = PackingList::find($packing_list_id);
    $packing_list->buyer_code = Buyer::find($packing_list->buyer_id)->buyer_code;
    $packing_list->buyer_name = Buyer::find($packing_list->buyer_id)->name;

    $carton_no_format = CartonNumberFormat::find($packing_list->carton_number_format_id);
    $packing_list->carton_number_format = $carton_no_format->description;

    //get soc
    $tartget_soc = Soc::where('customer_style_ref', $packing_list->customer_style_ref)->where('buyer_id', $packing_list->buyer_id)->first();
    $packing_list->style = $tartget_soc->style;
    $packing_list_soc = PackingListSoc::where('packing_list_id', $packing_list->id)->get();
    foreach ($packing_list_soc as $value) {
      $soc = Soc::find($value['soc_id']);
      $value['soc_no'] = $soc->wfx_soc_no;
      $value['garment_color'] = $soc->garment_color;
    }

    //get packing details    
    // $carton_pack_ids = PackingListDetail::select('carton_packing_list_id')->where('packing_list_id', $packing_list->id)->distinct()->get();
    

    $packing_list_detail_processed = [];
    $boxes_total = 0;
    $box_number_start = 1;
    $box_number_end = null;
    $qty_json = null;

    // Cartons generated from Carton Parameters (Carton Packing List Detils)
    $carton_pack_list_ids = PackingListDetail::select('carton_packing_list_id')->where('packing_list_id', $packing_list->id)->whereNotNull('carton_packing_list_id')->distinct()->get();
    Log::info($carton_pack_list_ids);
    foreach ($carton_pack_list_ids as $ctn_pack_list_id) {
      $packing_list_detail = PackingListDetail::where('packing_list_id', $packing_list->id)->where('carton_packing_list_id', $ctn_pack_list_id->carton_packing_list_id)->get();
      
      foreach ($packing_list_detail as $detail) {
        $boxes_total = $boxes_total + $detail->total;
        $box_number_end = $detail->carton_number;
        $qty_json =  $detail->qty_json;
      }
      
      $carton_packing_list = CartonPackingList::find($ctn_pack_list_id->carton_packing_list_id);
      $pack_ratio_sum = PackingListSoc::where(['packing_list_id' => $packing_list_id])->sum('pack_ratio');

      //----- Calc CBM ------
      $carton = Carton::find($carton_packing_list->carton->id);
      $current_cbm = 0;
      if (!(is_null($carton))){
        $current_cbm = $carton->height * $carton->width * $carton->length;
      }
      $no_of_boxes = $box_number_end - $box_number_start + 1;
      $cbm = $cbm + ($current_cbm * $no_of_boxes);
      $volume_weight = $volume_weight + (($carton_packing_list->weight_per_piece) * ($boxes_total * $pack_ratio_sum));
      //---------------------

      $packing_list_detail_processed[] = [
        'start' => $box_number_start,
        'end' => $box_number_end,
        'carton_type' => $carton_packing_list->carton->carton_type,
        'qty_json' =>  $qty_json,
        'total' => ($boxes_total * $pack_ratio_sum),
        'packing_list_id' => $packing_list_id,
        'carton_packing_list_id' => $ctn_pack_list_id->carton_packing_list_id
      ];

      $boxes_total = 0;
      $box_number_start = $box_number_end + 1;
      $box_number_end = null;
      $qty_json = null;
    }

    //Cartons generated from Manual Entry

    $packing_list_manual_detail = PackingListDetail::where('packing_list_id', $packing_list->id)->where('manually_modified', '1')->get();
    foreach($packing_list_manual_detail as $manual_rec){
      // Manual Addition will always be one carton per saving
      
      $box_number_end = $box_number_start;
      $packing_list_detail_processed[] = [
        'start' => $box_number_start,
        'end' => $box_number_end,
        'carton_type' => $manual_rec->carton->carton_type,
        'qty_json' =>  $manual_rec->qty_json,
        'total' => $manual_rec->total,
        'packing_list_id' => $packing_list_id,
        'carton_packing_list_id' => null
      ];
      $box_number_start = $box_number_start + 1;
    }

    $packing_list->cbm = $cbm/1000;
    $packing_list->volume_weight = $volume_weight / 1000;
    return ["PackingList" => $packing_list, "PackingListSoc" => $packing_list_soc,  "PackingListDetails" => $packing_list_detail_processed];
  }

  public static function deleteDetailsByPackingList($packing_list_id)
  {
    $packing_list_details = PackingListDetail::where('packing_list_id', $packing_list_id)->pluck('id')->toArray();
    self::deleteRecs($packing_list_details);
  }

  public static function getPackingListSocByCarton($carton_no, $packing_list_id, $carton_packing_list_id)
  {

    if(!(is_null($carton_packing_list_id))) {
    $packing_list_detail_row = PackingListDetail::where('packing_list_id', $packing_list_id)
    ->where('carton_packing_list_id', $carton_packing_list_id)
    ->where('carton_number', $carton_no)
    ->first();
    }   
    else{
      $packing_list_detail_row = PackingListDetail::where('packing_list_id', $packing_list_id)
      ->whereNull('carton_packing_list_id')
      ->where('carton_number', $carton_no)
      ->first();   
    }

    $packing_list_soc = PackingSocConsumption::select(
      'packing_soc_consumptions.id as packing_soc_consumptions_id',
      'packing_soc_consumptions.qty_json',
      'socs.id as soc_id',
      'socs.wfx_soc_no',
      'socs.garment_color'
    )
    ->join('packing_list_soc', 'packing_list_soc.id', '=', 'packing_soc_consumptions.packing_list_soc_id')
    ->join('socs', 'socs.id', 'packing_list_soc.soc_id')
    ->where( 'packing_soc_consumptions.packing_list_detail_id', $packing_list_detail_row->id)
    ->get();

    return ["PackingListSoc" => $packing_list_soc];

  }
  public static function editPackingCartonQty(Request $request)
  {
    $soc_consumption_data = $request->soc_data;
    try {
      DB::beginTransaction();
      foreach ($soc_consumption_data as $soc_rec) {
        Log::info($soc_rec["qty_json"]);
        $packing_soc_consumption = PackingSocConsumption::find($soc_rec["packing_soc_consumptions_id"]);
        if (!(is_null($packing_soc_consumption))) {
          $rec = [];
          $rec['id'] = $packing_soc_consumption->id;
          $rec['qty_json'] = $soc_rec["qty_json"];
          $rec['updated_at'] = $packing_soc_consumption->updated_at;
          PackingSocConsumptionRepository::updateRec($packing_soc_consumption->id, $rec);
        } else {
          throw new Exception("Invalid Soc Consumption Record");
        }
      }
      DB::commit();
      return response()->json(['status' => 'success'], 200);
    } catch (Exception $e) {
      DB::rollBack();
      return response()->json(
        ['status' => 'error','message' => $e->getMessage()],400);
    }
  }

  public static function deleteCarton($carton_no, $packing_list_id, $carton_packing_list_id)
  {

    // if(!(is_null($carton_packing_list_id))) {
    // $packing_list_detail_row = PackingListDetail::where('packing_list_id', $packing_list_id)
    // ->where('carton_packing_list_id', $carton_packing_list_id)
    // ->where('carton_number', $carton_no)
    // ->first();
    // }   
    // else{
    //   $packing_list_detail_row = PackingListDetail::where('packing_list_id', $packing_list_id)
    //   ->whereNull('carton_packing_list_id')
    //   ->where('carton_number', $carton_no)
    //   ->first();   
    // }

    // $packing_list_soc = PackingSocConsumption::select(
    //   'packing_soc_consumptions.id as packing_soc_consumptions_id',
    //   'packing_soc_consumptions.qty_json',
    //   'socs.id as soc_id',
    //   'socs.wfx_soc_no',
    //   'socs.garment_color'
    // )
    // ->join('packing_list_soc', 'packing_list_soc.id', '=', 'packing_soc_consumptions.packing_list_soc_id')
    // ->join('socs', 'socs.id', 'packing_list_soc.soc_id')
    // ->where( 'packing_soc_consumptions.packing_list_detail_id', $packing_list_detail_row->id)
    // ->get();

    // return ["PackingListSoc" => $packing_list_soc];

  }

  public function getPackingListStickers($packing_list_id){
    $packing_style = DB::table('packing_lists')
    //->select('styles.style_code','packing_lists.vpo')
	->select('styles.style_code','packing_lists.vpo', 'packing_lists.destination', 'packing_lists.id as pkId')
    ->join('styles', 'styles.id', '=', 'packing_lists.style_id')
    ->where('packing_lists.id', $packing_list_id)
    ->first();

    $soc = DB::table('packing_list_soc')
   // ->select('socs.wfx_soc_no','socs.pack_color','socs.pack_color')
   ->select('socs.wfx_soc_no','socs.pack_color','socs.pack_color', 'socs.ColorName')
    ->join('socs', 'socs.id', '=', 'packing_list_soc.soc_id')
    ->where('packing_list_soc.packing_list_id', $packing_list_id)
    ->get();

    $pack_ratio_sum = PackingListSoc::where(['packing_list_id' =>$packing_list_id])->sum('pack_ratio');

    $cartons = DB::table('carton_packing_list')
    //->select('packing_list_details.id','packing_list_details.carton_no2','packing_list_details.qty_json')
	->select('packing_list_details.id','packing_list_details.carton_no2','packing_list_details.qty_json','carton_packing_list.customer_size_code' )
    ->join('packing_list_details', 'packing_list_details.carton_packing_list_id', '=', 'carton_packing_list.id')
    ->where('carton_packing_list.packing_list_id', $packing_list_id)
    ->get();

    $data = ['packing_style' => $packing_style, 'soc' => $soc, 'pack_ratio_sum' => $pack_ratio_sum, 'cartons' => $cartons];
    
    $customPaper = array(1,1,144.00,288.00);
    $pdf = PDF::loadView('print.plsticker',$data);
    $pdf->setPaper("A4", 'portrait');
    return $pdf->stream('packing_list_sticker_report_' . date('Y_m_d_H_i_s') . '.pdf');
  }
}