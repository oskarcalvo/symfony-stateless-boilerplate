<?php

declare(strict_types=1);

namespace App\Tests\Unit\Identity\Domain;

use App\Identity\Domain\InvalidCredentials;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(InvalidCredentials::class)]
final class InvalidCredentialsTest extends TestCase
{
    public function testItDoesNotRevealWhichCredentialFailed(): void
    {
        $exception = new InvalidCredentials();

        self::assertInstanceOf(\DomainException::class, $exception);
        self::assertSame('Invalid credentials.', $exception->getMessage());
    }
}
