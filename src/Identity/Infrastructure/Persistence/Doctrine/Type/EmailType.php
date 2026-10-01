<?php

declare(strict_types=1);

namespace App\Identity\Infrastructure\Persistence\Doctrine\Type;

use App\Identity\Domain\Email;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Type;

final class EmailType extends Type
{
    public const string NAME = 'identity_email';

    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return $platform->getStringTypeDeclarationSQL(['length' => Email::MAX_LENGTH] + $column);
    }

    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): ?Email
    {
        if (null === $value || $value instanceof Email) {
            return $value;
        }

        return Email::fromString((string) $value);
    }

    public function convertToDatabaseValue(mixed $value, AbstractPlatform $platform): ?string
    {
        if (null === $value) {
            return null;
        }

        return ($value instanceof Email ? $value : Email::fromString((string) $value))->value;
    }
}
