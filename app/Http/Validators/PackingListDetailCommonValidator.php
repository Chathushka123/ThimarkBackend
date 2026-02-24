<?php

namespace App\Http\Validators;

class PackingListDetailCommonValidator
{
  public static function getCommonRules()
  {
    return [
 
         'carton_number' => ['required'],
          'qty_json' => ['required'],   
         'total' => ['numeric','required'],  
         'packing_list_id' => ['numeric','sometimes','required','exists:packing_lists,id'],
         'carton_packing_list_id' => ['numeric','sometimes','required','exists:carton_packing_list,id'],
   

    ];
  }
}



