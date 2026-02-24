<?php

namespace App\Http\Repositories;

use App\BundleBin;
use Illuminate\Http\Request;
use App\QcReject;
use App\Http\Resources\QcRejectResource;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use App\Http\Resources\QcRejectWithParentsResource;
use Illuminate\Validation\Rule;
use Exception;

use App\Http\Validators\QcRejectCreateValidator;
use App\Http\Validators\QcRejectUpdateValidator;

class QcRejectRepository
{
  public function show(Team $team)
  {
    return response()->json(
      [
        'status' => 'success',
        'data' => new QcRejectWithParentsResource($team),
      ],
      200
    );
  }

  public static function createRec(array $rec)
  {
    $validator = Validator::make(
      $rec,
      QcRejectCreateValidator::getCreateRules()
    );
    if ($validator->fails()) {
      throw new Exception($validator->errors());
    }
    // try {
    $model = QcReject::create($rec);
    // } catch (Exception $e) {
    //   throw new Exception(json_encode([$e->getMessage()]));
    // }
    return $model;
  }

  public static function updateRec($model_id, array $rec)
  {
    // if ($master_id == null) {
    $model = QcReject::findOrFail($model_id);
    // } else {
    //   $model = Team::where($parent_key, $master_id)
    //     ->where('id', $model_id)
    //     ->firstOrFail();
    // }
    // return $model;
    if (!$model->updated_at->eq(\Carbon\Carbon::parse($rec['updated_at']))) {
      $entity = (new \ReflectionClass($model))->getShortName();
      throw new \App\Exceptions\ConcurrencyCheckFailedException($entity);
    }
    Utilities::hydrate($model, $rec);
    $validator = Validator::make(
      $rec,
      QcRejectUpdateValidator::getUpdateRules($model_id)
    );
    if ($validator->fails()) {
      throw new Exception($validator->errors());
    }
    // try {
    $model->update($rec);
    // } catch (Exception $e) {
    //   throw new Exception(json_encode([$e->getMessage()]));
    // }
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
      foreach ($recs as $qcr_id) {
        $cnt = BundleBin::where('qc_reject_id', $qcr_id)->where('utilized', true)->count();
        if ($cnt > 0) {
          throw new \App\Exceptions\GeneralException("Rejected quantities are in progress");
        }
        $bbs = BundleBin::where('qc_reject_id', $qcr_id)->where('utilized', false)->get();
        if ($bbs->count() > 0) {
          foreach ($bbs as $bb) {
            BundleBinRepository::deleteRecs([$bb->id]);
          }
        }
        QcReject::destroy($qcr_id);
      }
      DB::commit();
      return response()->json([], 200);
    } catch (Exception $e) {
      DB::rollBack();
      throw $e;
    }
  }
}
