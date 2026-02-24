<?php
namespace App\Http\Validators;
use App\Http\Validators\ShiftDetailCommonValidator;

class FrRecordCreateValidator
{
  public static function getCreateRules()
  {
    return array_merge([], FrRecordCommonValidator::getCommonRules());
  }
}
