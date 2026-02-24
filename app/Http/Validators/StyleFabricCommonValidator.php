<?php

namespace App\Http\Validators;

use Illuminate\Validation\Rule;

class StyleFabricCommonValidator
{
  public static function getCommonRules()
  {
    return [
      //'avg_consumption' => [
       // 'required'
      //]
      // 'routing_id' => ['required', 'exists:routings,id']
    ];
  }
}
