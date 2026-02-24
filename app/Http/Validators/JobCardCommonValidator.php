<?php

namespace App\Http\Validators;

class JobCardCommonValidator
{
  public static function getCommonRules()
  {
    return [
      'fpo_id' => ['required', 'exists:fpos,id'],
      'team_id' => ['required', 'exists:teams,id'],
      'job_card_date' => ['required'],
      'trims_required' => ['sometimes', 'required', 'boolean']
    ];
  }
}
