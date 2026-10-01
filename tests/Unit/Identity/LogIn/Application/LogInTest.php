<?php

declare(strict_types=1);

namespace App\Tests\Unit\Identity\LogIn\Application;

use App\Identity\Domain\InvalidCredentials;
use App\Identity\LogIn\Application\LogIn;
use App\Tests\Double\Identity\FakeAccessTokenIssuer;
use App\Tests\Double\Identity\FakePasswordHasher;
use App\Tests\Double\Identity\InMemoryUserRepository;
use App\Tests\Double\Identity\UserMother;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(LogIn::class)]
final class LogInTest extends TestCase
{
    private InMemoryUserRepository $users;

    protected function setUp(): void
    {
        $this->users = new InMemoryUserRepository();
    }

    public function testValidCredentialsIssueATokenForTheUser(): void
    {
        $user = UserMother::create('john@example.com', 'hashed:s3cret');
        $this->users->save($user);

        $token = $this->logIn()('John@Example.com', 's3cret');

        self::assertSame('token-for-'.$user->id()->value, $token->value);
    }

    public function testAWrongPasswordIsRejected(): void
    {
        $this->users->save(UserMother::create('john@example.com', 'hashed:s3cret'));

        $this->expectException(InvalidCredentials::class);

        $this->logIn()('john@example.com', 'wrong');
    }

    public function testAnUnknownEmailIsRejectedAfterSpendingAHash(): void
    {
        $hasher = new FakePasswordHasher();

        try {
            $this->logIn($hasher)('nobody@example.com', 's3cret');
            self::fail('InvalidCredentials expected.');
        } catch (InvalidCredentials) {
            self::assertSame(['s3cret'], $hasher->hashed, 'A hash must be computed to keep timing constant.');
        }
    }

    public function testAMalformedEmailIsRejectedAsInvalidCredentials(): void
    {
        $this->expectException(InvalidCredentials::class);

        $this->logIn()('not-an-email', 's3cret');
    }

    public function testAnOutdatedHashIsUpgradedOnLogin(): void
    {
        $user = UserMother::create('john@example.com', 'old:s3cret');
        $this->users->save($user);

        $this->logIn(new FakePasswordHasher(prefix: 'new:', needsRehash: true))('john@example.com', 's3cret');

        self::assertSame('new:s3cret', $user->passwordHash());
        self::assertSame(2, $this->users->saves);
    }

    public function testACurrentHashIsLeftUntouched(): void
    {
        $this->users->save(UserMother::create('john@example.com', 'hashed:s3cret'));

        $this->logIn()('john@example.com', 's3cret');

        self::assertSame(1, $this->users->saves);
    }

    private function logIn(?FakePasswordHasher $hasher = null): LogIn
    {
        return new LogIn($this->users, $hasher ?? new FakePasswordHasher(), new FakeAccessTokenIssuer());
    }
}
