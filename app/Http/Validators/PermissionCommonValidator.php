<?php

namespace App\Http\Validators;

class PermissionCommonValidator
{
  public static function getCommonRules()
  {
    return [
 
         'screen_id' => ['numeric','sometimes','required','exists:screens,id'],
  
         'role_id' => ['numeric','sometimes','required','exists:roles,id'],  

    ];
  }
}



