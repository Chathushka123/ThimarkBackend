<?php

namespace App\Http\Validators;

use Illuminate\Validation\Rule;

class TeamCommonValidator
{
  public static function getCommonRules()
  {
    return [
      'description' => ['required'],
      'team_category_id' => ['required', 'exists:team_categories,id'],
      'supervisor_id' => ['nullable', 'sometimes', 'exists:employees,id'],
    ];
  }
}
