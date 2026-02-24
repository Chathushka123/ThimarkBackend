<?php

namespace App\Http\Validators;

class CutUpdateCommonValidator
{
  public static function getCommonRules()
  {
    return [
      'qty_json' => 'json',
      'qty_json_order' => 'required',
      'fppo_id' => 'required|exists:fppos,id',
      'cut_plan_id' => 'required|exists:cut_plans,id',
      //'daily_shift_id' => 'required|exists:daily_shifts,id',
    ];
  }
}
