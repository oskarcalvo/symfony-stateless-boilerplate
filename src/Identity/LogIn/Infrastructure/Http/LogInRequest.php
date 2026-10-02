<?php

declare(strict_types=1);

namespace App\Identity\LogIn\Infrastructure\Http;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * JSON body of POST /api/v1/login. Same rules as the web form (LoginFormData).
 */
final readonly class LogInRequest
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Email]
        public string $email = '',
        #[Assert\NotBlank]
        public string $password = '',
    ) {
    }
}
