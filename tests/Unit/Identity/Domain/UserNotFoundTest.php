<?php

declare(strict_types=1);

namespace App\Tests\Unit\Identity\Domain;

use App\Identity\Domain\UserId;
use App\Identity\Domain\UserNotFound;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(UserNotFound::class)]
final class UserNotFoundTest extends TestCase
{
    public function testItNamesTheMissingId(): void
    {
        $id = UserId::generate();

        $exception = UserNotFound::withId($id);

        self::assertInstanceOf(\DomainException::class, $exception);
        self::assertSame(\sprintf('User "%s" not found.', $id), $exception->getMessage());
    }
}
