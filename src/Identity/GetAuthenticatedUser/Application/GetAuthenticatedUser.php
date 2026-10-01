<?php

declare(strict_types=1);

namespace App\Identity\GetAuthenticatedUser\Application;

use App\Identity\Domain\UserId;
use App\Identity\Domain\UserNotFound;
use App\Identity\Domain\UserRepository;

final class GetAuthenticatedUser
{
    public function __construct(
        private readonly UserRepository $users,
    ) {
    }

    public function __invoke(UserId $id): AuthenticatedUserView
    {
        $user = $this->users->ofId($id) ?? throw UserNotFound::withId($id);

        return new AuthenticatedUserView(
            $user->id()->value,
            $user->email()->value,
            $user->roles(),
            $user->registeredAt(),
        );
    }
}
