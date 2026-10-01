<?php

declare(strict_types=1);

namespace App\Identity\Domain;

final readonly class AccessToken
{
    public function __construct(
        public string $value,
        public \DateTimeImmutable $expiresAt,
    ) {
    }
}
