<?php

declare(strict_types=1);

namespace App\Identity\RegisterUser\Infrastructure\Cli;

use App\Identity\Domain\UserAlreadyExists;
use App\Identity\RegisterUser\Application\RegisterUser;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'identity:user:register', description: 'Registers a user that can log in with email and password')]
final class RegisterUserCommand
{
    public function __construct(
        private readonly RegisterUser $registerUser,
    ) {
    }

    public function __invoke(
        SymfonyStyle $io,
        #[Argument('The user email')] string $email,
        #[Argument('The plain password')] string $password,
        #[Argument('The name shown to the user')] string $name,
    ): int {
        try {
            $id = ($this->registerUser)($email, $name, $password);
        } catch (UserAlreadyExists|\InvalidArgumentException $e) {
            $io->error($e->getMessage());

            return Command::FAILURE;
        }

        $io->success(\sprintf('User "%s" registered with id %s.', $email, $id));

        return Command::SUCCESS;
    }
}
