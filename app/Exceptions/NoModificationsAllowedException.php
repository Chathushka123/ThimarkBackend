<?php

namespace App\Exceptions;

use Exception;

class NoModificationsAllowedException extends Exception
{
    public function __construct($model, $state)
    {
        parent::__construct(json_encode(["err" => ["$model is in $state state. No modifications are allowed."]]));
    }
}
