<?php

declare(strict_types=1);

namespace App\Tests\Unit\Identity\Infrastructure\Security;

use App\Identity\Infrastructure\Security\AccessTokenCookie;
use App\Identity\Infrastructure\Security\RespondToRejectedWebToken;
use Lexik\Bundle\JWTAuthenticationBundle\Event\AuthenticationFailureEvent;
use Lexik\Bundle\JWTAuthenticationBundle\Event\JWTExpiredEvent;
use Lexik\Bundle\JWTAuthenticationBundle\Event\JWTInvalidEvent;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Bundle\SecurityBundle\Security\FirewallConfig;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Twig\Environment;
use Twig\Loader\ArrayLoader;

#[CoversClass(RespondToRejectedWebToken::class)]
final class RespondToRejectedWebTokenTest extends TestCase
{
    public function testOnTheWebFirewallAnExpiredTokenRedirectsToLoginAndDropsTheCookie(): void
    {
        $event = new JWTExpiredEvent(new AuthenticationException(), new JsonResponse(status: 401), Request::create('/'));

        $this->listener(firewall: 'main')($event);

        $response = $event->getResponse();
        self::assertInstanceOf(RedirectResponse::class, $response);
        self::assertSame('/login', $response->getTargetUrl());
        $this->assertTheCookieIsCleared($response);
    }

    public function testOnTheWebFirewallAnInvalidTokenIsDeniedWithA401PageAndDropsTheCookie(): void
    {
        $event = new JWTInvalidEvent(new AuthenticationException(), new JsonResponse(status: 401), Request::create('/user'));

        $this->listener(firewall: 'main')($event);

        $response = $event->getResponse();
        self::assertNotInstanceOf(JsonResponse::class, $response);
        self::assertSame(Response::HTTP_UNAUTHORIZED, $response->getStatusCode());
        self::assertSame('<h1>Acceso no permitido</h1>', $response->getContent());
        $this->assertTheCookieIsCleared($response);
    }

    public function testOnTheApiFirewallItKeepsTheJson401(): void
    {
        foreach ([JWTInvalidEvent::class, JWTExpiredEvent::class] as $eventClass) {
            /** @var AuthenticationFailureEvent $event */
            $event = new $eventClass(new AuthenticationException(), $original = new JsonResponse(status: 401), Request::create('/api/v1/me'));

            $this->listener(firewall: 'api')($event);

            self::assertSame($original, $event->getResponse());
        }
    }

    public function testWithoutARequestItDoesNothing(): void
    {
        $event = new JWTInvalidEvent(new AuthenticationException(), $original = new JsonResponse(status: 401));

        $this->listener(firewall: 'main')($event);

        self::assertSame($original, $event->getResponse());
    }

    private function assertTheCookieIsCleared(Response $response): void
    {
        $cookies = $response->headers->getCookies();
        self::assertCount(1, $cookies);
        self::assertSame(AccessTokenCookie::NAME, $cookies[0]->getName());
        self::assertTrue($cookies[0]->isCleared());
    }

    private function listener(string $firewall): RespondToRejectedWebToken
    {
        $security = $this->createStub(Security::class);
        $security->method('getFirewallConfig')->willReturn(new FirewallConfig($firewall, 'security.user_checker', stateless: true));
        $urlGenerator = $this->createStub(UrlGeneratorInterface::class);
        $urlGenerator->method('generate')->willReturn('/login');
        $twig = new Environment(new ArrayLoader([
            RespondToRejectedWebToken::ACCESS_DENIED_TEMPLATE => '<h1>Acceso no permitido</h1>',
        ]));

        return new RespondToRejectedWebToken($security, $urlGenerator, $twig);
    }
}
