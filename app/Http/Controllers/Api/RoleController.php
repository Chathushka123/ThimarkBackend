<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Team;
use App\Http\Resources\TeamResource;
use App\Http\Repositories\TeamRepository;
use App\Http\Repositories\Utilities;
use Illuminate\Http\Request;

class RoleController extends Controller
{
    private $repo;

    public function __construct()
    {
        $this->repo = new RoleRepository();
    }


}
