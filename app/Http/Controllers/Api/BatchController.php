<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Repositories\BatchRepository;
use Illuminate\Http\Request;

class BatchController extends Controller
{
    private $repo;

    public function __construct()
    {
        $this->repo = new BatchRepository();
    }

    public function getBatches(Request $request)
    {
        return $this->repo->getBatches();
    }

    public function createAndUpdateBatch(Request $request)
    {
        return $this->repo->createAndUpdateBatch($request);
    }

    public function deleteBatch(Request $request)
    {
        return $this->repo->deleteBatch($request);
    }
}
