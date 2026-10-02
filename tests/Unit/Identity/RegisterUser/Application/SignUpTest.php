<?php

declare(strict_types=1);

namespace App\Tests\Unit\Identity\RegisterUser\Application;

use App\Identity\Domain\Email;
use App\Identity\Domain\UserAlreadyExists;
use App\Identity\RegisterUser\Application\RegisterUser;
use App\Identity\RegisterUser\Application\SignUp;
use App\Tests\Double\Identity\FakeAccessTokenIssuer;
use App\Tests\Double\Identity\FakePasswordHasher;
use App\Tests\Double\Identity\InMemoryUserRepository;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;

#[CoversClass(SignUp::class)]
final class SignUpTest extends TestCase
{
    private InMemoryUserRepository $users;

    protected function setUp(): void
    {
        $this->users = new InMemoryUserRepository();
    }

    public function testItRegistersTheUserAndIssuesATokenForIt(): void
    {
        $signedUp = $this->signUp()('John@Example.com', ' John  Doe ', 's3cret-Passw0rd');

        $user = $this->users->ofEmail(Email::fromString('john@example.com'));
        self::assertNotNull($user);
        self::assertSame('hashed:s3cret-Passw0rd', $user->passwordHash());
        self::assertSame($user->id()->value, $signedUp->id);
        self::assertSame('john@example.com', $signedUp->email);
        self::assertSame('John Doe', $signedUp->name);
        self::assertSame('token-for-'.$user->id()->value, $signedUp->accessToken->value);
    }

    public function testADuplicatedEmailIsRejectedWithoutStoringAnything(): void
    {
        $this->signUp()('john@example.com', 'John Doe', 's3cret-Passw0rd');

        try {
            $this->signUp()('JOHN@example.com', 'Other', 'other-Passw0rd');
            self::fail('A duplicated email must be rejected.');
        } catch (UserAlreadyExists) {
        }

        self::assertSame(1, $this->users->saves);
    }

    public function testInvalidUserDataIsRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->signUp()('not-an-email', 'John Doe', 's3cret-Passw0rd');
    }

    private function signUp(): SignUp
    {
        return new SignUp(
            new RegisterUser($this->users, new FakePasswordHasher(), new MockClock('2026-01-01 10:00:00', 'UTC')),
            new FakeAccessTokenIssuer(),
        );
    }
}
