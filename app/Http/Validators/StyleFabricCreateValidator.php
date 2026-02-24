<?php

namespace App\Http\Validators;

use App\Http\Validators\StyleFabricCommonValidator;
use Illuminate\Validation\Rule;

class StyleFabricCreateValidator
{
  public static function getCreateRules($rec)
  {
    return array_merge([
      //'fabric' => ['required', Rule::unique('style_fabrics') ],
      'fabric' => ['required', Rule::unique('style_fabrics')->where(function ($query) use ($rec) {
        return $query->where('style_id', $rec['style_id']);      
      })],
    ], StyleFabricCommonValidator::getCommonRules());
  }
}
