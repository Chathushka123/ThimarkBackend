<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Exceptions\GeneralException;

/**
 * Double-submit CSRF check for the refresh_token cookie flow: the
 * csrf_refresh_token cookie is readable by JS, so a forged cross-site
 * request can't reproduce it in the X-CSRF-TOKEN header even though the
 * browser auto-attaches the cookie itself.
 */
class VerifyCsrfCookie
{
    public function handle(Request $request, Closure $next)
    {
        $headerValue = $request->header(config('refresh_token.csrf_header_name'));

        if (empty($headerValue)) {
            throw new GeneralException('CSRF token mismatch');
        }

        // Parse the raw Cookie header to collect ALL values for this cookie name.
        // PHP's $_COOKIE keeps only the first occurrence when the browser sends
        // duplicates (e.g. stale cookie on /api/v1 + current cookie on /), so
        // $request->cookie() alone cannot be trusted when duplicates exist.
        $cookieName = config('refresh_token.csrf_cookie_name');
        $cookieValues = $this->allCookieValues($request, $cookieName);

        foreach ($cookieValues as $value) {
            if (hash_equals($value, $headerValue)) {
                return $next($request);
            }
        }

        throw new GeneralException('CSRF token mismatch');
    }

    private function allCookieValues(Request $request, string $name): array
    {
        $values = [];
        foreach (explode(';', $request->header('Cookie', '')) as $part) {
            [$k, $v] = array_pad(explode('=', trim($part), 2), 2, '');
            if (trim($k) === $name && $v !== '') {
                $values[] = trim($v);
            }
        }
        return $values;
    }
}
