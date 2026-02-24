<?php

namespace App\Http\FunctionalValidators;

use App\DailyShiftTeam;
use App\Fpo;
use App\Http\Repositories\SocRepository;
use App\Soc;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class DailyShiftTeamFunctionalValidator
{
  public static function EnforceUnique($rec, $model_id = null)
  {
    $query = DailyShiftTeam::where([
      'current_date' => $rec['current_date'],
      'team_id' => $rec['team_id'],
      'daily_shift_id' => $rec['daily_shift_id']
    ]);

    if (!is_null($model_id)) {
      $query->where('id', '<>', $model_id);
    }

    $model = $query->first();

    if (!is_null($model)) {
      throw new Exception("Record modified by another user. Please refresh and proceed.");
    }
  }
}
