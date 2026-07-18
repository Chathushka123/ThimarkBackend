<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Str;
use App\Http\Repositories\RefreshTokenRepository;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;

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
        if (request('password') == "" && isset($request->common_user)) {
            $user = DB::table('users')
                ->select('*')
                ->where('email', request('email'))
                ->first();

            $decrypted = Crypt::decrypt($request->common_user);

            if (Auth::attempt(['email' => $user->email, 'password' => $decrypted])) {
                return $this->issueTokenResponse(Auth::user());
            }
            throw new \App\Exceptions\GeneralException("You have entered an invalid Email or Password");
        }

        if (Auth::attempt(['email' => request('email'), 'password' => request('password')])) {
            return $this->issueTokenResponse(Auth::user());
        }
        throw new \App\Exceptions\GeneralException("You have entered an invalid Email or Password");
    }

    /**
     * Exchange the refresh_token cookie for a new access/refresh token pair.
     * Rotates the refresh token: the old one is revoked immediately and a
     * new refresh_token + csrf_refresh_token cookie pair is issued.
     */
    public function refreshToken(Request $request)
    {
        $refreshToken = $request->cookie(config('refresh_token.cookie_name'));
        if (empty($refreshToken)) {
            throw new \App\Exceptions\GeneralException('Refresh token cookie is missing');
        }

        [$userId, $newRefreshToken] = RefreshTokenRepository::rotate($refreshToken);
        $user = User::findOrFail($userId);

        return $this->issueTokenResponse($user, $newRefreshToken);
    }

    private function issueTokenResponse(User $user, ?string $refreshToken = null): JsonResponse
    {
        if (!$user->is_active) {
            throw new \App\Exceptions\GeneralException("Cannot Login, not an Active User");
        }

        $accessToken = JWTAuth::fromUser($user);
        $refreshToken = $refreshToken ?? RefreshTokenRepository::issue($user->id);
        $user->load('role');

        $response = response()->json([
            'user' => $user,
            'roles' => $user->role ? [$user->role] : [],
            'access_token' => $accessToken,
            'token_type' => 'Bearer',
            'expires_in' => config('jwt.ttl') * 60,
        ]);

        return $this->attachRefreshCookies($response, $refreshToken);
    }

    private function attachRefreshCookies(JsonResponse $response, string $refreshToken): JsonResponse
    {
        $ttlMinutes = config('refresh_token.ttl');
        $path = config('refresh_token.cookie_path');
        $secure = config('refresh_token.secure');
        $sameSite = config('refresh_token.same_site');

        return $response
            ->cookie(
                config('refresh_token.cookie_name'),
                $refreshToken,
                $ttlMinutes,
                $path,
                null,
                $secure,
                true, // httpOnly
                false,
                $sameSite
            )
            ->cookie(
                config('refresh_token.csrf_cookie_name'),
                Str::random(40),
                $ttlMinutes,
                '/', // must be '/' so document.cookie is readable on any frontend page path
                null,
                $secure,
                false, // readable by JS for the double-submit header
                false,
                $sameSite
            );
    }

    /**
     * Logout user (blacklist the access token, revoke the refresh token,
     * clear the refresh_token/csrf_refresh_token cookies)
     *
     * @return [string] message
     */
    public function logout(Request $request)
    {
        JWTAuth::invalidate(JWTAuth::getToken());

        $refreshToken = $request->cookie(config('refresh_token.cookie_name'));
        if (!empty($refreshToken)) {
            RefreshTokenRepository::revoke($refreshToken);
        }

        $refreshPath = config('refresh_token.cookie_path');
        $csrfPath = '/'; // csrf cookie is set at '/' so JS can read it on any page

        return response()->json(['message' => 'Successfully logged out'])
            ->withCookie(Cookie::forget(config('refresh_token.cookie_name'), $refreshPath))
            ->withCookie(Cookie::forget(config('refresh_token.csrf_cookie_name'), $csrfPath))
            // also clear any stale csrf cookie that may have been set on the old /api/v1 path
            ->withCookie(Cookie::forget(config('refresh_token.csrf_cookie_name'), $refreshPath));
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
