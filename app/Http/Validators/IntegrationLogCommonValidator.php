<?php

namespace App\Http\Validators;

class IntegrationLogCommonValidator
{
  public static function getCommonRules()
  {
    return [
          'process_name' => ['required'],   
         'file_name' => ['required'],   
         'status' => ['required'],   
    ];
  }
}



