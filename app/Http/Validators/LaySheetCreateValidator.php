<?php

namespace App\Http\Validators;

use App\Http\Validators\LaySheetCommonValidator;

class LaySheetCreateValidator
{
  public static function getCreateRules()
  {
    return array_merge([
      'sheet_no' => 'required',
      'combine_order_id' => 'required|exists:combine_orders,id',
      'fpo_fabric_id' => 'required|exists:fpo_fabrics,id',
    ], LaySheetCommonValidator::getCommonRules());
  }
}
