<?php

declare(strict_types=1);

namespace App\Tests\Unit\Identity\Infrastructure\Security;

use App\Identity\Infrastructure\Security\AccessTokenCookie;
use App\Identity\Infrastructure\Security\RedirectRejectedWebTokenToLogin;
use Lexik\Bundle\JWTAuthenticationBundle\Event\JWTInvalidEvent;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Bundle\SecurityBundle\Security\FirewallConfig;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;

#[CoversClass(RedirectRejectedWebTokenToLogin::class)]
final class RedirectRejectedWebTokenToLoginTest extends TestCase
{
    public function testOnTheWebFirewallItRedirectsToLoginAndDropsTheCookie(): void
    {
        $event = $this->rejectedTokenEvent(Request::create('/'));

        $this->listener(firewall: 'main')($event);

        $response = $event->getResponse();
        self::assertInstanceOf(RedirectResponse::class, $response);
        self::assertSame('/login', $response->getTargetUrl());
        $cookies = $response->headers->getCookies();
        self::assertCount(1, $cookies);
        self::assertSame(AccessTokenCookie::NAME, $cookies[0]->getName());
        self::assertTrue($cookies[0]->isCleared());
    }

    public function testOnTheApiFirewallItKeepsTheJson401(): void
    {
        $event = $this->rejectedTokenEvent(Request::create('/api/v1/me'));
        $original = $event->getResponse();

        $this->listener(firewall: 'api')($event);

        self::assertSame($original, $event->getResponse());
    }

    public function testWithoutARequestItDoesNothing(): void
    {
        $event = new JWTInvalidEvent(new AuthenticationException(), $original = new JsonResponse(status: 401));

        $this->listener(firewall: 'main')($event);

        self::assertSame($original, $event->getResponse());
    }

    private function rejectedTokenEvent(Request $request): JWTInvalidEvent
    {
        return new JWTInvalidEvent(new AuthenticationException(), new JsonResponse(status: 401), $request);
    }

    private function listener(string $firewall): RedirectRejectedWebTokenToLogin
    {
        $security = $this->createStub(Security::class);
        $security->method('getFirewallConfig')->willReturn(new FirewallConfig($firewall, 'security.user_checker', stateless: true));
        $urlGenerator = $this->createStub(UrlGeneratorInterface::class);
        $urlGenerator->method('generate')->willReturn('/login');

        return new RedirectRejectedWebTokenToLogin($security, $urlGenerator);
    }
}
