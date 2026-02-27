<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Repositories\UomRepository;
use Illuminate\Http\Request;

class UomController extends Controller
{
    private $repo;

    public function __construct()
    {
        $this->repo = new UomRepository();
    }

    public function getUoms(Request $request)
    {
        return $this->repo->getUoms();
    }

    public function createAndUpdateUom(Request $request)
    {
        return $this->repo->createAndUpdateUom($request);
    }

    public function deleteUom(Request $request)
    {
        return $this->repo->deleteUom($request);
    }
}
