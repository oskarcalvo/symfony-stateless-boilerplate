<?php

declare(strict_types=1);

namespace App\Identity\LogIn\Application;

use App\Identity\Domain\AccessToken;
use App\Identity\Domain\AccessTokenIssuer;
use App\Identity\Domain\Email;
use App\Identity\Domain\InvalidCredentials;
use App\Identity\Domain\PasswordHasher;
use App\Identity\Domain\UserRepository;

/**
 * Checks the credentials and issues the JWT. Shared by every delivery channel (web form, API v1).
 */
final class LogIn
{
    public function __construct(
        private readonly UserRepository $users,
        private readonly PasswordHasher $passwordHasher,
        private readonly AccessTokenIssuer $accessTokenIssuer,
    ) {
    }

    /**
     * @throws InvalidCredentials
     */
    public function __invoke(string $email, string $plainPassword): AccessToken
    {
        try {
            $user = $this->users->ofEmail(Email::fromString($email));
        } catch (\InvalidArgumentException) {
            $user = null;
        }

        if (null === $user) {
            // Spend the same time as a real check so response times don't reveal which emails exist.
            $this->passwordHasher->hash($plainPassword);

            throw new InvalidCredentials();
        }

        if (!$this->passwordHasher->verify($user->passwordHash(), $plainPassword)) {
            throw new InvalidCredentials();
        }

        if ($this->passwordHasher->needsRehash($user->passwordHash())) {
            $user->changePasswordHash($this->passwordHasher->hash($plainPassword));
            $this->users->save($user);
        }

        return $this->accessTokenIssuer->issueFor($user);
    }
}
