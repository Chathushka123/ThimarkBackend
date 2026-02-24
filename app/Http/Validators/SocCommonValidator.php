<?php

namespace App\Http\Validators;

class SocCommonValidator
{
  public static function getCommonRules() {
    return [
      'qty_json' => 'required|json',
      'qty_json_order' => 'required',
      'style_id' => 'required|exists:styles,id',
      'buyer_id' => 'required|exists:buyers,id',
      'pack_color' => 'required',
      'garment_color' => 'required',
      'customer_style_ref' => 'required'
    ];
  }
}