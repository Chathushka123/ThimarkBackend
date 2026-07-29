<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\User;
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

    /**
     * Look up a user's name/email by id - used by scan-a-user-id fields
     * (e.g. the Returnable screen's Requester field) to resolve and display
     * who the id belongs to.
     */
    public function getOne($id)
    {
        $user = User::select('id', 'name', 'email')->find($id);

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'User not found.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'User retrieved successfully.',
            'data' => $user,
        ]);
    }

    /**
     * List active employees (id/name/email only) for the Employee Stickers
     * screen's selectable grid.
     */
    public function activeList()
    {
        $users = User::where('is_active', 1)
            ->select('id', 'name', 'email')
            ->orderBy('name')
            ->get();

        return response()->json([
            'success' => true,
            'message' => 'Active users retrieved successfully.',
            'data' => $users,
        ]);
    }

    /**
     * Print employee stickers (QR + name/email, no id shown) for the given
     * comma-separated user ids - 3 per row, 18 per A4 page.
     */
    public function printEmployeeStickersByIds($ids)
    {
        $idArray = explode(',', $ids);
        $users = User::whereIn('id', $idArray)->where('is_active', 1)->orderBy('name')->get();
        $pdf = PDF::loadView('print.employee_stickers', ['users' => $users]);
        $pdf->setPaper('A4', 'portrait');
        return $pdf->stream('employee_stickers_' . date('Y_m_d_H_i_s') . '.pdf');
    }
}
