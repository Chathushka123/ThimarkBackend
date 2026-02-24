<?php

namespace App\Exceptions;

use Exception;

class GeneralException extends Exception
{
    public function __construct($param)
    {
        parent::__construct(json_encode(["err" => [$param]]));
    }
}
