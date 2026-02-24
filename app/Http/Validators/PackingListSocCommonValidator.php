<?php
namespace App\Http\Validators;

class PackingListSocCommonValidator
{
  public static function getCommonRules()
  {
    return [
 
        'soc_id' => ['numeric','sometimes','required','exists:socs,id'],   
        'packing_list_id' => ['numeric','sometimes','required','exists:packing_lists,id'],   
        'quantity_json' => ['required'],     
        'pack_ratio' => ['sometimes','numeric'],
        'cmb' => ['numeric','sometimes'],
        'volume_weight' => ['numeric','sometimes'],
   

    ];
  }
}



