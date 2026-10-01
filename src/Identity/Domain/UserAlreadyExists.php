<?php

declare(strict_types=1);

namespace App\Identity\Domain;

final class UserAlreadyExists extends \DomainException
{
    public static function withEmail(Email $email): self
    {
        return new self(\sprintf('A user with email "%s" already exists.', $email));
    }
}
