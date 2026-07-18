<?php

namespace App\Http\Validators;

class TestCreateValidator
{
  public static function getCreateRules()
  {
    return [
      'code' => ['required', 'unique:tests'],
      'name' => ['required'],
    ];
  }
}