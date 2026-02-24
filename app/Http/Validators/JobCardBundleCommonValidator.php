<?php

namespace App\Http\Validators;

class JobCardBundleCommonValidator
{
  public static function getCommonRules()
  {
    return [
      'job_card_id' => ['required', 'exists:job_cards,id'],
      'bundle_id' => ['required', 'exists:bundles,id'],
      'original_quantity' => ['required', 'numeric'],
      'resized_quantity' => ['nullable', 'sometimes', 'numeric']
    ];
  }
}
