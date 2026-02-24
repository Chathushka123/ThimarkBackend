<?php

namespace App\Http\Validators;

class BundleTicketSecondaryCreateValidator
{
    public static function getCreateRules()
    {
        return array_merge([], BundleTicketSecondaryCommonValidator::getCommonRules());
    }
}
