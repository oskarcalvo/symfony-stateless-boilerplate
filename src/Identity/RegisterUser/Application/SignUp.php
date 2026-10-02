<?php

declare(strict_types=1);

namespace App\Identity\RegisterUser\Application;

use App\Identity\Domain\AccessTokenIssuer;
use App\Identity\Domain\UserAlreadyExists;

/**
 * Self-service registration: creates the account and issues its first JWT, so the new user is
 * logged in straight away. Shared by every public channel (web form, API v1); the console
 * command uses RegisterUser alone because an administrator does not need a token.
 */
final class SignUp
{
    public function __construct(
        private readonly RegisterUser $registerUser,
        private readonly AccessTokenIssuer $accessTokenIssuer,
    ) {
    }

    /**
     * @throws UserAlreadyExists
     * @throws \InvalidArgumentException when the email or the name is not valid
     */
    public function __invoke(string $email, string $name, string $plainPassword): SignedUpUser
    {
        $user = ($this->registerUser)($email, $name, $plainPassword);

        return new SignedUpUser(
            $user->id()->value,
            $user->email()->value,
            $user->name()->value,
            $this->accessTokenIssuer->issueFor($user),
        );
    }
}
