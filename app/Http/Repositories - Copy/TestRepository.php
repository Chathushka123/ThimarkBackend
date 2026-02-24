<?php

namespace App\Http\Repositories;

use Illuminate\Http\Request;
use App\Test;
use App\CutPlan;
use App\CutUpdate;
use App\Http\Resources\TestResource;
// use App\HashStore;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use App\Http\Resources\TestWithParentsResource;
use Illuminate\Validation\Rule;
use Exception;
use Illuminate\Support\Str;

use App\Http\Validators\TestCreateValidator;


class TestRepository
{
    public static function createRec(array $rec)
    {
      $validator = Validator::make(
        $rec,
        TestCreateValidator::getCreateRules()
      );
      if ($validator->fails()) {
        throw new Exception($validator->errors());
      }
      $rec['code'] = Str::upper($rec['code']);
      
      try {
        $model = Test::create($rec);
      } catch (Exception $e) {
        throw new \App\Exceptions\GeneralException($e->getMessage());
      }
      return $model;
    }

    public static function deleteRecs(array $recs)
    {
     // Test::destroy($recs);
     //print_r($recs);
      $deleted = DB::delete('delete from tests where code = "'.$recs[0].'"');
      return $deleted;
     
    }
}