<?php

declare(strict_types=1);

namespace App\Tests\Double\Identity;

use App\Identity\Domain\Email;
use App\Identity\Domain\User;
use App\Identity\Domain\UserId;
use App\Identity\Domain\UserName;

final class UserMother
{
    public static function create(
        string $email = 'john@example.com',
        string $passwordHash = 'hashed:s3cret',
        ?UserId $id = null,
        string $name = 'John Doe',
    ): User {
        return User::register(
            $id ?? UserId::generate(),
            Email::fromString($email),
            UserName::fromString($name),
            $passwordHash,
            new \DateTimeImmutable('2026-01-01 10:00:00'),
        );
    }
}
