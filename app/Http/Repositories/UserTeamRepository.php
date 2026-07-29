<?php

namespace App\Http\Repositories;

use App\Models\UserTeam;
use Exception;

class UserTeamRepository
{
  public static function createRec(array $rec)
  {
    try {
      $model = UserTeam::create($rec);
    } catch (Exception $e) {
      throw new \App\Exceptions\GeneralException($e->getMessage());
    }
    return $model;
  }

  public static function deleteRecs(array $recs)
  {
    UserTeam::destroy($recs);
  }
}
