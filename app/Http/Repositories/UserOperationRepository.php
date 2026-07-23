<?php

namespace App\Http\Repositories;

use App\Models\UserOperation;
use Exception;

/**
 * Backs the generic masterDetails endpoint for the UserOperation pivot
 * table (assigning a user to the operations they may scan for on the
 * Production WIP Scanning screen). Only create/delete are used — the
 * frontend always replaces a user's assignments wholesale rather than
 * issuing updates to individual rows.
 */
class UserOperationRepository
{
  public static function createRec(array $rec)
  {
    try {
      $model = UserOperation::create($rec);
    } catch (Exception $e) {
      throw new \App\Exceptions\GeneralException($e->getMessage());
    }
    return $model;
  }

  public static function deleteRecs(array $recs)
  {
    UserOperation::destroy($recs);
  }
}
