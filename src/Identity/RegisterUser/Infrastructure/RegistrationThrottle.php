<?php

declare(strict_types=1);

namespace App\Identity\RegisterUser\Infrastructure;

use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;

/**
 * Limits sign-ups per IP (config/packages/rate_limiter.yaml) so nobody can mass-create accounts
 * or burn CPU on password hashing. Shared by the web form and the API.
 */
final class RegistrationThrottle
{
    public function __construct(
        #[Target('registration')]
        private readonly RateLimiterFactoryInterface $registrationLimiter,
    ) {
    }

    /**
     * Records an attempt.
     *
     * @return \DateTimeImmutable|null when the caller may retry, or null if the attempt is allowed
     */
    public function attempt(?string $clientIp): ?\DateTimeImmutable
    {
        $limit = $this->registrationLimiter->create($clientIp)->consume();

        return $limit->isAccepted() ? null : $limit->getRetryAfter();
    }
}
