<?php

declare(strict_types=1);

namespace App\Identity\LogIn\Infrastructure\Http;

use Symfony\Component\Validator\Constraints as Assert;

final class LoginFormData
{
    #[Assert\NotBlank]
    #[Assert\Email]
    public ?string $email = null;

    #[Assert\NotBlank]
    public ?string $password = null;
}
