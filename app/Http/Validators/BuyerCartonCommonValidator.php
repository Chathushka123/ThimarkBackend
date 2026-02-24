<?php

namespace App\Http\Validators;

class BuyerCartonCommonValidator
{
  public static function getCommonRules()
  {
    return [ 
         'buyer_id' => ['numeric','required','exists:buyers,id'], 
         'carton_id' => ['numeric','required','exists:cartons,id']
   

    ];
  }
}



