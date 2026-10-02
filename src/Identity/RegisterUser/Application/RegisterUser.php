<?php

declare(strict_types=1);

namespace App\Identity\RegisterUser\Application;

use App\Identity\Domain\Email;
use App\Identity\Domain\PasswordHasher;
use App\Identity\Domain\User;
use App\Identity\Domain\UserAlreadyExists;
use App\Identity\Domain\UserId;
use App\Identity\Domain\UserName;
use App\Identity\Domain\UserRepository;
use Psr\Clock\ClockInterface;

final class RegisterUser
{
    public function __construct(
        private readonly UserRepository $users,
        private readonly PasswordHasher $passwordHasher,
        private readonly ClockInterface $clock,
    ) {
    }

    /**
     * @throws UserAlreadyExists
     * @throws \InvalidArgumentException when the email or the name is not valid
     */
    public function __invoke(string $email, string $name, string $plainPassword): User
    {
        $email = Email::fromString($email);
        $name = UserName::fromString($name);

        if (null !== $this->users->ofEmail($email)) {
            throw UserAlreadyExists::withEmail($email);
        }

        $user = User::register(UserId::generate(), $email, $name, $this->passwordHasher->hash($plainPassword), $this->clock->now());
        $this->users->save($user);

        return $user;
    }
}
