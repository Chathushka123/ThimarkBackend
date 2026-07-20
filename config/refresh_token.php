<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Refresh Token TTL
    |--------------------------------------------------------------------------
    |
    | Lifetime, in minutes, of the opaque refresh token issued alongside the
    | JWT access token. Stored in Redis (see RefreshTokenRepository) so it
    | can be revoked on logout/rotation, unlike the stateless access token.
    |
    */

    'ttl' => (int) env('JWT_REFRESH_TOKEN_TTL', 10080),

    /*
    |--------------------------------------------------------------------------
    | Refresh Token Cookie
    |--------------------------------------------------------------------------
    |
    | The refresh token is delivered to the browser as an httpOnly cookie
    | (never in the JSON body) so XSS can't read it. A second, non-httpOnly
    | cookie carries a CSRF value the frontend must echo back as a header
    | on requests that read the refresh cookie (double-submit pattern) —
    | see VerifyCsrfCookie middleware.
    |
    */

    'cookie_name' => 'refresh_token',

    'csrf_cookie_name' => 'csrf_refresh_token',

    'csrf_header_name' => 'X-CSRF-TOKEN',

    'cookie_path' => '/api/v1',

    'secure' => (bool) env('REFRESH_COOKIE_SECURE', true),

    'same_site' => env('REFRESH_COOKIE_SAMESITE', 'lax'),

];
