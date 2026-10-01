<?php

declare(strict_types=1);

namespace App\Tests\Unit\Identity\Infrastructure\Security;

use App\Identity\Domain\UserId;
use App\Identity\Infrastructure\Security\SecurityUser;
use App\Identity\Infrastructure\Security\SecurityUserProvider;
use App\Tests\Double\Identity\InMemoryUserRepository;
use App\Tests\Double\Identity\UserMother;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;
use Symfony\Component\Security\Core\User\InMemoryUser;

#[CoversClass(SecurityUserProvider::class)]
final class SecurityUserProviderTest extends TestCase
{
    private InMemoryUserRepository $users;
    private SecurityUserProvider $provider;

    protected function setUp(): void
    {
        $this->users = new InMemoryUserRepository();
        $this->provider = new SecurityUserProvider($this->users);
    }

    public function testItLoadsTheUserByItsId(): void
    {
        $user = UserMother::create();
        $this->users->save($user);

        self::assertSame($user->id()->value, $this->provider->loadUserByIdentifier($user->id()->value)->getUserIdentifier());
    }

    public function testAnUnknownIdIsNotFound(): void
    {
        $this->expectException(UserNotFoundException::class);

        $this->provider->loadUserByIdentifier(UserId::generate()->value);
    }

    public function testAMalformedIdIsNotFound(): void
    {
        try {
            $this->provider->loadUserByIdentifier('not-a-uuid');
            self::fail('UserNotFoundException expected.');
        } catch (UserNotFoundException $e) {
            self::assertSame('not-a-uuid', $e->getUserIdentifier());
        }
    }

    public function testRefreshingReloadsFromTheRepository(): void
    {
        $user = UserMother::create();
        $this->users->save($user);
        $stale = SecurityUser::fromUser($user);
        $user->grantRole('ROLE_ADMIN');

        self::assertContains('ROLE_ADMIN', $this->provider->refreshUser($stale)->getRoles());
    }

    public function testItOnlySupportsSecurityUsers(): void
    {
        self::assertTrue($this->provider->supportsClass(SecurityUser::class));
        self::assertFalse($this->provider->supportsClass(InMemoryUser::class));

        $this->expectException(UnsupportedUserException::class);
        $this->provider->refreshUser(new InMemoryUser('john', 'pass'));
    }

    public function testItUpgradesThePasswordHash(): void
    {
        $user = UserMother::create(passwordHash: 'old');
        $this->users->save($user);

        $this->provider->upgradePassword(SecurityUser::fromUser($user), 'new');

        self::assertSame('new', $user->passwordHash());
    }

    public function testUpgradingIgnoresUnknownUsers(): void
    {
        $this->provider->upgradePassword(SecurityUser::fromUser(UserMother::create()), 'new');
        $this->provider->upgradePassword(new InMemoryUser('john', 'pass'), 'new');

        self::assertSame(0, $this->users->saves);
    }
}
