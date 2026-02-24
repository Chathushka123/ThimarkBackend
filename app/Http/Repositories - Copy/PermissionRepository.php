<?php

namespace App\Http\Repositories;

use Illuminate\Http\Request;
use App\Permission;
use App\Http\Resources\PermissionResource;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use App\Http\Resources\PermissionWithParentsResource;
use Illuminate\Validation\Rule;
use Exception;

use App\Http\Validators\PermissionCreateValidator;
use App\Http\Validators\PermissionUpdateValidator;
use App\Role;
use App\Screen;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class PermissionRepository
{
  public function show(Permission $permission)
  {
    return response()->json(
      [
        'status' => 'success',
        'data' => new PermissionWithParentsResource($permission),
      ],
      200
    );
  }

  public static function createRec(array $rec)
  {
    $validator = Validator::make(
      $rec,
      PermissionCreateValidator::getCreateRules()
    );
    if ($validator->fails()) {
      throw new Exception($validator->errors());
    }
    try {
      $model = Permission::create($rec);
    } catch (Exception $e) {
      throw new \App\Exceptions\GeneralException($e->getMessage());
    }
    return $model;
  }

  public static function updateRec($model_id, array $rec)
  {

    $model = Permission::findOrFail($model_id);

    if (!$model->updated_at->eq(\Carbon\Carbon::parse($rec['updated_at']))) {
      $entity = (new \ReflectionClass($model))->getShortName();
      throw new \App\Exceptions\ConcurrencyCheckFailedException($entity);
    }
    Utilities::hydrate($model, $rec);
    $validator = Validator::make(
      $rec,
      PermissionUpdateValidator::getUpdateRules($model_id)
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
    Permission::destroy($recs);
  }

  public function getPermissions()
  {
    $final_array = [];
    $screens = Screen::get();
    $roles = Role::get();
    $curr_arry = [];
    foreach($screens as $screen){
      $curr_arry = [];
      $curr_arry["key"] = $screen->screen_code;
      $curr_arry["function"] = $screen->screen_name;
      foreach($roles as $role){
          $rec = Permission::select('grant')->where('screen_id', $screen->id)->where('role_id', $role->id)->first();
          if(!is_null($rec)){
            $curr_arry[$role->role_code] = $rec['grant'];
          }
          
      }
      array_push($final_array, $curr_arry);
    }
    return $final_array;
  }

  public function isAuthorized($screen_code)
  {
    $user = Auth::user();
    if ($user->email != 'sysadmin@gmail.com') {
    $permitted = "";
    $user = Auth::user();
    $screen = Screen::where('screen_code', $screen_code)->first();
    if (!(is_null($screen))) {
      $permission = Permission::where('screen_id', $screen->id)->where('role_id', $user->role_id)->first();
      if (!(is_null($permission))) {
        if (!(is_null($permission->grant)))
          $permitted = $permission->grant;
      }
    }
    return $permitted;}
    else{
      return "w";
    }
  }

  public function getNavigator()
  {
    include resource_path('navigator.php');
    //$json = file_get_contents(asset('navigator.json'));
    $user = Auth::user();
    
    if ($user->email != 'sysadmin@gmail.com') {
      $json =  json_decode($navigator_json, true);
      $permission = Permission::where('role_id', Auth::user()->role_id)->whereNotNull('grant')->with('screen')->get();
      
      //return $permission;
      foreach ($permission as $perm) {
        foreach ($json as $index => $rec) {
          if (array_key_exists("nodes", $rec)) {
            foreach ($rec["nodes"] as $nodeIndex => $node) {
              if ($node["path"] == "/" . $perm->screen->screen_code) {
                $json[$index]["nodes"][$nodeIndex]["permitted"] = 1;
                $json[$index]["permitted"] = 1;
              }
            }
          } else {
            if ($rec["path"] ==  "/" . $perm->screen->screen_code) {
              $json[$index]["permitted"] = 1;
            }
          }
        }
      }
      $intermediate =  array_filter($json, function ($v, $k) {
        return $v['permitted'] == 1;
      }, ARRAY_FILTER_USE_BOTH);

      $intermediate =  array_values($intermediate);

      foreach ($intermediate as $index => $element) {
        $vararr =  array_filter($element["nodes"], function ($v, $k) {
          return $v['permitted'] == 1;
        }, ARRAY_FILTER_USE_BOTH);

        $intermediate[$index]["nodes"] = array_values($vararr);
      }
      return $intermediate;
    } 
    else {
      
      return json_decode($navigator_json, true);
    }
  }

  public static function generatePermissionsGrid()
  {
    $screens = Screen::get();
    $roles = Role::get();
    $rec = [];
    foreach ($screens as $screen) {
      foreach ($roles as $role) {
        $perm_rec = Permission::where('screen_id', $screen->id)->where('role_id', $role->id)->get();
        if (!($perm_rec->count() > 0)) {
          $rec["role_id"] = $role->id;
          $rec["screen_id"] = $screen->id;
          self::createRec($rec);
        }
      }
    }
  }

  public function updatePermissions($request){
    try {
      DB::beginTransaction();

      $full_array = $request->permissions;
      foreach ($full_array as $index => $attrib_array) {

        $val = $attrib_array['key'];
        $screen_obj = Screen::where('screen_code', $val)->first();

        foreach ($attrib_array as $attrib_index => $attrib_value) {
          if (($attrib_index != 'function') && ($attrib_index != 'key')) {
            $role_obj = Role::where('role_code', $attrib_index)->first();
            $permission_obj = Permission::where('role_id', $role_obj->id)->where('screen_id', $screen_obj->id)->first();
            $permission_obj->grant = $attrib_value;
            PermissionRepository::updateRec($permission_obj->id, $permission_obj->toArray());
          }
        }
      }
      DB::commit();
      return response()->json(["status" => "success"], 200);
    } catch (Exception $e) {
      DB::rollBack();
      throw new \App\Exceptions\GeneralException($e->getMessage());
    }
  }

}
