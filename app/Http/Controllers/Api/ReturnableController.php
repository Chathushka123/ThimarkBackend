<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Repositories\ReturnableRepository;
use Illuminate\Http\Request;

class ReturnableController extends Controller
{
    private $repo;

    public function __construct()
    {
        $this->repo = new ReturnableRepository();
    }

    public function getReturnables(Request $request)
    {
        return $this->repo->getReturnables();
    }

    public function createAndUpdateReturnable(Request $request)
    {
        return $this->repo->createAndUpdateReturnable($request);
    }

    public function deleteReturnable(Request $request)
    {
        return $this->repo->deleteReturnable($request);
    }
}
