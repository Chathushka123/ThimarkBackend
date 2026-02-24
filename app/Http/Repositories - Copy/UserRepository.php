<?php

namespace App\Http\Repositories;

use Illuminate\Http\Request;
use App\User;
use App\Http\Resources\UserResource;
// use App\HashStore;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use App\Http\Resources\UserWithParentsResource;
use Illuminate\Validation\Rule;
use Exception;
use Illuminate\Support\Facades\Crypt;

use App\Http\Validators\UserCreateValidator;
use App\Http\Validators\UserUpdateValidator;
use Illuminate\Support\Facades\Log;

class UserRepository
{

  public static function createRec(array $rec)
  {
    $rec['password'] = bcrypt('1234');
    $validator = Validator::make(
      $rec,
      UserCreateValidator::getCreateRules()
    );
    if ($validator->fails()) {
      throw new Exception($validator->errors());
    }
    try {
      
      $model = User::create($rec);
    } catch (Exception $e) {
      throw new \App\Exceptions\GeneralException($e->getMessage());
    }
    return $model;
  }

  public static function updateRec($model_id, array $rec)
  {

    $model = User::findOrFail($model_id);
     Utilities::hydrate($model, $rec);
    $validator = Validator::make(
      $rec,
      UserUpdateValidator::getUpdateRules($model_id)
    );
    if ($validator->fails()) {
      throw new Exception($validator->errors());
    }
    if ($model->email == "sysadmin@gmail.com"){     
      if($model->email != $rec['email']){
        throw new \App\Exceptions\GeneralException("Changes to Administrator Information is not Allowed");
      }
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
   $user =  User::whereIn('id', $recs)->where('email','sysadmin@gmail.com')->get();
    if($user->count() > 0){
      throw new \App\Exceptions\GeneralException("Cannot Remove System Administrator");
    }

    User::destroy($recs);
  }

  public static function changePassword($user_id, $password, $updated_at)
  {
    try {
      DB::beginTransaction();
      $model = User::find($user_id);
      $model->password = bcrypt($password);
      $model->update_at = $updated_at;
      if($model->common_user_state == 1){
        $model->common_user = Crypt::encrypt($password);
      }else{
        $model->common_user = null;
      }
      self::updateRec($model->id, $model->toArray());
      DB::commit();
      return response()->json(["status" => "success"], 200);
    } catch (Exception $e) {
      DB::rollBack();
      return response()->json(["status" => "error", "message" => ($e->getMessage()), "trace" => $e->getTraceAsString()], 400);
    }
  }
  
}
