<?php

declare(strict_types=1);

namespace App\Identity\Infrastructure\Security;

use App\Identity\Domain\AccessToken;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Request;

/**
 * The browser carries the JWT in an HttpOnly cookie, so page JavaScript can never read it.
 */
final class AccessTokenCookie
{
    // Must match lexik_jwt_authentication.token_extractors.cookie.name
    public const string NAME = 'BEARER';

    public static function for(AccessToken $token, Request $request): Cookie
    {
        return Cookie::create(self::NAME)
            ->withValue($token->value)
            ->withExpires($token->expiresAt)
            ->withPath('/')
            ->withSecure($request->isSecure())
            ->withHttpOnly()
            ->withSameSite(Cookie::SAMESITE_LAX);
    }

    public static function clear(Request $request): Cookie
    {
        return Cookie::create(self::NAME)
            ->withExpires(1)
            ->withPath('/')
            ->withSecure($request->isSecure())
            ->withHttpOnly()
            ->withSameSite(Cookie::SAMESITE_LAX);
    }
}
