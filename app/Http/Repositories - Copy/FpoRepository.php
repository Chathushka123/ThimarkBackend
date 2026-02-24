<?php

namespace App\Http\Repositories;

use App\Exceptions\ConcurrencyCheckFailedException;
use Illuminate\Http\Request;
use App\Fpo;
use App\FpoCutPlan;
use App\FpoFabric;
use App\FpoOperation;
use App\Http\Controllers\Api\SocController;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use App\Http\Resources\FpoWithParentsResource;
use Illuminate\Validation\Rule;
use Exception;
use App\Http\FunctionalValidators\FpoFunctionalValidator;

use App\Http\Validators\FpoCreateValidator;
use App\Http\Validators\FpoUpdateValidator;
use Illuminate\Support\Facades\Cookie;
use App\Http\Controllers\Controller;
use App\Soc;
use Illuminate\Support\Facades\Log;
use PDF;
use PhpOffice\PhpSpreadsheet\Writer\Pdf as WriterPdf;

class FpoRepository
{
  public function show(Fpo $fpo)
  {
    return response()->json(
      [
        'status' => 'success',
        'data' => new FpoWithParentsResource($fpo),
      ],
      200
    );
  }

  private static function getValidationMessages()
  {
    return [
      'wfx_fpo_no.required' => 'FPO No is required',
      'wfx_fpo_no.unique' => 'FPO No has already been taken',
    ];
  }

  public static function createRec(array $rec)
  {
    $rec['qty_json'] = Utilities::fillJsonValues($rec['qty_json'], 0);
    $rec['qty_json'] = json_encode($rec['qty_json']);

    $validator = Validator::make(
      $rec,
      FpoCreateValidator::getCreateRules(),
      self::getValidationMessages()
    );
    if ($validator->fails()) {
      throw new Exception($validator->errors());
    }
    $funcValidator = new FpoFunctionalValidator($rec);
    $funcValidator->validateCreate();
    $rec['qty_json'] = Utilities::json_numerize(json_decode($rec['qty_json'], true), "int");
    try {
      $rec['status'] = Fpo::getInitialStatus();
      $model = Fpo::create($rec);
      //SocController::fsmActionClose(Soc::findOrFail($model->soc_id));

    } catch (Exception $e) {
      throw new \App\Exceptions\GeneralException($e->getMessage());
    }
    return $model;
  }

  public static function updateRec($model_id, array $rec)
  {
    $model = Fpo::findOrFail($model_id);
   
   //$model = Fpo::where('wfx_fpo_no', '=', $model_id)->firstOrFail();
    
    if ($model->status == "Closed") {
      throw new \App\Exceptions\NoModificationsAllowedException("FPO", "Closed");
    }

    if (!$model->updated_at->eq(\Carbon\Carbon::parse($rec['updated_at']))) {
      $entity = (new \ReflectionClass($model))->getShortName();
      throw new ConcurrencyCheckFailedException($entity);
    }

    if (array_key_exists('qty_json', $rec)) {
      $rec['qty_json'] = Utilities::fillJsonValues($rec['qty_json'], 0);
      $rec['qty_json'] = json_encode($rec['qty_json']);
      
    }
    
    Utilities::hydrate($model, $rec);
    $validator = Validator::make(
      $rec,
      FpoUpdateValidator::getUpdateRules($model_id),
      self::getValidationMessages()
    );
    if ($validator->fails()) {
      throw new Exception($validator->errors());
    }

    $funcValidator = new FpoFunctionalValidator($rec, $model);
    $funcValidator->validateUpdate();

    if (array_key_exists('qty_json', $rec)) {
      $rec['qty_json'] = Utilities::json_numerize(json_decode($rec['qty_json'], true), "int");
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

    if (FpoCutPlan::whereIn('fpo_id', $recs)->exists()) {
      throw new \App\Exceptions\GeneralException('FPO has been progressed, Not allowed to delete');
    };

    foreach ($recs as $id) {
      $fpo = Fpo::find($id);
      FpoOperationRepository::deleteRecs(
        FpoOperation::where('fpo_id', $fpo->id)->pluck('id')->toArray()
      );

      FpoFabricRepository::deleteRecs(
        FpoFabric::where('fpo_id', $fpo->id)->pluck('id')->toArray()
      );

      if (!(is_null($fpo->combine_order_id))) {
        throw new \App\Exceptions\GeneralException('FPO has been progressed, Not allowed to delete');
      }
    }
    FpoCutPlan::whereIn('fpo_id', $recs)->delete();

    FpoOperation::whereIn('fpo_id', $recs)->delete();

    Fpo::destroy($recs);
  }

  public function getColorQuantities($fpoIds)
  {
    $ret = [];
    $color_quantities = [];
    $sum = [];
    foreach ($fpoIds as $fpoId) {
      $fpo = Fpo::find($fpoId);
      $fpo->load('soc');
      $color_quantities[] = [
        'fpo_id' => $fpo->id,
        'fpo_no' => $fpo->wfx_fpo_no,
        'garment_color' => $fpo->soc->garment_color,
        'qty_json' => $fpo->qty_json
      ];
      foreach ($fpo->qty_json as $key => $value) {
        if (isset($sum[$key])) {
          $sum[$key] += $value;
        } else {
          $sum[$key] = $value;
        }
      }
    }
    $ret[] = [
      'color_quantities' => $color_quantities,
      'sum' => $sum
    ];

    return $ret;
  }

  public function createPackingList(Fpo $fpo)
  {
    $bundles = [];
    $bundle_ids = [];
    // return $fpo->fpo_cut_plans; //->cut_updates()->bundle_cuts;

    foreach ($fpo->fpo_cut_plans as $fpo_cut_plan) {
      foreach ($fpo_cut_plan->cut_updates as $cut_update) {
        foreach ($cut_update->bundle_cuts as $bundle_cut) {
          $bundles[$bundle_cut->bundle->id] = $bundle_cut->bundle;
          $bundle_ids[] = $bundle_cut->bundle->id;
        }
      }
    }
    // return $bundles;
    $bundle_ids = array_unique($bundle_ids);

    foreach ($fpo->fpo_operations as $fpo_operation) {
      foreach ($bundle_ids as $bundle_id) {
        BundleTicketRepository::createRec([
          'bundle_id' => $bundle_id,
          'original_quantity' => $bundles[$bundle_id]->quantity,
          'scan_quantity' => 0,
          'scan_hour_id' => 0,
          'fpo_operation_id' => $fpo_operation->id
        ]);
      }
    }
  }



  public function generateLayout(Request $request)
  {
    $page = $request->input('page', [
      "width" => "210",
      "height" > "297",
      "margin" => [
        "left" => 0,
        "top" => 10,
        "right" => 0,
        "bottom" => 10
      ]
    ]);
    $params = ['cols' => 5, 'rows' => '3', 'page' => $page];
    //$pdf = PDF::loadView('fpo.sticker', ['params' => $params]);
    //return $pdf->download();
    // return view('fpo.sticker', ['params' => $params]);

  }


}
