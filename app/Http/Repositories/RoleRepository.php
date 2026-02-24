<?php

namespace App\Http\Repositories;

use Illuminate\Http\Request;
use App\Role;
use App\Http\Resources\RoleResource;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use App\Http\Resources\RoleWithParentsResource;
use App\Http\Validators\RoleCreateValidator;
use Illuminate\Validation\Rule;
use Exception;

use App\Http\Validators\RoleUpdateValidator;
use App\Permission;
use Illuminate\Support\Facades\Log;

class RoleRepository
{
  public function show(Role $role)
  {
    return response()->json(
      [
        'status' => 'success',
        'data' => new RoleWithParentsResource($role),
      ],
      200
    );
  }

  public static function createRec(array $rec)
  {
    $validator = Validator::make(
      $rec,
      RoleCreateValidator::getCreateRules()
    );
    if ($validator->fails()) {
      Utilities::extractError($validator);
    }
    try {
      $model = Role::create($rec);
      PermissionRepository::generatePermissionsGrid();
    } catch (Exception $e) {
      throw new \App\Exceptions\GeneralException($e->getMessage());
    }
    return $model;
  }

  public static function updateRec($model_id, array $rec)
  {
    //if (array_key_exists('role_code', $rec)) {
    //  throw new \App\Exceptions\GeneralException("Role Code cannot be modified.");
    //}

    $model = Role::findOrFail($model_id);
    Utilities::validateCode($model->role_code, $rec['role_code'], "Role Code");

    if (!$model->updated_at->eq(\Carbon\Carbon::parse($rec['updated_at']))) {
      $entity = (new \ReflectionClass($model))->getShortName();
      throw new \App\Exceptions\ConcurrencyCheckFailedException($entity);
    }
    Utilities::hydrate($model, $rec);
    $validator = Validator::make(
      $rec,
      RoleUpdateValidator::getUpdateRules($model_id)
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
    foreach ($recs as $key => $value) {

      $permissions = Permission::where('role_id', $value)->pluck('id')->toArray();
      PermissionRepository::deleteRecs($permissions);
    }
    Role::destroy($recs);
  }
}
