<?php

namespace App\Http\Validators;

class TeamCommonValidator
{
  public static function getCommonRules()
  {
    return [
      'team_name' => ['required'],
      'no_of_operators' => ['nullable', 'integer', 'min:0'],
      'operation_id' => ['nullable', 'integer', 'exists:operation_masters,id']
    ];
  }
}
