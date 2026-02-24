<?php

namespace App\Http\Validators;

class TrimStoreCommonValidator
{
  public static function getCommonRules()
  {
    return [
      'job_card_id' => ['required', 'exists:job_cards,id'],
      'trim_status' => ['required']
    ];
  }
}
