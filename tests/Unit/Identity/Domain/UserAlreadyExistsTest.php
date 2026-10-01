<?php

declare(strict_types=1);

namespace App\Tests\Unit\Identity\Domain;

use App\Identity\Domain\Email;
use App\Identity\Domain\UserAlreadyExists;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(UserAlreadyExists::class)]
final class UserAlreadyExistsTest extends TestCase
{
    public function testItNamesTheDuplicatedEmail(): void
    {
        $exception = UserAlreadyExists::withEmail(Email::fromString('john@example.com'));

        self::assertInstanceOf(\DomainException::class, $exception);
        self::assertSame('A user with email "john@example.com" already exists.', $exception->getMessage());
    }
}
