<?php

namespace App\Http\Repositories;

use Illuminate\Http\Request;
use App\BundleBin;
use App\Fppo;
use App\Http\Resources\BundleBinResource;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use App\Http\Resources\BundleBinWithParentsResource;
use Illuminate\Validation\Rule;
use Exception;

use App\Http\Validators\BundleBinCreateValidator;
use App\Http\Validators\BundleBinUpdateValidator;
use App\Exceptions\ConcurrencyCheckFailedException;
use App\FpoCutPlan;

class BundleBinRepository
{
  public function show(BundleBin $bundleBin)
  {
    return response()->json(
      [
        'status' => 'success',
        'data' => new BundleBinWithParentsResource($bundleBin),
      ],
      200
    );
  }

  public static function createRec(array $rec)
  {
    $validator = Validator::make(
      $rec,
      BundleBinCreateValidator::getCreateRules()
    );
    if ($validator->fails()) {
      throw new Exception($validator->errors());
    }
    try {
      $model = BundleBin::create($rec);
    } catch (Exception $e) {
      throw new \App\Exceptions\GeneralException($e->getMessage());
    }
    return $model;
  }

  public static function updateRec($model_id, array $rec)
  {
    // if ($master_id == null) {
    $model = BundleBin::findOrFail($model_id);
    // } else {
    //   $model = BundleBin::where($parent_key, $master_id)
    //     ->where('id', $model_id)
    //     ->firstOrFail();
    // }
    // return $model;
    if (!$model->updated_at->eq(\Carbon\Carbon::parse($rec['updated_at']))) {
      $entity = (new \ReflectionClass($model))->getShortName();
      throw new ConcurrencyCheckFailedException($entity);
    }
    Utilities::hydrate($model, $rec);
    $validator = Validator::make(
      $rec,
      BundleBinUpdateValidator::getUpdateRules($model_id)
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
    BundleBin::destroy($recs);
  }


  public function getByFppo($fppo_id)
  {
    $bundle_bins = $this->_getByFppoJcReject($fppo_id)->merge($this->_getByFppoQcReject($fppo_id));
    return $bundle_bins;
  }


  private function _getByFppoJcReject($fppo_id)
  {
    
    $bundles_bins = FpoCutPlan::select(
      'bundle_bins.id',
      'bundle_bins.size',
      'bundle_bins.quantity',
      'bundle_bins.created_date',
      'users.name as created_by',
      DB::raw("'JC Resize' as record_type")
    )
    ->join('fpos','fpo_cut_plans.fpo_id', '=' , 'fpos.id')
    ->join('job_cards','job_cards.fpo_id', '=' , 'fpos.id')
    ->join('job_card_bundles','job_card_bundles.job_card_id', '=' , 'job_cards.id')
    ->join('bundle_bins','bundle_bins.job_card_bundle_id', '=' , 'job_card_bundles.id')
    ->join('users','users.id', '=' , 'bundle_bins.created_by_id')
    ->where('bundle_bins.utilized', false)
    ->where('fpo_cut_plans.fppo_id', $fppo_id)
    ->distinct()
    ->get();
    return $bundles_bins;
  }


  private function _getByFppoQcReject($fppo_id)
  {

    $bundles_bins = FpoCutPlan::select(
      'bundle_bins.id',
      'bundle_bins.size',
      'bundle_bins.quantity',
      'bundle_bins.created_date',
      'users.name as created_by',
      DB::raw("'QC Reject' as record_type")
    )
    ->join('fpos','fpo_cut_plans.fpo_id', '=' , 'fpos.id')
    ->join('fpo_operations','fpo_operations.fpo_id', '=' , 'fpos.id')
    ->join('bundle_tickets','bundle_tickets.fpo_operation_id', '=' , 'fpo_operations.id')
    ->join('qc_rejects','qc_rejects.bundle_ticket_id', '=' , 'bundle_tickets.id')
    ->join('bundle_bins','bundle_bins.qc_reject_id', '=' , 'qc_rejects.id')
    ->join('users','users.id', '=' , 'bundle_bins.created_by_id')
    ->where('bundle_bins.utilized', false)
    ->where('fpo_cut_plans.fppo_id', $fppo_id)
    ->distinct()
    ->get();
    return $bundles_bins;
  }

  public function createBundle($fppo_id, array $bb_ids)
  {
    try {
      DB::beginTransaction();
      $sizes = BundleBin::whereIn('id', $bb_ids)->pluck('size')->toArray();
      if (sizeof(array_unique($sizes)) != 1) {
        throw new \App\Exceptions\GeneralException("Bundle Bins have different sizes.");
      }

      $tot_quantity = BundleBin::whereIn('id', $bb_ids)->sum('quantity');

      $bundle = BundleRepository::createRec([
        'fppo_id' => $fppo_id,
        'size' => $sizes[0],
        'quantity' => $tot_quantity
      ]);

      foreach ($bb_ids as $bb_id) {
        $bb = BundleBin::findOrFail($bb_id);
        self::updateRec($bb_id, ['utilized' => true, 'bundle_id' => $bundle->id, 'updated_at' => $bb->updated_at]);
      }

      DB::commit();
      return response()->json(["status" => "success", "data" => $bundle], 200);
    } catch (Exception $e) {
      DB::rollBack();
      throw $e;
    }
  }
}
