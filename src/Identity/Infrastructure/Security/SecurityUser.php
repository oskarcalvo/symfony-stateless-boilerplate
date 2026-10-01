<?php

declare(strict_types=1);

namespace App\Identity\Infrastructure\Security;

use App\Identity\Domain\User;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Adapter between the domain User and Symfony Security. The identifier is the user id,
 * which is what the JWT carries in its "sub" claim.
 */
final readonly class SecurityUser implements UserInterface, PasswordAuthenticatedUserInterface
{
    /**
     * @param non-empty-string $id
     * @param list<string>     $roles
     */
    private function __construct(
        private string $id,
        private string $email,
        private string $password,
        private array $roles,
    ) {
    }

    public static function fromUser(User $user): self
    {
        return new self($user->id()->value, $user->email()->value, $user->passwordHash(), $user->roles());
    }

    public function getUserIdentifier(): string
    {
        return $this->id;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function getRoles(): array
    {
        return $this->roles;
    }

    public function getPassword(): string
    {
        return $this->password;
    }
}
