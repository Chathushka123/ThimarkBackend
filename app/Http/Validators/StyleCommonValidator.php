<?php

namespace App\Http\Validators;

use Illuminate\Validation\Rule;

class StyleCommonValidator
{
  public static function getCommonRules()
  {
    return [
      'description' => [
        'required'
      ],
      'size_fit' => ['required', 'json'],
      'size_fit_json' => ['required', 'json'],
      // 'routing_id' => ['required', 'exists:routings,id']
    ];
  }
}
