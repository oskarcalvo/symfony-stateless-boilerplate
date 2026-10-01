<?php

declare(strict_types=1);

namespace App\Tests\Double\Identity;

use App\Identity\Domain\Email;
use App\Identity\Domain\User;
use App\Identity\Domain\UserId;
use App\Identity\Domain\UserRepository;

final class InMemoryUserRepository implements UserRepository
{
    /** @var array<string, User> */
    private array $users = [];

    public int $saves = 0;

    public function save(User $user): void
    {
        $this->users[$user->id()->value] = $user;
        ++$this->saves;
    }

    public function remove(User $user): void
    {
        unset($this->users[$user->id()->value]);
    }

    public function ofId(UserId $id): ?User
    {
        return $this->users[$id->value] ?? null;
    }

    public function ofEmail(Email $email): ?User
    {
        foreach ($this->users as $user) {
            if ($user->email()->equals($email)) {
                return $user;
            }
        }

        return null;
    }
}
