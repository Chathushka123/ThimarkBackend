<?php

namespace App\Http\Validators;

class OcColorCommonValidator
{
  public static function getCommonRules()
  {
    return [
      'oc_id' => 'required|exists:ocs,id',
      'garment_color' => 'required',
      'qty_json' => 'nullable|json'
    ];
  }
}
