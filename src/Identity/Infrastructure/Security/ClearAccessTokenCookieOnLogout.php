<?php

declare(strict_types=1);

namespace App\Identity\Infrastructure\Security;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\Security\Http\Event\LogoutEvent;

/**
 * Without a session, logging out means forgetting the token: the browser drops the cookie
 * and Lexik's blocklist (blocklist_token) rejects the token if it is replayed.
 * Only for the "main" (browser) firewall: the API never sets nor clears cookies.
 */
#[AsEventListener(dispatcher: 'security.event_dispatcher.main')]
final class ClearAccessTokenCookieOnLogout
{
    public function __invoke(LogoutEvent $event): void
    {
        $event->getResponse()?->headers->setCookie(AccessTokenCookie::clear($event->getRequest()));
    }
}
