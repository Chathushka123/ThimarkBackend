<?php

namespace App\Http\Validators;

class FrRecordCommonValidator
{
  public static function getCommonRules()
  {
    return [
 
         'run_date' => ['required'],   
         'team_id' => ['numeric','required','exists:teams,id',],   
         'total_planned_target' => ['numeric'],   
         'planned_efficiency' => ['numeric'],   
         'planned_sah' => ['numeric'],   
         'smv' => ['numeric']
   

   

    ];
  }
}



