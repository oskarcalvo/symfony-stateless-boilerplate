<?php

declare(strict_types=1);

namespace App\Tests\Unit\Identity\RegisterUser\Infrastructure;

use App\Identity\RegisterUser\Infrastructure\RegistrationThrottle;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\RateLimiter\Storage\InMemoryStorage;

#[CoversClass(RegistrationThrottle::class)]
final class RegistrationThrottleTest extends TestCase
{
    public function testSignUpsFromOneIpAreLimited(): void
    {
        $throttle = $this->throttle(limit: 2);

        self::assertNull($throttle->attempt('10.0.0.1'));
        self::assertNull($throttle->attempt('10.0.0.1'));

        self::assertGreaterThan(new \DateTimeImmutable(), $throttle->attempt('10.0.0.1'));
    }

    public function testEachIpHasItsOwnCounter(): void
    {
        $throttle = $this->throttle(limit: 1);

        $throttle->attempt('10.0.0.1');

        self::assertNotNull($throttle->attempt('10.0.0.1'));
        self::assertNull($throttle->attempt('10.0.0.2'));
    }

    private function throttle(int $limit): RegistrationThrottle
    {
        return new RegistrationThrottle(
            new RateLimiterFactory(['id' => 'registration', 'policy' => 'sliding_window', 'limit' => $limit, 'interval' => '1 hour'], new InMemoryStorage()),
        );
    }
}
