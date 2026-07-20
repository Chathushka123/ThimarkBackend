<?php

namespace App\Http\Validators;

use Illuminate\Validation\Rule;

class StyleFabricCommonValidator
{
  public static function getCommonRules()
  {
    return [
      'style_id' => ['required', 'exists:styles,id'],
      //'avg_consumption' => [
       // 'required'
      //]
      // 'routing_id' => ['required', 'exists:routings,id']
    ];
  }
}
