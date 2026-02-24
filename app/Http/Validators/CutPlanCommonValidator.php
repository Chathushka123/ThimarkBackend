<?php

namespace App\Http\Validators;

class CutPlanCommonValidator
{
  public static function getCommonRules()
  {
    return [
      'cut_no' => 'required',
      'value_json' => 'json',
      'ratio_json' => 'json',
      'marker_name' => 'required',
      // 'main_fabric' => 'required',
      // // 'yrds' => 'required',
      // // 'inch' => 'required',
      // 'acc_width' => 'required',
      // 'qty_json_order' => 'required',
      // 'max_plies' => 'required|numeric',
       'combine_order_id' => 'required|exists:combine_orders,id',
      // 'style_fabric_id' => 'required|exists:style_fabrics,id'
    ];
  }
}
