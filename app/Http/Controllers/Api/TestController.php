<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class TestController extends Controller
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

  public function getTestData(Request $request){
      // converting array to object before calling $this->search
      $queryString = json_decode(json_encode($request->all()), FALSE);
      return $this->search($queryString);
  }

  private function search($searchParams, $needValidJson = false)
  {
    $ret = array();
    try {
      foreach ($searchParams as $obj => $params) {
        $where = array();
        foreach ($params->where as $i => $fields) {
          $where[] = [
            data_get($fields, 'field-name'),
            data_get($fields, 'operator'),
            data_get($fields, 'value')
          ];
        }

        $whereins = array();
        if (isset($params->wherein) && ($params->wherein != "")) {
          foreach ($params->wherein as $i => $fields) {
            $whereins[data_get($fields, 'field-name')] = data_get($fields, 'value');
          }
        }

        $model = "App\\" . $obj;
        if (isset($params->select) && ($params->select != "") && ($params->select != "*")) {
          $fieldList = $params->select;
        } else {
          $fieldList = Schema::getColumnListing((new $model)->getTable());
        }
        $relations = null;
        if (isset($params->relations) && ($params->relations != "")) {
          $relations = $params->relations;
        }
        $distinct = false;
        if (isset($params->distinct) && ($params->distinct != "")) {
          $distinct = $params->distinct;
        }
        $orderby = false;
        if (isset($params->orderby) && ($params->orderby != "")) {
          $orderby = $params->orderby;
        }
        $limit = false;
        if (isset($params->limit) && ($params->limit != "")) {
          $limit = $params->limit;
        }

        $query = null;
        if ($relations === null) {
          if (!empty($where)) {
            $query = $model::where($where);
          }
          if (!empty($whereins)) {
            foreach ($whereins as $key => $value) {
              $query = $model::whereIn($key, $value);
            }
          }
        } else {
          $query = $model::with($relations);
          if (!empty($where)) {
            $query = $query->where($where);
          }
          if (!empty($whereins)) {
            foreach ($whereins as $key => $value) {
              $query = $query->whereIn($key, $value);
            }
          }
        }

        if ($distinct) {
          $query = $query->distinct();
        }

        $query = $query->select($fieldList);

        if ($orderby) {
          $orderByArr = explode(':', $orderby);
          $query = $query->orderBy($orderByArr[0], $orderByArr[1]);
        }

        if ($limit) {
          $query = $query->limit($limit);
        }

        // Log::info($query->toSql());
        $results =  $query->get();
        //sorting json
        foreach ($results as $key => $result) {
          if ((isset($result->qty_json)) && (isset($result->qty_json_order))) {
            $result->qty_json = Utilities::sortQtyJson($result->qty_json_order, $result->qty_json);
          }
        }

        if ($needValidJson) {
          $ret[$obj] = $results;
        } else {
          $ret[] = [$obj => $results];
        }
      }

      return $ret;
    } catch (Exception $e) {
      return response()->json(["status" => "error", "message" => $e->getMessage()], 400);
    }
  }

  private function destroy($model, $recs)
  {
    $repo = "App\\Http\\Repositories\\" . $model . "Repository";
    return $repo::deleteRecs($recs);
  }
}