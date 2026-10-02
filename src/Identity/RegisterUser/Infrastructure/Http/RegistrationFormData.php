<?php

declare(strict_types=1);

namespace App\Identity\RegisterUser\Infrastructure\Http;

use App\Identity\Domain\Email;
use App\Identity\Domain\UserName;
use Symfony\Component\Validator\Constraints as Assert;

final class RegistrationFormData
{
    public const int PASSWORD_MIN_LENGTH = 8;
    // Symfony's password hashers refuse longer passwords.
    public const int PASSWORD_MAX_LENGTH = 4096;

    #[Assert\NotBlank(normalizer: 'trim')]
    #[Assert\Length(max: UserName::MAX_LENGTH)]
    public ?string $name = null;

    #[Assert\NotBlank]
    #[Assert\Email]
    #[Assert\Length(max: Email::MAX_LENGTH)]
    public ?string $email = null;

    #[Assert\NotBlank]
    #[Assert\Length(min: self::PASSWORD_MIN_LENGTH, max: self::PASSWORD_MAX_LENGTH)]
    public ?string $password = null;
}
