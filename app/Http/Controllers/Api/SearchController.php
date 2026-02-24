<?php

namespace App\Http\Controllers\Api;

use Illuminate\Support\Facades\Schema;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use App\HashStore;
use App\Http\Repositories\Utilities;
use Illuminate\Support\Str;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Monolog\Utils;

class SearchController extends Controller
{
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

  public function searchByUuid($uuid)
  {
    $hashStore = HashStore::where('key', $uuid)->firstOrFail();
    return $this->search(json_decode($hashStore['source']));
  }

  public function searchByParameters(Request $request)
  {
    // converting array to object before calling $this->search
    $queryString = json_decode(json_encode($request->all()), FALSE);
   // print_r($queryString);
    return $this->search($queryString);
  }

  public function searchByParametersJson(Request $request)
  {
    // converting array to object before calling $this->search
    $queryString = json_decode(json_encode($request->all()), FALSE);
    return $this->search($queryString, true);
  }

  public function novelSearch(Request $request)
  {
    $with = [];
    $searchJson = $request->all();

    $relationsAreSet = isset($searchJson[array_key_first($searchJson)]['relations']);
    
    if ($relationsAreSet) {
      $searchJsonRelations = $searchJson[array_key_first($searchJson)]['relations'];
      if (($searchJsonRelations != "")) {
        
        foreach ($searchJsonRelations as $key => $temp) {
          $with[] = Str::lower(Str::snake(array_key_first($temp)));
        }
        
      }
    }

    $orderbyIsSet = isset($searchJson[array_key_first($searchJson)]['orderby']);
    if ($orderbyIsSet) {
      $searchJsonOrderby = $searchJson[array_key_first($searchJson)]['orderby'];
      if (($searchJsonOrderby != "")) {
        $orderby = $searchJsonOrderby;
        unset($searchJson[array_key_first($searchJson)]['orderby']);
      }
    }

    $limitIsSet = isset($searchJson[array_key_first($searchJson)]['limit']);
    if ($limitIsSet) {
      $searchJsonLimit = $searchJson[array_key_first($searchJson)]['limit'];
      if (isset($searchJsonLimit) && ($searchJsonLimit != "")) {
        $limit = $searchJsonLimit;
        unset($searchJson[array_key_first($searchJson)]['limit']);
      }
    }

    $rootClass = "App\\" . array_key_first($searchJson);
    $query = '';
    if (empty($with)) {
      $query = $rootClass::whereIn('id', $this->_getModelIds($searchJson));
      $this->_addOrderByAndLimit($query, $orderby, $limit);

      //sorting json
      $results =  $query->get();
      foreach ($results as $key => $result) {
        if ((isset($result->qty_json)) && (isset($result->qty_json_order))) {
          $result->qty_json = Utilities::sortQtyJson($result->qty_json_order, $result->qty_json);
        }
      }
    } else {
      $query = $rootClass::with($with)->whereIn('id', $this->_getModelIds($searchJson));
      $this->_addOrderByAndLimit($query, $orderby, $limit);
      $sql = $query->toSql();

      //sorting json
      $results =  $query->get();
      foreach ($results as $key => $result) {
        if ((isset($result->qty_json)) && (isset($result->qty_json_order))) {
          $result->qty_json = Utilities::sortQtyJson($result->qty_json_order, $result->qty_json);
        }
      }
    }
    // return $sql;
    $resourceClass = "App\\Http\\Resources\\" . array_key_first($searchJson) . "WithParentsResource";
    return $resourceClass::collection($results);
  }

  private function _getModelIds($searchJson)
  {
    foreach ($searchJson as $obj => $params) {
      $query = DB::table(Str::plural(Str::snake($obj)));
      // remove relations from $params and get it to $relations
      $relations = null;
      if (isset($params['relations']) && ($params['relations'] != "")) {
        $relations = $params['relations'];
        unset($params['relations']);
      }
      // adding where clauses
      foreach ($params as $key => $value) {
        if ($value == '%') {
          // $query->where($key, 'LIKE', '%');
          null;
        } else if ($value == '') {
          $query->where(function ($query) use ($key) {
            $query->whereNull($key)->orWhere($key, '');
          });
        } else {
          $query->where($key, 'LIKE', '%' . $value . '%');
        }
      }
      // adding whereIn clauses to include relations
      if ($relations) {
        foreach ($relations as $relationJson) {
          $query->whereIn(Str::snake(array_key_first($relationJson)) . '_id', $this->_getModelIds($relationJson));
        }
      }
      return $query->pluck('id');
    }
  }


  private function _addOrderByAndLimit(&$query, $orderby, $limit)
  {
    if ($orderby) {
      $orderByArr = explode(':', $orderby);
      $query = $query->orderBy($orderByArr[0], $orderByArr[1]);
    }
    if ($limit) {
      $query = $query->limit($limit);
    }
  }
}
