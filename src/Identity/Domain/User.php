<?php

declare(strict_types=1);

namespace App\Identity\Domain;

/**
 * User aggregate. Persistence mapping lives in Infrastructure (XML) so the domain stays framework-agnostic.
 */
class User
{
    public const string DEFAULT_ROLE = 'ROLE_USER';

    /**
     * @param list<string> $roles
     */
    private function __construct(
        private UserId $id,
        private Email $email,
        private string $passwordHash,
        private array $roles,
        private \DateTimeImmutable $registeredAt,
    ) {
    }

    public static function register(UserId $id, Email $email, string $passwordHash, \DateTimeImmutable $registeredAt): self
    {
        return new self($id, $email, $passwordHash, [], $registeredAt);
    }

    public function id(): UserId
    {
        return $this->id;
    }

    public function email(): Email
    {
        return $this->email;
    }

    public function passwordHash(): string
    {
        return $this->passwordHash;
    }

    /**
     * @return list<string>
     */
    public function roles(): array
    {
        return array_values(array_unique([...$this->roles, self::DEFAULT_ROLE]));
    }

    public function registeredAt(): \DateTimeImmutable
    {
        return $this->registeredAt;
    }

    public function grantRole(string $role): void
    {
        if (!\in_array($role, $this->roles(), true)) {
            $this->roles[] = $role;
        }
    }

    public function changePasswordHash(string $passwordHash): void
    {
        $this->passwordHash = $passwordHash;
    }
}
