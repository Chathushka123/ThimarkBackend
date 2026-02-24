<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Carbon\Carbon;
use App\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

class AuthController extends Controller
{
    public $successStatus = 200;

    public function register(Request $request)
    {
        $validator = Validator::make(
            $request->all(),
            [
                'name' => 'required',
                'email' => 'required|email',
                'password' => 'required',
            ]
        );
        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 401);
        }
        $input = $request->all();
        $input['password'] = bcrypt($input['password']);
        $user = User::create($input);
        return response()->json([
            'message' => 'User was successfully created!'
        ], 201);
    }

    public function login(Request $request)
    {
        if( request('password') == "" && isset($request->common_user)){
            $user = DB::table('users')
            ->select('*')            
            //->where('users.common_user', request('common_user'))
            ->where('email', request('email'))
            ->first();

            $decrypted = Crypt::decrypt($request->common_user);
            
            if (Auth::attempt(['email' => $user->email, 'password' => $decrypted])) {
                $user = Auth::user();
                if ($user->is_active) {
                    $tokenResult = $user->createToken('Personal Access Token');
                    $token = $tokenResult->token;
                    if ($request->remember_me)
                        $token->expires_at = Carbon::now()->addWeeks(1);
                    $token->save();
                    return response()->json([
                        'user' => $user,
                        'access_token' => $tokenResult->accessToken,
                        'token_type' => 'Bearer',
                        'expires_at' => Carbon::parse(
                            $tokenResult->token->expires_at
                        )->toDateTimeString()
                    ]);
                } else {
                    //return response()->json(['error' => 'Unauthorized'], 401);
                    throw new \App\Exceptions\GeneralException("Cannot Login, not an Active User");
                }
            } else {
                throw new \App\Exceptions\GeneralException("You have entered an invalid Email or Password");
            }

        }else{

            if (Auth::attempt(['email' => request('email'), 'password' => request('password')])) {
                $user = Auth::user();
                if ($user->is_active) {
                    $tokenResult = $user->createToken('Personal Access Token');
                    $token = $tokenResult->token;
                    if ($request->remember_me)
                        $token->expires_at = Carbon::now()->addWeeks(1);
                    $token->save();
                    return response()->json([
                        'user' => $user,
                        'access_token' => $tokenResult->accessToken,
                        'token_type' => 'Bearer',
                        'expires_at' => Carbon::parse(
                            $tokenResult->token->expires_at
                        )->toDateTimeString()
                    ]);
                } else {
                    //return response()->json(['error' => 'Unauthorized'], 401);
                    throw new \App\Exceptions\GeneralException("Cannot Login, not an Active User");
                }
            } else {
                throw new \App\Exceptions\GeneralException("You have entered an invalid Email or Password");
            }
        }
    }

    /**
     * Logout user (Revoke the token)
     *
     * @return [string] message
     */
    public function logout(Request $request)
    {
        $request->user()->token()->revoke();
        return response()->json([
            'message' => 'Successfully logged out'
        ]);
    }

    /**
     * Get the authenticated User
     *
     * @return [json] user object
     */
    public function user(Request $request)
    {
        return response()->json($request->user());
    }

    
}
