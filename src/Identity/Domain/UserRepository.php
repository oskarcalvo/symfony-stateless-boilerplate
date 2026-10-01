<?php

declare(strict_types=1);

namespace App\Identity\Domain;

interface UserRepository
{
    public function save(User $user): void;

    public function remove(User $user): void;

    public function ofId(UserId $id): ?User;

    public function ofEmail(Email $email): ?User;
}
