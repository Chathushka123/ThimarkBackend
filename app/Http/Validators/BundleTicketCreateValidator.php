<?php

namespace App\Http\Validators;

use App\Http\Validators\BundleTicketCommonValidator;

class BundleTicketCreateValidator
{
  public static function getCreateRules()
  {
    return array_merge([], BundleTicketCommonValidator::getCommonRules());
  }
}
