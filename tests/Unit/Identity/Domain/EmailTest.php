<?php

declare(strict_types=1);

namespace App\Tests\Unit\Identity\Domain;

use App\Identity\Domain\Email;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Email::class)]
final class EmailTest extends TestCase
{
    public function testItNormalizesTheAddress(): void
    {
        self::assertSame('john@example.com', Email::fromString('  John@Example.COM ')->value);
    }

    #[DataProvider('invalidEmails')]
    public function testItRejectsInvalidAddresses(string $email): void
    {
        $this->expectException(\InvalidArgumentException::class);

        Email::fromString($email);
    }

    public static function invalidEmails(): iterable
    {
        yield 'empty' => [''];
        yield 'no domain' => ['john@'];
        yield 'too long' => [str_repeat('a', 175).'@example.com'];
    }
}
