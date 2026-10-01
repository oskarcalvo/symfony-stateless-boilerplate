<?php

declare(strict_types=1);

namespace App\Identity\Infrastructure\Security;

use App\Identity\Domain\UserId;
use App\Identity\Domain\UserRepository;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\PasswordUpgraderInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;

/**
 * Loads the user from the database on every request, using the id carried by the JWT.
 * Nothing about the user is kept between requests: no session, no serialized token.
 *
 * @implements UserProviderInterface<SecurityUser>
 */
final class SecurityUserProvider implements UserProviderInterface, PasswordUpgraderInterface
{
    public function __construct(
        private readonly UserRepository $users,
    ) {
    }

    public function loadUserByIdentifier(string $identifier): SecurityUser
    {
        try {
            $user = $this->users->ofId(UserId::fromString($identifier));
        } catch (\InvalidArgumentException) {
            $user = null;
        }

        if (null === $user) {
            $exception = new UserNotFoundException();
            $exception->setUserIdentifier($identifier);

            throw $exception;
        }

        return SecurityUser::fromUser($user);
    }

    public function refreshUser(UserInterface $user): SecurityUser
    {
        if (!$user instanceof SecurityUser) {
            throw new UnsupportedUserException(\sprintf('Instances of "%s" are not supported.', $user::class));
        }

        return $this->loadUserByIdentifier($user->getUserIdentifier());
    }

    public function supportsClass(string $class): bool
    {
        return SecurityUser::class === $class;
    }

    public function upgradePassword(PasswordAuthenticatedUserInterface $user, string $newHashedPassword): void
    {
        if (!$user instanceof SecurityUser) {
            return;
        }

        $domainUser = $this->users->ofId(UserId::fromString($user->getUserIdentifier()));
        if (null === $domainUser) {
            return;
        }

        $domainUser->changePasswordHash($newHashedPassword);
        $this->users->save($domainUser);
    }
}
