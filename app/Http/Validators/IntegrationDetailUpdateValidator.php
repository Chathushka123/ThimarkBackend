
<?php

namespace App\Http\Validators;

use App\Http\Validators\ShiftDetailCommonValidator;
use Illuminate\Validation\Rule;

class IntegrationDetailUpdateValidator
{
  public static function getUpdateRules($keyIgnore)
  {
    return array_merge([], IntegrationDetailCommonValidator::getCommonRules());
  }
}
