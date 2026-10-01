<?php

declare(strict_types=1);

namespace App\Identity\Domain;

/**
 * Deliberately vague: callers must not learn whether the email or the password was wrong.
 */
final class InvalidCredentials extends \DomainException
{
    public function __construct()
    {
        parent::__construct('Invalid credentials.');
    }
}
