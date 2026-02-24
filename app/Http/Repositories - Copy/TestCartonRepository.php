<?php

namespace App\Http\Repositories;

use Illuminate\Http\Request;
use App\TestCarton;
use App\Http\Resources\TestCartonResource;
//use App\Http\Resources\BuyerCartontWithParentsResource;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Exception;

use App\Http\Validators\TestCartonCreateValidator;
//use App\Http\Validators\BuyerCartonUpdateValidator;

class TestCartonRepository
{

    public static function createRec(array $rec)
    {
      $validator = Validator::make(
        $rec,
        TestCartonCreateValidator::getCreateRules()
      );
      if ($validator->fails()) {
        throw new Exception($validator->errors());
      }
      try {
        $model = TestCarton::create($rec);
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
}