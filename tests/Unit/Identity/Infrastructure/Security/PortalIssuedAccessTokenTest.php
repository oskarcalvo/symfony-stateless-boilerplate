<?php

declare(strict_types=1);

namespace App\Tests\Unit\Identity\Infrastructure\Security;

use App\Identity\Infrastructure\Security\PortalIssuedAccessToken;
use App\Identity\Infrastructure\Security\SecurityUser;
use App\Tests\Double\Identity\UserMother;
use Lexik\Bundle\JWTAuthenticationBundle\Event\JWTCreatedEvent;
use Lexik\Bundle\JWTAuthenticationBundle\Event\JWTDecodedEvent;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(PortalIssuedAccessToken::class)]
final class PortalIssuedAccessTokenTest extends TestCase
{
    private PortalIssuedAccessToken $listener;

    protected function setUp(): void
    {
        $this->listener = new PortalIssuedAccessToken('the-portal');
    }

    public function testEveryCreatedTokenNamesThePortalAsIssuer(): void
    {
        $event = new JWTCreatedEvent(['sub' => 'user-id', 'iss' => 'someone-else'], SecurityUser::fromUser(UserMother::create()));

        $this->listener->stampIssuer($event);

        self::assertSame(['iss' => 'the-portal', 'sub' => 'user-id'], $event->getData());
    }

    public function testATokenIssuedByThePortalIsAccepted(): void
    {
        $event = new JWTDecodedEvent(['sub' => 'user-id', 'iss' => 'the-portal']);

        $this->listener->rejectForeignIssuer($event);

        self::assertTrue($event->isValid());
    }

    /**
     * @param array<string, mixed> $payload
     */
    #[DataProvider('foreignPayloads')]
    public function testATokenNotIssuedByThePortalIsMarkedInvalid(array $payload): void
    {
        $event = new JWTDecodedEvent($payload);

        $this->listener->rejectForeignIssuer($event);

        self::assertFalse($event->isValid());
    }

    public static function foreignPayloads(): iterable
    {
        yield 'another issuer' => [['sub' => 'user-id', 'iss' => 'another-app']];
        yield 'no issuer' => [['sub' => 'user-id']];
        yield 'issuer of another type' => [['sub' => 'user-id', 'iss' => ['the-portal']]];
    }
}
