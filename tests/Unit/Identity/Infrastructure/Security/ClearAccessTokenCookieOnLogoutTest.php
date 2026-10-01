<?php

declare(strict_types=1);

namespace App\Tests\Unit\Identity\Infrastructure\Security;

use App\Identity\Infrastructure\Security\AccessTokenCookie;
use App\Identity\Infrastructure\Security\ClearAccessTokenCookieOnLogout;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Http\Event\LogoutEvent;

#[CoversClass(ClearAccessTokenCookieOnLogout::class)]
final class ClearAccessTokenCookieOnLogoutTest extends TestCase
{
    public function testItExpiresTheTokenCookieOnTheLogoutResponse(): void
    {
        $event = new LogoutEvent(Request::create('https://example.com/logout', 'POST'), null);
        $event->setResponse(new RedirectResponse('/login'));

        (new ClearAccessTokenCookieOnLogout())($event);

        $cookies = $event->getResponse()?->headers->getCookies() ?? [];
        self::assertCount(1, $cookies);
        self::assertSame(AccessTokenCookie::NAME, $cookies[0]->getName());
        self::assertTrue($cookies[0]->isCleared());
        self::assertTrue($cookies[0]->isSecure());
    }

    public function testWithoutAResponseThereIsNothingToClear(): void
    {
        $event = new LogoutEvent(Request::create('/logout', 'POST'), null);

        (new ClearAccessTokenCookieOnLogout())($event);

        self::assertNull($event->getResponse());
    }
}
