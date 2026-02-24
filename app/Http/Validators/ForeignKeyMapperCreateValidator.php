<?php

namespace App\Http\Validators;

use App\Http\Validators\ForeignKeyMapperCommonValidator;

class ForeignKeyMapperCreateValidator
{
  public static function getCreateRules()
  {
    return array_merge([
      'model' => 'required|unique:foreign_key_mappers'
    ], ForeignKeyMapperCommonValidator::getCommonRules());
  }
}
