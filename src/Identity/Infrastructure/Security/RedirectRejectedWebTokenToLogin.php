<?php

declare(strict_types=1);

namespace App\Identity\Infrastructure\Security;

use Lexik\Bundle\JWTAuthenticationBundle\Event\AuthenticationFailureEvent;
use Lexik\Bundle\JWTAuthenticationBundle\Events;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * On the web firewall, an expired or invalid token cookie sends the browser back to the login form
 * and deletes the cookie (otherwise every page, /login included, would answer with a JSON 401).
 * The API firewall keeps Lexik's JSON 401 responses.
 */
#[AsEventListener(event: Events::JWT_INVALID)]
#[AsEventListener(event: Events::JWT_EXPIRED)]
final class RedirectRejectedWebTokenToLogin
{
    private const string WEB_FIREWALL = 'main';

    public function __construct(
        private readonly Security $security,
        private readonly UrlGeneratorInterface $urlGenerator,
    ) {
    }

    public function __invoke(AuthenticationFailureEvent $event): void
    {
        $request = $event->getRequest();
        if (null === $request || self::WEB_FIREWALL !== $this->security->getFirewallConfig($request)?->getName()) {
            return;
        }

        $response = new RedirectResponse($this->urlGenerator->generate('identity_login'));
        $response->headers->setCookie(AccessTokenCookie::clear($request));

        $event->setResponse($response);
    }
}
