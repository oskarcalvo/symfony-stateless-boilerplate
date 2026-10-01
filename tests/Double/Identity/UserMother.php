<?php

declare(strict_types=1);

namespace App\Tests\Double\Identity;

use App\Identity\Domain\Email;
use App\Identity\Domain\User;
use App\Identity\Domain\UserId;

final class UserMother
{
    public static function create(
        string $email = 'john@example.com',
        string $passwordHash = 'hashed:s3cret',
        ?UserId $id = null,
    ): User {
        return User::register($id ?? UserId::generate(), Email::fromString($email), $passwordHash, new \DateTimeImmutable('2026-01-01 10:00:00'));
    }
}
