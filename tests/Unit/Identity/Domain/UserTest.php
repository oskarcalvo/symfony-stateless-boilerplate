<?php

declare(strict_types=1);

namespace App\Tests\Unit\Identity\Domain;

use App\Identity\Domain\Email;
use App\Identity\Domain\User;
use App\Identity\Domain\UserId;
use App\Identity\Domain\UserName;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(User::class)]
final class UserTest extends TestCase
{
    public function testRegisteringAUserKeepsItsData(): void
    {
        $id = UserId::generate();
        $registeredAt = new \DateTimeImmutable('2026-01-01 10:00:00');

        $user = User::register($id, Email::fromString('john@example.com'), UserName::fromString('John Doe'), 'hash', $registeredAt);

        self::assertTrue($user->id()->equals($id));
        self::assertSame('john@example.com', $user->email()->value);
        self::assertSame('John Doe', $user->name()->value);
        self::assertSame('hash', $user->passwordHash());
        self::assertSame($registeredAt, $user->registeredAt());
    }

    public function testEveryUserHasTheDefaultRole(): void
    {
        $user = User::register(UserId::generate(), Email::fromString('john@example.com'), UserName::fromString('John Doe'), 'hash', new \DateTimeImmutable());

        self::assertSame([User::DEFAULT_ROLE], $user->roles());
    }

    public function testGrantingARoleIsIdempotent(): void
    {
        $user = User::register(UserId::generate(), Email::fromString('john@example.com'), UserName::fromString('John Doe'), 'hash', new \DateTimeImmutable());

        $user->grantRole('ROLE_ADMIN');
        $user->grantRole('ROLE_ADMIN');
        $user->grantRole(User::DEFAULT_ROLE);

        self::assertSame(['ROLE_ADMIN', User::DEFAULT_ROLE], $user->roles());
    }

    public function testThePasswordHashCanBeReplaced(): void
    {
        $user = User::register(UserId::generate(), Email::fromString('john@example.com'), UserName::fromString('John Doe'), 'old', new \DateTimeImmutable());

        $user->changePasswordHash('new');

        self::assertSame('new', $user->passwordHash());
    }
}
