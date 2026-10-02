<?php

declare(strict_types=1);

namespace App\Identity\Infrastructure\Security;

use Lexik\Bundle\JWTAuthenticationBundle\Event\AuthenticationFailureEvent;
use Lexik\Bundle\JWTAuthenticationBundle\Event\JWTExpiredEvent;
use Lexik\Bundle\JWTAuthenticationBundle\Events;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Twig\Environment;

/**
 * On the web firewall a rejected token cookie is always deleted, and then:
 * - expired: the browser goes back to the login form, as with any finished login;
 * - invalid (not issued by this portal, tampered, revoked, or its user no longer exists):
 *   access is denied with a 401 page.
 * The API firewall keeps Lexik's JSON 401 responses.
 */
#[AsEventListener(event: Events::JWT_INVALID)]
#[AsEventListener(event: Events::JWT_EXPIRED)]
final class RespondToRejectedWebToken
{
    private const string WEB_FIREWALL = 'main';
    public const string ACCESS_DENIED_TEMPLATE = 'identity/access_denied.html.twig';

    public function __construct(
        private readonly Security $security,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly Environment $twig,
    ) {
    }

    public function __invoke(AuthenticationFailureEvent $event): void
    {
        $request = $event->getRequest();
        if (null === $request || self::WEB_FIREWALL !== $this->security->getFirewallConfig($request)?->getName()) {
            return;
        }

        $response = $event instanceof JWTExpiredEvent
            ? new RedirectResponse($this->urlGenerator->generate('identity_login'))
            : new Response($this->twig->render(self::ACCESS_DENIED_TEMPLATE), Response::HTTP_UNAUTHORIZED);
        $response->headers->setCookie(AccessTokenCookie::clear($request));

        $event->setResponse($response);
    }
}
