<?php

declare(strict_types=1);

namespace App\Tests\Unit\Identity\Infrastructure\Security;

use App\Identity\Infrastructure\Security\LexikAccessTokenIssuer;
use App\Identity\Infrastructure\Security\SecurityUser;
use App\Tests\Double\Identity\UserMother;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(LexikAccessTokenIssuer::class)]
final class LexikAccessTokenIssuerTest extends TestCase
{
    public function testItSignsATokenForTheSecurityUserAndReportsItsExpiration(): void
    {
        $user = UserMother::create();
        $manager = $this->createMock(JWTTokenManagerInterface::class);
        $manager->expects(self::once())
            ->method('create')
            ->with(self::callback(static fn (SecurityUser $u): bool => $u->getUserIdentifier() === $user->id()->value))
            ->willReturn('signed.jwt');
        $manager->expects(self::once())->method('parse')->with('signed.jwt')->willReturn(['exp' => 1893456000]);

        $token = (new LexikAccessTokenIssuer($manager))->issueFor($user);

        self::assertSame('signed.jwt', $token->value);
        self::assertSame(1893456000, $token->expiresAt->getTimestamp());
    }
}
