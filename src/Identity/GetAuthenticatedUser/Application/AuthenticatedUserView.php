<?php

declare(strict_types=1);

namespace App\Identity\GetAuthenticatedUser\Application;

final readonly class AuthenticatedUserView
{
    /**
     * @param list<string> $roles
     */
    public function __construct(
        public string $id,
        public string $email,
        public array $roles,
        public \DateTimeImmutable $registeredAt,
    ) {
    }
}
