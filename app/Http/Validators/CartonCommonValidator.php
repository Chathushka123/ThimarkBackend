<?php

namespace App\Http\Validators;

class CartonCommonValidator
{
  public static function getCommonRules()
  {
    return [
 
         'uom' => ['required'],
         'height' => ['numeric','required'],
         'width' => ['numeric','required'],
         'length' => ['numeric','required'],
         'weight' => ['numeric']
   ];
  }
}