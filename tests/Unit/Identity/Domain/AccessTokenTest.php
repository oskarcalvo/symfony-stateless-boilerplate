<?php

declare(strict_types=1);

namespace App\Tests\Unit\Identity\Domain;

use App\Identity\Domain\AccessToken;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(AccessToken::class)]
final class AccessTokenTest extends TestCase
{
    public function testItExposesTheTokenAndItsExpiration(): void
    {
        $expiresAt = new \DateTimeImmutable('2030-01-01 00:00:00');

        $token = new AccessToken('a.b.c', $expiresAt);

        self::assertSame('a.b.c', $token->value);
        self::assertSame($expiresAt, $token->expiresAt);
    }
}
