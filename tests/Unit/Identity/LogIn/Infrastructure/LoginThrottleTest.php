<?php

declare(strict_types=1);

namespace App\Tests\Unit\Identity\LogIn\Infrastructure;

use App\Identity\LogIn\Infrastructure\LoginThrottle;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\RateLimiter\Storage\InMemoryStorage;

#[CoversClass(LoginThrottle::class)]
final class LoginThrottleTest extends TestCase
{
    public function testAttemptsOnOneAccountAreLimited(): void
    {
        $throttle = $this->throttle(localLimit: 2, globalLimit: 100);

        self::assertNull($throttle->attempt('john@example.com', '10.0.0.1'));
        self::assertNull($throttle->attempt('john@example.com', '10.0.0.1'));

        self::assertGreaterThan(new \DateTimeImmutable(), $throttle->attempt('john@example.com', '10.0.0.1'));
    }

    public function testTheAccountKeyIgnoresEmailCaseAndSpaces(): void
    {
        $throttle = $this->throttle(localLimit: 1, globalLimit: 100);

        $throttle->attempt('john@example.com', '10.0.0.1');

        self::assertNotNull($throttle->attempt(' John@Example.com ', '10.0.0.1'));
    }

    public function testAttemptsFromOneIpAcrossAccountsAreLimited(): void
    {
        $throttle = $this->throttle(localLimit: 100, globalLimit: 2);

        $throttle->attempt('a@example.com', '10.0.0.1');
        $throttle->attempt('b@example.com', '10.0.0.1');

        self::assertNotNull($throttle->attempt('c@example.com', '10.0.0.1'));
        self::assertNull($throttle->attempt('c@example.com', '10.0.0.2'));
    }

    public function testASuccessfulLoginResetsOnlyTheAccountCounter(): void
    {
        $throttle = $this->throttle(localLimit: 2, globalLimit: 3);
        $throttle->attempt('john@example.com', '10.0.0.1');
        $throttle->attempt('john@example.com', '10.0.0.1');

        $throttle->succeeded('john@example.com', '10.0.0.1');

        self::assertNull($throttle->attempt('john@example.com', '10.0.0.1'));
        self::assertNotNull($throttle->attempt('john@example.com', '10.0.0.1'), 'The per-IP counter is not reset.');
    }

    private function throttle(int $localLimit, int $globalLimit): LoginThrottle
    {
        $storage = new InMemoryStorage();

        return new LoginThrottle(
            new RateLimiterFactory(['id' => 'login_local', 'policy' => 'sliding_window', 'limit' => $localLimit, 'interval' => '1 minute'], $storage),
            new RateLimiterFactory(['id' => 'login_global', 'policy' => 'sliding_window', 'limit' => $globalLimit, 'interval' => '1 minute'], $storage),
        );
    }
}
