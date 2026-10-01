<?php

declare(strict_types=1);

namespace App\Identity\Infrastructure\Persistence\Doctrine;

use App\Identity\Domain\Email;
use App\Identity\Domain\User;
use App\Identity\Domain\UserId;
use App\Identity\Domain\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;

#[AsAlias(UserRepository::class)]
final class DoctrineUserRepository implements UserRepository
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function save(User $user): void
    {
        $this->entityManager->persist($user);
        $this->entityManager->flush();
    }

    public function remove(User $user): void
    {
        $this->entityManager->remove($user);
        $this->entityManager->flush();
    }

    public function ofId(UserId $id): ?User
    {
        return $this->entityManager->find(User::class, $id);
    }

    public function ofEmail(Email $email): ?User
    {
        return $this->entityManager->getRepository(User::class)->findOneBy(['email' => $email]);
    }
}
