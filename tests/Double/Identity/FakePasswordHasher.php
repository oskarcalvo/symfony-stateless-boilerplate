<?php

declare(strict_types=1);

namespace App\Tests\Double\Identity;

use App\Identity\Domain\PasswordHasher;

/**
 * Reversible "hash" so tests can read what was stored. Never use outside tests.
 */
final class FakePasswordHasher implements PasswordHasher
{
    /** @var list<string> */
    public array $hashed = [];

    public function __construct(
        private readonly string $prefix = 'hashed:',
        private readonly bool $needsRehash = false,
    ) {
    }

    public function hash(string $plainPassword): string
    {
        $this->hashed[] = $plainPassword;

        return $this->prefix.$plainPassword;
    }

    public function verify(string $passwordHash, string $plainPassword): bool
    {
        return str_ends_with($passwordHash, ':'.$plainPassword);
    }

    public function needsRehash(string $passwordHash): bool
    {
        return $this->needsRehash;
    }
}
