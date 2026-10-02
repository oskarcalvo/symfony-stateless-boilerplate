<?php

declare(strict_types=1);

namespace App\Identity\Infrastructure\Persistence\Doctrine\Type;

use App\Identity\Domain\UserName;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Type;

final class UserNameType extends Type
{
    public const string NAME = 'identity_user_name';

    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return $platform->getStringTypeDeclarationSQL(['length' => UserName::MAX_LENGTH] + $column);
    }

    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): ?UserName
    {
        if (null === $value || $value instanceof UserName) {
            return $value;
        }

        return UserName::fromString((string) $value);
    }

    public function convertToDatabaseValue(mixed $value, AbstractPlatform $platform): ?string
    {
        if (null === $value) {
            return null;
        }

        return ($value instanceof UserName ? $value : UserName::fromString((string) $value))->value;
    }
}
