<?php

declare(strict_types=1);

namespace App\Identity\LogIn\Infrastructure;

use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;

/**
 * Limits login attempts per email+IP and per IP (config/packages/rate_limiter.yaml).
 * Shared by every login channel so the web form and the API count against the same limits.
 */
final class LoginThrottle
{
    public function __construct(
        #[Target('login_local')]
        private readonly RateLimiterFactoryInterface $loginLocalLimiter,
        #[Target('login_global')]
        private readonly RateLimiterFactoryInterface $loginGlobalLimiter,
    ) {
    }

    /**
     * Records an attempt.
     *
     * @return \DateTimeImmutable|null when the caller may retry, or null if the attempt is allowed
     */
    public function attempt(string $email, ?string $clientIp): ?\DateTimeImmutable
    {
        $retryAfter = null;

        foreach ([$this->loginGlobalLimiter->create($clientIp), $this->loginLocalLimiter->create($this->localKey($email, $clientIp))] as $limiter) {
            $limit = $limiter->consume();
            if (!$limit->isAccepted()) {
                $retryAfter = max($retryAfter ?? $limit->getRetryAfter(), $limit->getRetryAfter());
            }
        }

        return $retryAfter;
    }

    /**
     * A successful login forgives the failed attempts on that account (not the per-IP count).
     */
    public function succeeded(string $email, ?string $clientIp): void
    {
        $this->loginLocalLimiter->create($this->localKey($email, $clientIp))->reset();
    }

    private function localKey(string $email, ?string $clientIp): string
    {
        return mb_strtolower(trim($email)).'-'.$clientIp;
    }
}
