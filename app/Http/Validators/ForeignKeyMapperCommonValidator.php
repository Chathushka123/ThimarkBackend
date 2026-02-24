<?php

namespace App\Http\Validators;

class ForeignKeyMapperCommonValidator
{
  public static function getCommonRules()
  {
    return [
      'key_mapping' => 'required|json'
    ];
  }
}
