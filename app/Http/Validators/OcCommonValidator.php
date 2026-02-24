<?php

namespace App\Http\Validators;

class OcCommonValidator
{
  public static function getCommonRules()
  {
    return [
      'buyer_id' => 'required|exists:buyers,id',
      'qty_json' => 'nullable|json',
      // 'style_id' => 'required|exists:styles,id',
      // 'pack_color' => 'required'
    ];
  }
}
