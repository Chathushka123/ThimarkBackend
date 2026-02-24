<?php

namespace App\Http\Validators;

use App\Http\Validators\ForeignKeyMapperCommonValidator;
use Illuminate\Validation\Rule;

class ForeignKeyMapperUpdateValidator
{
  public static function getUpdateRules($keyIgnore)
  {
    return array_merge([
      'model' => [
        'required',
        Rule::unique('foreign_key_mappers')->ignore($keyIgnore)
      ]
    ], ForeignKeyMapperCommonValidator::getCommonRules());
  }
}
