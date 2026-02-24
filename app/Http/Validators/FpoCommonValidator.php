<?php

namespace App\Http\Validators;

class FpoCommonValidator
{
  public static function getCommonRules()
  {
    return [
      'qty_json' => ['json'],
      'qty_json_order' => 'required',
      'combine_order_id' => 'nullable|exists:combine_orders,id'
    ];
  }
}
