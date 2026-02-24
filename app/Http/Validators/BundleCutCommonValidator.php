<?php

namespace App\Http\Validators;

class BundleCutCommonValidator
{
  public static function getCommonRules()
  {
    return [
      'bundle_id' => ['required', 'exists:bundles,id'],
      'cut_update_id' => ['required', 'exists:cut_updates,id'],
      'quantity' => 'required|numeric'
    ];
  }
}
