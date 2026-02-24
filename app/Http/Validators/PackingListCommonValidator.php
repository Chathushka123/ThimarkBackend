<?php

namespace App\Http\Validators;

class PackingListCommonValidator
{
  public static function getCommonRules()
  {
    return [

      'packing_list_date' => ['required'],
      'packing_list_date' => ['required'],
      'packing_list_delivery_date' => ['required'],
      'volume_weight' => ['nullable', 'sometimes', 'numeric'],
      'cbm' =>  ['nullable', 'sometimes', 'numeric'],
      'parameter_type' => ['required'],
      'carton_number_format_id' => ['nullable', 'sometimes', 'exists:carton_number_formats,id'],
      'revision_no' =>  ['nullable', 'sometimes', 'numeric'],


    ];
  }
}
