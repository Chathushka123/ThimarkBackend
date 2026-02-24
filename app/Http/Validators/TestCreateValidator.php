<?php

namespace App\Http\Validators;

class TestCreateValidator
{
  public static function getCreateRules()
  {
    return array_merge([
      'code' => 'required|unique:tests'
    ]);
  }
}