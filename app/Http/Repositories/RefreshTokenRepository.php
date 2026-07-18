<?php

namespace App\Http\Repositories;

use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Str;
use App\Exceptions\GeneralException;

class RefreshTokenRepository
{
    private static function key(string $token): string
    {
        return 'refresh_token:' . $token;
    }

    public static function issue(int $userId): string
    {
        $token = Str::random(64);
        Redis::setex(self::key($token), config('refresh_token.ttl') * 60, $userId);
        return $token;
    }

    public static function resolveUserId(string $token): int
    {
        $userId = Redis::get(self::key($token));
        if (is_null($userId)) {
            throw new GeneralException('Refresh token is invalid or has expired');
        }
        return (int) $userId;
    }

    public static function revoke(string $token): void
    {
        Redis::del(self::key($token));
    }

    public static function rotate(string $token): array
    {
        $userId = self::resolveUserId($token);
        self::revoke($token);
        return [$userId, self::issue($userId)];
    }
}
