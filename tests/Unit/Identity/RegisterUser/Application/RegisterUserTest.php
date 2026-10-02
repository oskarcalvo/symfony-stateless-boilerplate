<?php

declare(strict_types=1);

namespace App\Tests\Unit\Identity\RegisterUser\Application;

use App\Identity\Domain\Email;
use App\Identity\Domain\UserAlreadyExists;
use App\Identity\RegisterUser\Application\RegisterUser;
use App\Tests\Double\Identity\FakePasswordHasher;
use App\Tests\Double\Identity\InMemoryUserRepository;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;

#[CoversClass(RegisterUser::class)]
final class RegisterUserTest extends TestCase
{
    private InMemoryUserRepository $users;

    protected function setUp(): void
    {
        $this->users = new InMemoryUserRepository();
    }

    public function testItStoresTheUserWithAHashedPassword(): void
    {
        $registered = $this->registerUser()('John@Example.com', ' John  Doe ', 's3cret');

        $user = $this->users->ofId($registered->id());
        self::assertSame($registered, $user);
        self::assertSame('john@example.com', $user->email()->value);
        self::assertSame('John Doe', $user->name()->value);
        self::assertSame('hashed:s3cret', $user->passwordHash());
        self::assertEquals(new \DateTimeImmutable('2026-01-01 10:00:00', new \DateTimeZone('UTC')), $user->registeredAt());
        self::assertSame(['ROLE_USER'], $user->roles());
    }

    public function testTheEmailMustBeUnique(): void
    {
        $this->registerUser()('john@example.com', 'John Doe', 's3cret');

        $this->expectException(UserAlreadyExists::class);

        $this->registerUser()('JOHN@example.com', 'Other', 'other');
    }

    public function testTheEmailMustBeValid(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->registerUser()('not-an-email', 'John Doe', 's3cret');
    }

    public function testTheNameMustBeValid(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->registerUser()('john@example.com', '   ', 's3cret');
    }

    public function testNothingIsStoredWhenRegistrationFails(): void
    {
        try {
            $this->registerUser()('john@example.com', '', 's3cret');
        } catch (\InvalidArgumentException) {
        }

        self::assertNull($this->users->ofEmail(Email::fromString('john@example.com')));
        self::assertSame(0, $this->users->saves);
    }

    private function registerUser(): RegisterUser
    {
        return new RegisterUser($this->users, new FakePasswordHasher(), new MockClock('2026-01-01 10:00:00', 'UTC'));
    }
}
