<?php

namespace App\Http\Validators;

use Illuminate\Validation\Rule;

class BundleBinCommonValidator
{
  public static function getCommonRules()
  {
    return [
      'created_date' => ['required', 'date'],
      'record_type' => ['required'],
      'size' => ['required'],
      'quantity' => ['required', 'numeric'],
      'utilized' => ['nullable', 'bool'],
      'qc_reject_id' => ['nullable', 'exists:qc_rejects,id'],
      'job_card_bundle_id' => ['nullable', 'exists:job_card_bundles,id'],
      'created_by_id' => ['required', 'exists:users,id'],
      'bundle_id' => ['nullable', 'exists:bundles,id']
    ];
  }
}
