<?php

declare(strict_types=1);

namespace App\Tests\Unit\Identity\Infrastructure\Security;

use App\Identity\Infrastructure\Security\SymfonyPasswordHasher;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\PasswordHasher\Hasher\NativePasswordHasher;
use Symfony\Component\PasswordHasher\Hasher\PasswordHasherFactory;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;

#[CoversClass(SymfonyPasswordHasher::class)]
final class SymfonyPasswordHasherTest extends TestCase
{
    public function testItHashesAndVerifiesWithTheHasherConfiguredForUsers(): void
    {
        $hasher = $this->hasher(cost: 4);

        $hash = $hasher->hash('s3cret');

        self::assertNotSame('s3cret', $hash);
        self::assertTrue($hasher->verify($hash, 's3cret'));
        self::assertFalse($hasher->verify($hash, 'wrong'));
    }

    public function testItDetectsHashesMadeWithOutdatedSettings(): void
    {
        $hash = $this->hasher(cost: 4)->hash('s3cret');

        self::assertFalse($this->hasher(cost: 4)->needsRehash($hash));
        self::assertTrue($this->hasher(cost: 5)->needsRehash($hash));
    }

    private function hasher(int $cost): SymfonyPasswordHasher
    {
        // Keyed like security.yaml: password_hashers.PasswordAuthenticatedUserInterface
        return new SymfonyPasswordHasher(new PasswordHasherFactory([
            PasswordAuthenticatedUserInterface::class => new NativePasswordHasher(cost: $cost, algorithm: \PASSWORD_BCRYPT),
        ]));
    }
}
