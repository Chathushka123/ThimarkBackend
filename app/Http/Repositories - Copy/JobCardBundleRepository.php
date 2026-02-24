<?php

namespace App\Http\Repositories;

use App\Bundle;
use App\BundleBin;
use App\BundleTicket;
use App\Exceptions\ConcurrencyCheckFailedException;
use App\Exceptions\GeneralException;
use Illuminate\Http\Request;
use App\JobCardBundle;
use App\Http\Resources\JobCardBundleResource;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use App\Http\Resources\JobCardBundleWithParentsResource;
use Illuminate\Validation\Rule;
use Exception;

use App\Http\Validators\JobCardBundleCreateValidator;
use App\Http\Validators\JobCardBundleUpdateValidator;
use App\JobCard;
use Illuminate\Support\Facades\Log;

class JobCardBundleRepository
{
  public function show(JobCardBundle $jobCardBundle)
  {
    return response()->json(
      [
        'status' => 'success',
        'data' => new JobCardBundleWithParentsResource($jobCardBundle),
      ],
      200
    );
  }

  public static function createRec(array $rec)
  {
    if (JobCardBundle::where('bundle_id', $rec['bundle_id'])->count() > 0) {
      throw new GeneralException("Bundle has already been used in another Job Card");
    }
    $validator = Validator::make(
      $rec,
      JobCardBundleCreateValidator::getCreateRules()
    );
    if ($validator->fails()) {
      Utilities::extractError($validator);
    }

    if ($rec['resized_quantity'] > $rec['original_quantity']) {
      throw new GeneralException("Quantity exceeds the available quantity in bundle No " . $rec['bundle_id']);
    }

    try {
      $bundle = Bundle::findOrFail($rec['bundle_id']);
      $model = JobCardBundle::create($rec);
    } catch (Exception $e) {
      throw new \App\Exceptions\GeneralException($e->getMessage());
    }
    return $model;
  }

  public static function updateRec($model_id, array $rec)
  {
    Log::info('----updateRec-----');
    Log::info($rec);
    $model = JobCardBundle::findOrFail($model_id);

    if (!$model->updated_at->eq(\Carbon\Carbon::parse($rec['updated_at']))) {
      $entity = (new \ReflectionClass($model))->getShortName();
      throw new ConcurrencyCheckFailedException($entity);
    }

    Utilities::hydrate($model, $rec);
    $validator = Validator::make(
      $rec,
      JobCardBundleUpdateValidator::getUpdateRules($model_id)
    );

    if ($validator->fails()) {
      throw new Exception($validator->errors());
    }

    if ($rec['resized_quantity'] > $rec['original_quantity']) {
      throw new Exception("Quantity exceeds the available quantity in bundle No " . $rec['bundle_id']);
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
    $cnt = JobCard::find($master_id)->job_card_bundles()->count();
    $ret = [];
    foreach ($recs as $rec) {
      $parent_key = array_search("!PARENT_KEY!", $rec);
      if ($parent_key) {
        $rec[$parent_key] = $master_id;
      }
      $rec['line_no'] = ++$cnt;
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
    foreach ($recs as $job_card_bundle_id) {
      $modle = JobCardBundle::find($job_card_bundle_id);
      if ($modle->job_card->status != 'Open') {
        throw new GeneralException("Job Cad has progressed. Cannot delete.");
      }
      $bbs = BundleBin::where('job_card_bundle_id', $job_card_bundle_id)->where('utilized', false)->get();
      if ($bbs->count() > 0) {
        foreach ($bbs as $bb) {
          BundleBinRepository::deleteRecs([$bb->id]);
        }
      }
      JobCardBundle::destroy($job_card_bundle_id);
    }
  }
}
