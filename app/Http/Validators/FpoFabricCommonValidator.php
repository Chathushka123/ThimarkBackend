<?php

namespace App\Http\Validators;

use Illuminate\Validation\Rule;

class FpoFabricCommonValidator
{
  public static function getCommonRules()
  {
    return [
      'style_fabric_id' => ['required', 'exists:style_fabrics,id'],
      'fpo_id' => ['required', 'exists:fpos,id'],
      'avg_consumption' => 'required|numeric'
    ];
  }
}
