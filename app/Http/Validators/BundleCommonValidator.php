<?php

namespace App\Http\Validators;

class BundleCommonValidator
{
  public static function getCommonRules()
  {
    return [
      'size' => 'required',
      'quantity' => 'required|numeric',
      'fppo_id' => 'sometimes|exists:fppos,id'
    ];
  }
}
