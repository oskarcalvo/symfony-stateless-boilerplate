<?php

declare(strict_types=1);

namespace App\Tests\Unit\Identity\Infrastructure\Persistence\Doctrine\Type;

use App\Identity\Domain\UserId;
use App\Identity\Infrastructure\Persistence\Doctrine\Type\UserIdType;
use Doctrine\DBAL\Platforms\MariaDBPlatform;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(UserIdType::class)]
final class UserIdTypeTest extends TestCase
{
    private UserIdType $type;
    private MariaDBPlatform $platform;

    protected function setUp(): void
    {
        $this->type = new UserIdType();
        $this->platform = new MariaDBPlatform();
    }

    public function testItIsStoredAsAGuidColumn(): void
    {
        self::assertSame($this->platform->getGuidTypeDeclarationSQL([]), $this->type->getSQLDeclaration([], $this->platform));
    }

    public function testItConvertsBothWays(): void
    {
        $id = UserId::generate();

        self::assertSame($id->value, $this->type->convertToDatabaseValue($id, $this->platform));
        self::assertTrue($id->equals($this->type->convertToPHPValue($id->value, $this->platform)));
    }

    public function testItAcceptsAlreadyConvertedValuesAndNull(): void
    {
        $id = UserId::generate();

        self::assertSame($id, $this->type->convertToPHPValue($id, $this->platform));
        self::assertSame($id->value, $this->type->convertToDatabaseValue($id->value, $this->platform));
        self::assertNull($this->type->convertToPHPValue(null, $this->platform));
        self::assertNull($this->type->convertToDatabaseValue(null, $this->platform));
    }

    public function testItRejectsInvalidValues(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->type->convertToDatabaseValue('not-a-uuid', $this->platform);
    }
}
