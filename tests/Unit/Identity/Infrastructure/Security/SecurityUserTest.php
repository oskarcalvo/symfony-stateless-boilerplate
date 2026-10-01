<?php

declare(strict_types=1);

namespace App\Tests\Unit\Identity\Infrastructure\Security;

use App\Identity\Infrastructure\Security\SecurityUser;
use App\Tests\Double\Identity\UserMother;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(SecurityUser::class)]
final class SecurityUserTest extends TestCase
{
    public function testItIsIdentifiedByTheUserIdNotTheEmail(): void
    {
        $user = UserMother::create('john@example.com', 'hash');
        $user->grantRole('ROLE_ADMIN');

        $securityUser = SecurityUser::fromUser($user);

        self::assertSame($user->id()->value, $securityUser->getUserIdentifier());
        self::assertSame('john@example.com', $securityUser->getEmail());
        self::assertSame('hash', $securityUser->getPassword());
        self::assertSame(['ROLE_ADMIN', 'ROLE_USER'], $securityUser->getRoles());
    }
}
