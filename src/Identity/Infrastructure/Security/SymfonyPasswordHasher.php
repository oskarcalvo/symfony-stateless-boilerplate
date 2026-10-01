<?php

declare(strict_types=1);

namespace App\Identity\Infrastructure\Security;

use App\Identity\Domain\PasswordHasher;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;
use Symfony\Component\PasswordHasher\Hasher\PasswordHasherFactoryInterface;
use Symfony\Component\PasswordHasher\PasswordHasherInterface;

/**
 * Uses the hasher configured for users in security.yaml (password_hashers).
 */
#[AsAlias(PasswordHasher::class)]
final class SymfonyPasswordHasher implements PasswordHasher
{
    public function __construct(
        private readonly PasswordHasherFactoryInterface $hasherFactory,
    ) {
    }

    public function hash(string $plainPassword): string
    {
        return $this->hasher()->hash($plainPassword);
    }

    public function verify(string $passwordHash, string $plainPassword): bool
    {
        return $this->hasher()->verify($passwordHash, $plainPassword);
    }

    public function needsRehash(string $passwordHash): bool
    {
        return $this->hasher()->needsRehash($passwordHash);
    }

    private function hasher(): PasswordHasherInterface
    {
        return $this->hasherFactory->getPasswordHasher(SecurityUser::class);
    }
}
