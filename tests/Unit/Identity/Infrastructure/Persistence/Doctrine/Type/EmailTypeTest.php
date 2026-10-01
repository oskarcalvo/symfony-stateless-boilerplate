<?php

declare(strict_types=1);

namespace App\Tests\Unit\Identity\Infrastructure\Persistence\Doctrine\Type;

use App\Identity\Domain\Email;
use App\Identity\Infrastructure\Persistence\Doctrine\Type\EmailType;
use Doctrine\DBAL\Platforms\MariaDBPlatform;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(EmailType::class)]
final class EmailTypeTest extends TestCase
{
    private EmailType $type;
    private MariaDBPlatform $platform;

    protected function setUp(): void
    {
        $this->type = new EmailType();
        $this->platform = new MariaDBPlatform();
    }

    public function testItIsStoredAsAVarcharOfTheMaximumEmailLength(): void
    {
        self::assertSame('VARCHAR(180)', $this->type->getSQLDeclaration([], $this->platform));
    }

    public function testItConvertsBothWays(): void
    {
        self::assertSame('john@example.com', $this->type->convertToDatabaseValue(Email::fromString('john@example.com'), $this->platform));
        self::assertSame('john@example.com', $this->type->convertToPHPValue('john@example.com', $this->platform)?->value);
    }

    public function testItNormalizesRawStringsAndAcceptsNull(): void
    {
        self::assertSame('john@example.com', $this->type->convertToDatabaseValue('John@Example.com', $this->platform));
        self::assertNull($this->type->convertToPHPValue(null, $this->platform));
        self::assertNull($this->type->convertToDatabaseValue(null, $this->platform));
    }

    public function testItRejectsInvalidValues(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->type->convertToDatabaseValue('not-an-email', $this->platform);
    }
}
