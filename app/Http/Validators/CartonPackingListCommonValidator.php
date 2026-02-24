<?php

namespace App\Http\Validators;

class CartonPackingListCommonValidator
{
  public static function getCommonRules()
  {
    return [

      'packing_list_id' => ['numeric', 'sometimes', 'required', 'exists:packing_lists,id'],
      'carton_id' => ['numeric', 'sometimes', 'required', 'exists:cartons,id'],
      'ratio_json' => ['required'],
      'no_of_cartons' => ['required'],
      'total_quantity' => ['numeric', 'required'],
      'pcs_per_carton' => ['numeric', 'required'],
      'weight_per_piece' => ['numeric'],
      'calculated_no_of_cartons' => ['numeric']

    ];
  }
}
