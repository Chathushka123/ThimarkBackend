<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class MasterDetailController extends Controller
{

  public function __invoke(Request $request)
  {
    

    $ret = [];
    try {
      DB::beginTransaction();
      foreach ($request->all() as $master_model => $body) {
        if (array_key_exists('CRE', $body) && !empty($body['CRE'])) {
          foreach ($body['CRE'] as $key => $master_body) {
            $ret[] = $this->create($master_model, $master_body);
          }
        }
        if (array_key_exists('UPD', $body) && !empty($body['UPD'])) {
          foreach ($body['UPD'] as $key => $master_body) {
            $this->update($master_model, $master_body);
          }
        }
        if (array_key_exists('DEL', $body) && !empty($body['DEL'])) {
          $this->destroy($master_model, $body['DEL']);
        }
      }
      DB::commit();
      return response()->json(["status" => "success", "data" => $ret], 200);
    } catch (Exception $e) {
      DB::rollBack();
      return response()->json(["status" => "error", "message" => ($e->getMessage()), "trace" => $e->getTraceAsString()], 400);
    }
  }

  private function create($masterModel, $masterRec)
  {
    $masterRepository = "App\\Http\\Repositories\\" . $masterModel . "Repository";
    $relations = isset($masterRec['relations']) ? $masterRec['relations'] : null;
    $masterData = array_diff_key($masterRec, array('relations' => 'dummy'));
    
    // $currentExecutionPointer = ["source" => ["model" => $masterModel, "data" => $masterData]];
    $masterObj = $masterRepository::createRec($masterData);

    if ($relations !== null) {
      foreach ($relations as $childModel => $content) {
        $childRepository = "App\\Http\\Repositories\\" . $childModel . "Repository";
        if (array_key_exists('CRE', $content) && !empty($content['CRE'])) {
          $childRepository::createMultipleRecs($masterObj->id, $content['CRE']);
        }
        if (array_key_exists('UPD', $content) && !empty($content['UPD'])) {
          $childRepository::updateMultipleRecs($masterObj->id, $content['UPD']);
        }

      }
    }
    return $masterObj;

  }

  private function update($masterModel, $masterRec)
  {

    $masterRepository = "App\\Http\\Repositories\\" . $masterModel . "Repository";
    foreach ($masterRec as $masterId => $content) {
      $relations = (array_key_exists('relations', $content)) ? $content['relations'] : null;
      $masterData = array_diff_key($content, array('relations' => 'dummy'));
      $currentExecutionPointer = ["action" => 'UPD', "source" => $masterId];
      $masterObj = $masterRepository::updateRec($masterId, $masterData);

      if (!is_null($relations)) {
        foreach ($relations as $childModel => $content) {
          $childRepository = "App\\Http\\Repositories\\" . $childModel . "Repository";
          if (array_key_exists('CRE', $content) && !empty($content['CRE'])) {
            $childRepository::createMultipleRecs($masterId, $content['CRE']);
          }

          if (array_key_exists('UPD', $content) && !empty($content['UPD'])) {
            
            $childRepository::updateMultipleRecs($masterId, $content['UPD']);
          }
          if (array_key_exists('DEL', $content) && !empty($content['DEL'])) {
            $currentExecutionPointer = ["action" => 'DEL'];
            $childRepository::deleteRecs($content['DEL']);
          }
        }
      }
      
    }
  }

  private function destroy($model, $recs)
  {
    $repo = "App\\Http\\Repositories\\" . $model . "Repository";
    return $repo::deleteRecs($recs);
  }
}
