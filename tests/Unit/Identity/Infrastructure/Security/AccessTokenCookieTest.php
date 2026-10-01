<?php

declare(strict_types=1);

namespace App\Tests\Unit\Identity\Infrastructure\Security;

use App\Identity\Domain\AccessToken;
use App\Identity\Infrastructure\Security\AccessTokenCookie;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Request;

#[CoversClass(AccessTokenCookie::class)]
final class AccessTokenCookieTest extends TestCase
{
    public function testItCarriesTheTokenOutOfReachOfJavascript(): void
    {
        $expiresAt = new \DateTimeImmutable('2030-01-01 00:00:00');

        $cookie = AccessTokenCookie::for(new AccessToken('a.b.c', $expiresAt), Request::create('https://example.com/login'));

        self::assertSame(AccessTokenCookie::NAME, $cookie->getName());
        self::assertSame('a.b.c', $cookie->getValue());
        self::assertSame($expiresAt->getTimestamp(), $cookie->getExpiresTime());
        self::assertSame('/', $cookie->getPath());
        self::assertTrue($cookie->isHttpOnly());
        self::assertTrue($cookie->isSecure());
        self::assertSame(Cookie::SAMESITE_LAX, $cookie->getSameSite());
    }

    public function testItIsOnlySecureOverHttps(): void
    {
        $cookie = AccessTokenCookie::for(new AccessToken('a.b.c', new \DateTimeImmutable('+1 hour')), Request::create('http://example.com/login'));

        self::assertFalse($cookie->isSecure());
    }

    public function testClearingExpiresTheCookieWithTheSameAttributes(): void
    {
        $cookie = AccessTokenCookie::clear(Request::create('https://example.com/'));

        self::assertSame(AccessTokenCookie::NAME, $cookie->getName());
        self::assertTrue($cookie->isCleared());
        self::assertSame('/', $cookie->getPath());
        self::assertTrue($cookie->isHttpOnly());
        self::assertTrue($cookie->isSecure());
    }
}
