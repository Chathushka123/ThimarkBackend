<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\PackingSocConsumption;
use App\Http\Resources\PackingSocConsumptionResource;
use App\Http\Repositories\PackingSocConsumptionRepository;
use App\Http\Repositories\Utilities;
use Illuminate\Http\Request;

class PackingSocConsumptionController extends Controller
{
    private $repo;

    public function __construct()
    {
        $this->repo = new PackingSocConsumptionRepository();
    }


}
