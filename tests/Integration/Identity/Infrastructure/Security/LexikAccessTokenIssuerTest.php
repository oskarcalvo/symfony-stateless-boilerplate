<?php

declare(strict_types=1);

namespace App\Tests\Integration\Identity\Infrastructure\Security;

use App\Identity\Domain\AccessTokenIssuer;
use App\Identity\Domain\Email;
use App\Identity\Domain\User;
use App\Identity\Domain\UserId;
use App\Identity\Domain\UserName;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class LexikAccessTokenIssuerTest extends KernelTestCase
{
    public function testItIssuesASignedTokenCarryingTheUserIdAsSubjectAndThePortalAsIssuer(): void
    {
        $user = User::register(UserId::generate(), Email::fromString('john@example.com'), UserName::fromString('John Doe'), 'hash', new \DateTimeImmutable());

        $token = static::getContainer()->get(AccessTokenIssuer::class)->issueFor($user);
        $payload = static::getContainer()->get(JWTTokenManagerInterface::class)->parse($token->value);

        self::assertSame($user->id()->value, $payload['sub']);
        self::assertSame('stateless-portal', $payload['iss']);
        self::assertSame($payload['exp'], $token->expiresAt->getTimestamp());
        self::assertGreaterThan(time(), $token->expiresAt->getTimestamp());
    }
}
