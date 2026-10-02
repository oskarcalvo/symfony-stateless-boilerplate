<?php

declare(strict_types=1);

namespace App\Tests\Unit\Identity\Infrastructure\Persistence\Doctrine\Type;

use App\Identity\Domain\UserName;
use App\Identity\Infrastructure\Persistence\Doctrine\Type\UserNameType;
use Doctrine\DBAL\Platforms\MariaDBPlatform;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(UserNameType::class)]
final class UserNameTypeTest extends TestCase
{
    private UserNameType $type;
    private MariaDBPlatform $platform;

    protected function setUp(): void
    {
        $this->type = new UserNameType();
        $this->platform = new MariaDBPlatform();
    }

    public function testItIsStoredAsAVarcharOfTheMaximumNameLength(): void
    {
        self::assertSame('VARCHAR(100)', $this->type->getSQLDeclaration([], $this->platform));
    }

    public function testItConvertsBothWays(): void
    {
        self::assertSame('John Doe', $this->type->convertToDatabaseValue(UserName::fromString('John Doe'), $this->platform));
        self::assertSame('John Doe', $this->type->convertToPHPValue('John Doe', $this->platform)?->value);
    }

    public function testItNormalizesRawStringsAndAcceptsNull(): void
    {
        self::assertSame('John Doe', $this->type->convertToDatabaseValue('  John   Doe ', $this->platform));
        self::assertNull($this->type->convertToPHPValue(null, $this->platform));
        self::assertNull($this->type->convertToDatabaseValue(null, $this->platform));
    }

    public function testItRejectsInvalidValues(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->type->convertToDatabaseValue('   ', $this->platform);
    }
}
