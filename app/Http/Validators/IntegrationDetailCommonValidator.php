<?php

namespace App\Http\Validators;

class IntegrationDetailCommonValidator
{
  public static function getCommonRules()
  {
    return [ 
         'integration_log_id' => ['numeric','required','exists:integration_logs,id']
    ];
  }
}



