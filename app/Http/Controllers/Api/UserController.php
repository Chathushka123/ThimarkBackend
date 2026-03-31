<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\User;
use App\Http\Resources\UserResource;
use App\Http\Repositories\UserRepository;
use App\Http\Repositories\Utilities;
use Illuminate\Http\Request;
use PDF;

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

    public function printStickers($id)
    {
        $users = User::where('id', '=', $id)->get();
        $pdf = PDF::loadView('print.users_stickers', ['users' => $users]);
        $pdf->setPaper('A4', 'portrait');
        return $pdf->stream('users_stickers_' . date('Y_m_d_H_i_s') . '.pdf');
    }
}
