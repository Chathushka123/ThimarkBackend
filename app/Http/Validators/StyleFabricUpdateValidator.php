<?php

namespace App\Http\Validators;

use App\Http\Validators\StyleFabricCommonValidator;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

class StyleFabricUpdateValidator
{
  public static function getUpdateRules($keyIgnore, $rec)
  {
    return array_merge([
      //'fabric' => ['required', Rule::unique('style_fabrics')->ignore($keyIgnore) ],
      'fabric' => ['required', Rule::unique('style_fabrics')->where(function ($query) use ($rec) {
        return $query->where('style_id', $rec['style_id']);      
      })->ignore($keyIgnore)],

    ], StyleFabricCommonValidator::getCommonRules());
    
  }
}
