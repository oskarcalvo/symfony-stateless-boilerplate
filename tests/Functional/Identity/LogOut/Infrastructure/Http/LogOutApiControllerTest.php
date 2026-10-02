<?php

declare(strict_types=1);

namespace App\Tests\Functional\Identity\LogOut\Infrastructure\Http;

use App\Identity\Infrastructure\Security\AccessTokenCookie;
use App\Tests\Functional\Identity\IdentityWebTestCase;
use Symfony\Component\BrowserKit\Cookie;

final class LogOutApiControllerTest extends IdentityWebTestCase
{
    public function testLogoutAnswers204WithoutCookies(): void
    {
        $token = $this->issueTokenFor($this->createUser());

        $this->logOut($token);

        self::assertResponseStatusCodeSame(204);
        self::assertSame('', $this->client->getResponse()->getContent());
        self::assertSame([], $this->client->getResponse()->headers->getCookies());
    }

    public function testTheTokenIsRevokedAndCannotBeReplayed(): void
    {
        $token = $this->issueTokenFor($this->createUser());

        $this->logOut($token);

        $this->client->request('GET', '/api/v1/me', server: ['HTTP_AUTHORIZATION' => 'Bearer '.$token]);
        self::assertResponseStatusCodeSame(401);

        $this->logOut($token);
        self::assertResponseStatusCodeSame(401);
    }

    public function testOnlyTheTokenSentIsRevoked(): void
    {
        $user = $this->createUser();
        $loggedOut = $this->issueTokenFor($user);
        $otherDevice = $this->issueTokenFor($user);

        $this->logOut($loggedOut);

        $this->client->request('GET', '/api/v1/me', server: ['HTTP_AUTHORIZATION' => 'Bearer '.$otherDevice]);
        self::assertResponseIsSuccessful();
    }

    public function testATokenFromTheApiLoginCanBeRevoked(): void
    {
        $this->createUser('john@example.com', 's3cret-Passw0rd');
        $this->client->jsonRequest('POST', '/api/v1/login', ['email' => 'john@example.com', 'password' => 's3cret-Passw0rd']);
        $token = json_decode($this->client->getResponse()->getContent(), true, flags: \JSON_THROW_ON_ERROR)['token'];

        $this->logOut($token);
        self::assertResponseStatusCodeSame(204);

        $this->client->request('GET', '/api/v1/me', server: ['HTTP_AUTHORIZATION' => 'Bearer '.$token]);
        self::assertResponseStatusCodeSame(401);
    }

    public function testWithoutATokenItIsRejected(): void
    {
        $this->client->request('POST', '/api/v1/logout');

        self::assertResponseStatusCodeSame(401);
    }

    public function testOnlyPostIsAllowed(): void
    {
        $token = $this->issueTokenFor($this->createUser());

        // With SameSite=Lax a cross-site GET navigation carries the BEARER cookie: GET must never revoke.
        $this->client->getCookieJar()->set(new Cookie(AccessTokenCookie::NAME, $token, domain: 'localhost'));
        $this->client->request('GET', '/api/v1/logout');
        self::assertResponseStatusCodeSame(405);

        $this->client->getCookieJar()->clear();
        $this->client->request('GET', '/api/v1/me', server: ['HTTP_AUTHORIZATION' => 'Bearer '.$token]);
        self::assertResponseIsSuccessful();
    }

    public function testATamperedTokenIsRejected(): void
    {
        $this->logOut($this->issueTokenFor($this->createUser()).'tampered');

        self::assertResponseStatusCodeSame(401);
    }

    public function testItNeverStartsASession(): void
    {
        $this->logOut($this->issueTokenFor($this->createUser()));

        self::assertFalse($this->client->getRequest()->hasSession());
    }

    private function logOut(string $token): void
    {
        $this->client->request('POST', '/api/v1/logout', server: ['HTTP_AUTHORIZATION' => 'Bearer '.$token]);
    }
}
