<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\User;
use App\Http\Resources\UserResource;
use App\Http\Repositories\UserRepository;
use App\Http\Repositories\Utilities;
use Illuminate\Http\Request;

class UserController extends Controller
{
    private $repo;

    public function __construct()
    {
        $this->repo = new UserRepository();
    }

    public function changePassword(Request $request)
    {
        $user_id = $request->user_id;
        $password = $request->password;
        $updated_at = $request->updated_at;
        return $this->repo->changePassword($user_id, $password, $updated_at);
    }
}
