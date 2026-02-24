<?php

namespace App\Exceptions;

use Exception;

class ConcurrencyCheckFailedException extends Exception
{
    public function __construct($model = null)
    {
        parent::__construct(json_encode(["err" => ["$model has been modified already. Please refresh the $model and try again."]]));
    }
}
