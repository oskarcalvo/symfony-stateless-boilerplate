<?php

declare(strict_types=1);

namespace App\Identity\Domain;

/**
 * Port: one-way hashing of user passwords.
 */
interface PasswordHasher
{
    public function hash(string $plainPassword): string;

    public function verify(string $passwordHash, string $plainPassword): bool;

    public function needsRehash(string $passwordHash): bool;
}
