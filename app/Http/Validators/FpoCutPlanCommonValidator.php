<?php

namespace App\Http\Validators;

use Illuminate\Validation\Rule;

class FpoCutPlanCommonValidator
{
  public static function getCommonRules()
  {
    return [
      'line_no' => ['required'],
      'qty_json' => ['required', 'json'],
      'cut_plan_id' => ['required', 'exists:cut_plans,id'],
      'fppo_id' => ['nullable', 'exists:fppos,id'],
      'fpo_id' => ['required', 'exists:fpos,id']
    ];
  }
}
