<?php

namespace App\Http\Validators;

use Illuminate\Validation\Rule;

class DowntimeDefinitionCommonValidator
{
  public static function getCommonRules()
  {
    return [
      'category' => ['required'],
      'description' => ['required']
    ];
  }
}
