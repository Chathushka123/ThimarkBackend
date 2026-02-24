<?php

namespace App\Http\Validators;

class PackingSocConsumptionCommonValidator
{
  public static function getCommonRules()
  {
    return [
 
         'packing_list_detail_id' => ['numeric','sometimes','required','exists:packing_list_details,id'],
   
         'packing_list_soc_id' => ['numeric','sometimes','required','exists:packing_list_soc,id'],
   
         'qty_json' => ['required']
   
    ];
  }
}



