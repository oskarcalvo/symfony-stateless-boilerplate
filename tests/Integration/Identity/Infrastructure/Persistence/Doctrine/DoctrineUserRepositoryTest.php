<?php

declare(strict_types=1);

namespace App\Tests\Integration\Identity\Infrastructure\Persistence\Doctrine;

use App\Identity\Domain\Email;
use App\Identity\Domain\UserId;
use App\Identity\Infrastructure\Persistence\Doctrine\DoctrineUserRepository;
use App\Identity\Domain\UserRepository;
use App\Tests\Double\Identity\UserMother;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

#[CoversClass(DoctrineUserRepository::class)]
final class DoctrineUserRepositoryTest extends KernelTestCase
{
    private UserRepository $users;
    private EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        $this->entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $this->entityManager->getConnection()->executeStatement('DELETE FROM identity_user');
        $this->users = static::getContainer()->get(UserRepository::class);
    }

    public function testItIsTheAdapterBehindThePort(): void
    {
        self::assertInstanceOf(DoctrineUserRepository::class, $this->users);
    }

    public function testASavedUserCanBeFoundByIdAndEmail(): void
    {
        $user = UserMother::create('john@example.com', 'hash', name: 'John Doe');
        $user->grantRole('ROLE_ADMIN');
        $this->users->save($user);
        $this->entityManager->clear();

        $byId = $this->users->ofId($user->id());
        $byEmail = $this->users->ofEmail(Email::fromString('john@example.com'));

        self::assertNotNull($byId);
        self::assertNotSame($user, $byId, 'Must come from the database, not the identity map.');
        self::assertTrue($byId->id()->equals($user->id()));
        self::assertSame('john@example.com', $byId->email()->value);
        self::assertSame('John Doe', $byId->name()->value);
        self::assertSame('hash', $byId->passwordHash());
        self::assertSame(['ROLE_ADMIN', 'ROLE_USER'], $byId->roles());
        self::assertEquals($user->registeredAt(), $byId->registeredAt());
        self::assertTrue($byEmail?->id()->equals($user->id()));
    }

    public function testUnknownUsersAreNull(): void
    {
        self::assertNull($this->users->ofId(UserId::generate()));
        self::assertNull($this->users->ofEmail(Email::fromString('nobody@example.com')));
    }

    public function testARemovedUserIsGone(): void
    {
        $user = UserMother::create();
        $this->users->save($user);

        $this->users->remove($user);
        $this->entityManager->clear();

        self::assertNull($this->users->ofId($user->id()));
    }
}
