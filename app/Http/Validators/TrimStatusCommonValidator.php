<?php

namespace App\Http\Validators;

class TrimStatusCommonValidator
{
  public static function getCommonRules()
  {
    return [
      'status' => ['required', 'unique:trim_statuses']
    ];
  }
}
