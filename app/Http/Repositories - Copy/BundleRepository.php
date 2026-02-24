<?php

namespace App\Http\Repositories;

use Carbon\Carbon;
use Illuminate\Http\Request;
use App\Bundle;
use App\BundleCutUpdate;
use App\BundleTicket;
use App\CutPlan;
use App\CutUpdate;
use App\FpoCutPlan;
use App\Fppo;
use App\Fpo;
use App\Soc;
use App\BundleTicketSecondary;
use App\Http\Resources\BundleResource;
// use App\HashStore;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use App\Http\Resources\BundleWithParentsResource;
use Illuminate\Validation\Rule;
use Exception;

use App\Http\Validators\BundleCreateValidator;
use App\Http\Validators\BundleUpdateValidator;
use App\JobCardBundle;
use Illuminate\Support\Facades\Log;
use MongoDB\Driver\Exception\ExecutionTimeoutException;

class BundleRepository
{
  public function show(Bundle $bundle)
  {
    return response()->json(
      [
        'status' => 'success',
        'data' => new BundleWithParentsResource($bundle),
      ],
      200
    );
  }

  public static function createRec(array $rec)
  {
    $validator = Validator::make(
      $rec,
      BundleCreateValidator::getCreateRules()
    );
    if ($validator->fails()) {
      throw new Exception($validator->errors());
    }
    try {
      $model = Bundle::create($rec);
    } catch (Exception $e) {
      throw new \App\Exceptions\GeneralException($e->getMessage());
    }
    return $model;
  }

  public static function updateRec($model_id, array $rec)
  {

    $model = Bundle::findOrFail($model_id);

    Utilities::hydrate($model, $rec);
    $validator = Validator::make(
      $rec,
      BundleUpdateValidator::getUpdateRules($model_id)
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
    Bundle::destroy($recs);
  }

  // private function _getBundlesByFppoAndCutNo($fppo_id, $cut_no)
  // {
  //   $fpo_cut_plans = FpoCutPlan::select('fpo_cut_plans.id')
  //     ->distinct()
  //     ->join('cut_plans', 'fpo_cut_plans.cut_plan_id', '=', 'cut_plans.id')
  //     ->where('fppo_id', $fppo_id)
  //     ->where('cut_plans.cut_no', 'LIKE', (is_null($cut_no) ? '%' : $cut_no))
  //     ->get()
  //     ->toArray();
  //   $cut_update_ids = CutUpdate::select('id')->distinct()->whereIn('fpo_cut_plan_id', $fpo_cut_plans)->get()->toArray();
  //   $bundles = Bundle::whereHas('cut_updates', function ($q) use ($cut_update_ids) {
  //     $q->whereIn('id', $cut_update_ids);
  //   })->get();

  //   return $bundles;
  // }

  public static function getBundlesByFppoAndCutId($fppo_id, $cut_id)
  {

    $bundles = FpoCutPlan::select('bundles.*')
      ->join('fppos', 'fpo_cut_plans.fppo_id', '=', 'fppos.id')
      ->join('bundles', 'bundles.fppo_id', '=', 'fppos.id')
      ->where('fpo_cut_plans.fppo_id', $fppo_id)
      ->where('fpo_cut_plans.cut_plan_id', $cut_id)
      ->distinct()
      ->get();

     return $bundles;
  }





  // public function getBundlesByFppoAndCutNo($fppo_id, $cut_no = null)
  // {
  //   return BundleResource::collection($this->_getBundlesByFppoAndCutNo($fppo_id, $cut_no));
  // }

  // public function deleteBundles($fppo_id, $cut_no = null)
  // {
  //   try {
  //     DB::beginTransaction();
  //     $bundles = $this->_getBundlesByFppoAndCutNo($fppo_id, $cut_no);
  //     if (JobCardBundle::whereIn('bundle_id', $bundles->pluck('id'))->exists()) {
  //       throw new \App\Exceptions\GeneralException("Bundles used in Job Card. Cannot delete.");
  //     }
  //     if (BundleTicket::whereIn('bundle_id', $bundles->pluck('id'))->whereNotNull('scan_quantity')->exists()) {
  //       throw new \App\Exceptions\GeneralException("There are Bundles with scans. Cannot delete.");
  //     }

  //     JobCardBundleRepository::deleteRecs(JobCardBundle::whereIn('bundle_id', $bundles->pluck('id'))->pluck('id')->toArray());
  //     BundleTicketRepository::deleteRecs(BundleTicket::whereIn('bundle_id', $bundles->pluck('id'))->pluck('id')->toArray());
  //     BundleCutUpdate::whereIn('bundle_id', $bundles->pluck('id'))->delete();
  //     self::deleteRecs($bundles->pluck('id')->toArray());
  //     DB::commit();
  //   } catch (Exception $e) {
  //     DB::rollBack();
  //     throw $e;
  //   }
  // }

    public static function saveRemarksByFppo(array $data){

      try{
          DB::beginTransaction();

          foreach ($data as $rec){
                  print_r($rec[0]);
              DB::table('bundles')
                  ->where('id', $rec[0])
                  ->update(['special_remarks' => $rec[1]]);
          }


          DB::commit();

          return response()->json(
              [
                  'status' => 'success'
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

    public static function getRemarkByFppo($fppo_id){
        try{
            $cutPlanId = DB::table('cut_updates')
                ->select('cut_plan_id')
                ->where('fppo_id', $fppo_id)
                ->first();

            $remark = "";

            $x = DB::table('cut_plans')
                ->select('special_remark')
                ->where(['id' => $cutPlanId->cut_plan_id])
                ->first();

            if($x != null){
                $remark = $x;
            }


            return response()->json(
                [
                    'status' => 'success',
                    'data' => $remark
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

  public static function deleteBundlesByFppo($fppo_id)
  {
    try {
      DB::beginTransaction();

      $bundles = Bundle::where('fppo_id', $fppo_id)->get();

      if (JobCardBundle::whereIn('bundle_id', $bundles->pluck('id'))->exists()) {
        throw new \App\Exceptions\GeneralException("Bundles used in Job Card. Cannot delete.");
      }

      if (BundleTicket::join('fpo_operations','fpo_operations.id','=','bundle_tickets.fpo_operation_id')->whereIn('bundle_id', $bundles->pluck('id'))->whereNotNull('scan_quantity')->where('fpo_operations.operation','!=','KD')->exists()) {
        throw new \App\Exceptions\GeneralException("There are Bundles with scans. Cannot delete.");
      }

      BundleTicketSecondaryRepository::deleteRecs(BundleTicketSecondary::whereIn('bundle_id', $bundles->pluck('id'))->pluck('id')->toArray());
      BundleTicketRepository::deleteRecs(BundleTicket::whereIn('bundle_id', $bundles->pluck('id'))->pluck('id')->toArray());

      BundleCutUpdate::whereIn('bundle_id', $bundles->pluck('id'))->delete();

      self::deleteRecs($bundles->pluck('id')->toArray());

      DB::commit();

      return response()->json(
        [
          'status' => 'success'
        ],
        200
      );
    } catch (Exception $e) {
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

    public static function deleteBundlesByFppoOneByOne($fppo_id, $bundle_id)
    {
        try {
            DB::beginTransaction();

            $bundles = Bundle::where('fppo_id', $fppo_id)
                ->where('id', $bundle_id)
                ->get();
            if(!is_null($bundles[0]->location_id)){
              throw new \App\Exceptions\GeneralException("Bundle Already GRN In WH");
            }
            
            if (JobCardBundle::whereIn('bundle_id', $bundles->pluck('id'))->exists()) {
                throw new \App\Exceptions\GeneralException("Bundles used in Job Card. Cannot delete.");
            }

            if (BundleTicket::join('fpo_operations','fpo_operations.id','=','bundle_tickets.fpo_operation_id')->whereIn('bundle_id', $bundles->pluck('id'))->whereNotNull('scan_quantity')->where('fpo_operations.operation','!=','KD')->exists()) {
              
                throw new \App\Exceptions\GeneralException("There are Bundles with scans. Cannot delete.");
            }

            BundleTicketSecondaryRepository::deleteRecs(BundleTicketSecondary::whereIn('bundle_id', $bundles->pluck('id'))->pluck('id')->toArray());
            BundleTicketRepository::deleteRecs(BundleTicket::whereIn('bundle_id', $bundles->pluck('id'))->pluck('id')->toArray());

            BundleCutUpdate::whereIn('bundle_id', $bundles->pluck('id'))->delete();

            self::deleteRecs($bundles->pluck('id')->toArray());

            DB::commit();

            return response()->json(
                [
                    'status' => 'success'
                ],
                200
            );
        } catch (Exception $e) {
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

  // private function _getUnutilizedBundlesByFppoAndCutNo($fppo_id, $cut_no = null)
  // {
  //   $fpo_cut_plans = FpoCutPlan::select('fpo_cut_plans.id')
  //     ->distinct()
  //     ->join('cut_plans', 'fpo_cut_plans.cut_plan_id', '=', 'cut_plans.id')
  //     ->where('fppo_id', $fppo_id)
  //     ->where('cut_plans.cut_no', 'LIKE', (is_null($cut_no) ? '%' : $cut_no))
  //     ->get()
  //     ->toArray();
  //   $cut_update_ids = CutUpdate::select('id')->distinct()->where('fppo_id', $fppo_id)->get()->toArray();
  //   $jcb_bundle_ids = JobCardBundle::pluck('bundle_id')->toArray();

  //   $bundles = Bundle::whereHas('cut_updates', function ($q) use ($cut_update_ids) {
  //   $q->whereIn('id', $cut_update_ids);
  //   })
  //   ->whereNotIn('id', $jcb_bundle_ids)->get();

  //   return $bundles;
  // }

  // public function getUnutilizedBundlesByFppoAndCutNo($fppo_id, $cut_no = null)
  // {
  //   return BundleResource::collection($this->_getUnutilizedBundlesByFppoAndCutNo($fppo_id, $cut_no = null));
  // }

  public static function getUnutilizedBundlesByFppoNo($fppo_id)
  {
    $jcb_bundle_ids = JobCardBundle::pluck('bundle_id')->toArray();

    $bundles = Bundle::where('bundles.fppo_id', $fppo_id)
    ->whereNotIn('id', $jcb_bundle_ids)
    ->get();

    return $bundles;

    //$bundles = $this->_getUnutilizedBundlesByFppoAndCutNo($fppo_id);
    //$bundles = $bundles->merge(Bundle::where('fppo_id', $fppo_id)->get());
    //return BundleResource::collection($bundles);
  }

  public static function getUnutilizedBundlesByFpoNo($fpo_id)
  {
    //$jcb_bundle_ids = JobCardBundle::pluck('bundle_id')->toArray();
    $jcb_bundle_ids = JobCardBundle::
        join('job_cards', 'job_cards.id', '=', 'job_card_bundles.job_card_id')
      ->where('job_cards.fpo_id',$fpo_id)
      ->pluck('bundle_id')->toArray();

      $bundles = Bundle::select(
        'fppos.fppo_no as fppo_no',
        'bundles.id as bundle_id',
        'bundles.size',
        'bundles.special_remarks',
        'bundles.quantity as original_quantity')
      ->join('fppos', 'bundles.fppo_id', '=', 'fppos.id')
      ->join('fpo_cut_plans', 'fpo_cut_plans.fppo_id', '=', 'fppos.id')
      ->join('fpos', 'fpo_cut_plans.fpo_id', '=', 'fpos.id')
      ->join('locations', 'locations.id', '=', 'bundles.location_id')
      ->where('fpos.id',$fpo_id)
      ->where('bundles.location_id','!=',null)
      ->where('bundles.location_id','!=',"")
      ->where('locations.site','!=','Move_To_Inspection')
      ->where('locations.site','!=','EA_Send')
      ->where('locations.site','!=','EA_Ready_To_Send')
      ->whereNotIn('bundles.id', $jcb_bundle_ids)
      ->distinct()
      ->get();

    return $bundles;

  }

  ///////////////////////////////////////////////////////////////////////////////////////////////
  //                          Automate Bundle Creation
  //////////////////////////////////////////////////////////////////////////////////////////////

  public function getFppoRelatedData($fppo_no){

    $soc = Soc::select('socs.wfx_soc_no','socs.id','styles.style_code')
    ->join('fpos', 'fpos.soc_id', '=', 'socs.id')
    ->join('fpo_cut_plans','fpo_cut_plans.fpo_id','=','fpos.id')
    ->join('fppos','fppos.id','=','fpo_cut_plans.fppo_id')
    ->join('styles','styles.id','=','socs.style_id')
    ->where('fppos.fppo_no','=',$fppo_no)
    ->first();

    $fpos = Fpo::select('fpos.id','fpos.wfx_fpo_no')
    ->where('fpos.soc_id','=',$soc->id)
    ->get();

    $fppo = Fppo::select('fppos.id','fppos.fppo_no','fpos.wfx_fpo_no','fpos.id as fpo_id','fpo_cut_plans.batch_no')
    ->join('fpo_cut_plans','fpo_cut_plans.fppo_id','=','fppos.id')
    ->join('fpos','fpos.id','=','fpo_cut_plans.fpo_id')
    ->where('fpos.soc_id','=',$soc->id)
    ->get();

    $data['soc'] = $soc;
    $data['fpo'] = $fpos;
    $data['fppo'] = $fppo;

    return response()->json(
      [
          'data' => $data,
          'status' => 'success'
      ],
      200
    );
  }

}
