<?php

namespace App\Traits;

trait StatusTrait
{
  public function getFsm()
  {
    return $this->fsm;
  }

  public function getStatus()
  {
    return $this->status;
  }

  public static function getInitialStatus()
  {
    return self::$fsm["_START_"][0];
  }

  public static function getNextStatuses($currentState)
  {
    return self::$fsm[$currentState];
  }

  public static function getStates()
  {
    return array_values(array_diff_key(array_keys(self::$fsm), [array_key_first(self::$fsm)]));
  }
}
