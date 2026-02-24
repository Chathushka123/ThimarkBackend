<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Permission;
use App\Http\Resources\PermissionResource;
use App\Http\Repositories\PermissionRepository;
use App\Http\Repositories\Utilities;
use Illuminate\Http\Request;

class PermissionController extends Controller
{
    private $repo;

    public function __construct()
    {
        $this->repo = new PermissionRepository();
    }


    public function isAuthorized(Request $request)
    {
        $screen = $request->screen;
        return $this->repo->isAuthorized($screen);
    }

    public function getPermissions(Request $request)
    {
       return $this->repo->getPermissions();
    }

    public function getNavigator(Request $request)
    {
        
        return $this->repo->getNavigator();
    }

    public function updatePermissions(Request $request)
    {
        
        return $this->repo->updatePermissions($request);
    }


}
