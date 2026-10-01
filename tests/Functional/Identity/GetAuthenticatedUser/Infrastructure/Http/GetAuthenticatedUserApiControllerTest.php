<?php

declare(strict_types=1);

namespace App\Tests\Functional\Identity\GetAuthenticatedUser\Infrastructure\Http;

use App\Identity\Domain\UserRepository;
use App\Tests\Functional\Identity\IdentityWebTestCase;

final class GetAuthenticatedUserApiControllerTest extends IdentityWebTestCase
{
    public function testItReturnsTheUserIdentifiedByTheBearerToken(): void
    {
        $user = $this->createUser('john@example.com');

        $this->client->request('GET', '/api/v1/me', server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$this->issueTokenFor($user),
        ]);

        self::assertResponseIsSuccessful();
        $body = json_decode($this->client->getResponse()->getContent(), true, flags: \JSON_THROW_ON_ERROR);
        self::assertSame($user->id()->value, $body['id']);
        self::assertSame('john@example.com', $body['email']);
        self::assertSame(['ROLE_USER'], $body['roles']);
    }

    public function testItAcceptsTheTokenFromTheBearerCookie(): void
    {
        $user = $this->createUser();

        $this->client->getCookieJar()->set(new \Symfony\Component\BrowserKit\Cookie('BEARER', $this->issueTokenFor($user)));
        $this->client->request('GET', '/api/v1/me');

        self::assertResponseIsSuccessful();
    }

    public function testItRejectsRequestsWithoutToken(): void
    {
        $this->client->request('GET', '/api/v1/me');

        self::assertResponseStatusCodeSame(401);
    }

    public function testItRejectsATamperedToken(): void
    {
        $token = $this->issueTokenFor($this->createUser());

        $this->client->request('GET', '/api/v1/me', server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$token.'tampered',
        ]);

        self::assertResponseStatusCodeSame(401);
    }

    public function testItRejectsAValidTokenWhoseUserNoLongerExists(): void
    {
        $user = $this->createUser();
        $token = $this->issueTokenFor($user);
        static::getContainer()->get(UserRepository::class)->remove($user);

        $this->client->request('GET', '/api/v1/me', server: ['HTTP_AUTHORIZATION' => 'Bearer '.$token]);

        self::assertResponseStatusCodeSame(401);
    }

    public function testItNeverStartsASession(): void
    {
        $user = $this->createUser();

        $this->client->request('GET', '/api/v1/me', server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$this->issueTokenFor($user),
        ]);

        self::assertResponseIsSuccessful();
        self::assertFalse($this->client->getRequest()->hasSession());
        self::assertFalse(static::getContainer()->has('session.factory'));
        self::assertSame([], $this->client->getResponse()->headers->getCookies());
    }
}
