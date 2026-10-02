<?php

declare(strict_types=1);

namespace App\Tests\Unit\Identity\RegisterUser\Application;

use App\Identity\Domain\AccessToken;
use App\Identity\RegisterUser\Application\SignedUpUser;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(SignedUpUser::class)]
final class SignedUpUserTest extends TestCase
{
    public function testItIsAReadOnlySnapshotWithPublicFields(): void
    {
        $token = new AccessToken('jwt', new \DateTimeImmutable('2026-01-01 11:00:00'));

        $signedUp = new SignedUpUser('id', 'john@example.com', 'John Doe', $token);

        self::assertSame('id', $signedUp->id);
        self::assertSame('john@example.com', $signedUp->email);
        self::assertSame('John Doe', $signedUp->name);
        self::assertSame($token, $signedUp->accessToken);
        self::assertTrue((new \ReflectionClass($signedUp))->isReadOnly());
    }
}
