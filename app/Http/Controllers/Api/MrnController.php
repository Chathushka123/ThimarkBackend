<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Repositories\MrnRepository;
use Illuminate\Http\Request;

class MrnController extends Controller
{
    private $repo;

    public function __construct()
    {
        $this->repo = new MrnRepository();
    }

    public function getMrns(Request $request)
    {
        return $this->repo->getMrns();
    }

    public function createAndUpdateMrn(Request $request)
    {
        return $this->repo->createAndUpdateMrn($request);
    }

    public function deleteMrn(Request $request)
    {
        return $this->repo->deleteMrn($request);
    }

    public function createAndUpdate(Request $request)
    {
        return $this->repo->createAndUpdate($request);
    }

    public function show($id)
    {
        return $this->repo->getMrnById($id);
    }

    public function finalize(Request $request)
    {
        return $this->repo->finalizeMrn($request);
    }

    public function reopen(Request $request)
    {
        return $this->repo->reopenMrn($request);
    }

    public function getSearchByMrn(Request $request)
    {
        return $this->repo->getSearchByMrn($request);
    }
}
