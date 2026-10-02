<?php

declare(strict_types=1);

namespace App\Identity\RegisterUser\Application;

use App\Identity\Domain\AccessToken;

final readonly class SignedUpUser
{
    public function __construct(
        public string $id,
        public string $email,
        public string $name,
        public AccessToken $accessToken,
    ) {
    }
}
