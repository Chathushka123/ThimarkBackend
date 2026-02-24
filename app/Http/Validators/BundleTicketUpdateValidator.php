<?php

namespace App\Http\Validators;

use App\Http\Validators\BundleTicketCommonValidator;
use Illuminate\Validation\Rule;

class BundleTicketUpdateValidator
{
  public static function getUpdateRules($keyIgnore)
  {
    return array_merge([], BundleTicketCommonValidator::getCommonRules());
  }
}
