<?php

declare(strict_types=1);

namespace App\Identity\Infrastructure\Security;

use Lexik\Bundle\JWTAuthenticationBundle\Event\JWTCreatedEvent;
use Lexik\Bundle\JWTAuthenticationBundle\Event\JWTDecodedEvent;
use Lexik\Bundle\JWTAuthenticationBundle\Events;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

/**
 * Only tokens issued by this portal are accepted. Lexik already rejects any token not signed with our
 * key; on top of that every token we sign carries the portal as "iss", and a decoded token with
 * another (or no) issuer is marked invalid, so Lexik answers it as an invalid token. That covers
 * tokens signed with a key shared with another application, and tokens issued before this check.
 */
#[AsEventListener(event: Events::JWT_CREATED, method: 'stampIssuer')]
#[AsEventListener(event: Events::JWT_DECODED, method: 'rejectForeignIssuer')]
final class PortalIssuedAccessToken
{
    public const string CLAIM = 'iss';

    public function __construct(
        #[Autowire(env: 'JWT_ISSUER')]
        private readonly string $issuer,
    ) {
    }

    public function stampIssuer(JWTCreatedEvent $event): void
    {
        $event->setData([self::CLAIM => $this->issuer] + $event->getData());
    }

    public function rejectForeignIssuer(JWTDecodedEvent $event): void
    {
        if ($this->issuer !== ($event->getPayload()[self::CLAIM] ?? null)) {
            $event->markAsInvalid();
        }
    }
}
