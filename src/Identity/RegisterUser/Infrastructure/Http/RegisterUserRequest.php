<?php

declare(strict_types=1);

namespace App\Identity\RegisterUser\Infrastructure\Http;

use App\Identity\Domain\Email;
use App\Identity\Domain\UserName;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * JSON body of POST /api/v1/register. Same rules as the web form (RegistrationFormData).
 */
final readonly class RegisterUserRequest
{
    public function __construct(
        #[Assert\NotBlank(normalizer: 'trim')]
        #[Assert\Length(max: UserName::MAX_LENGTH)]
        public string $name = '',
        #[Assert\NotBlank]
        #[Assert\Email]
        #[Assert\Length(max: Email::MAX_LENGTH)]
        public string $email = '',
        #[Assert\NotBlank]
        #[Assert\Length(min: RegistrationFormData::PASSWORD_MIN_LENGTH, max: RegistrationFormData::PASSWORD_MAX_LENGTH)]
        public string $password = '',
    ) {
    }
}
