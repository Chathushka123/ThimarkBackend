<?php

namespace App\Http\Validators;

use App\Http\Validators\BuyerCommonValidator;
use Illuminate\Validation\Rule;

class BuyerUpdateValidator
{
  public static function getUpdateRules($keyIgnore)
  {
    return array_merge([
      'buyer_code' => [
        'required',
        Rule::unique('buyers')->ignore($keyIgnore)
      ]
    ], BuyerCommonValidator::getCommonRules());
  }
}
