<?php

declare(strict_types=1);

namespace App\Tests\Functional\Identity\LogOut;

use App\Identity\Infrastructure\Security\AccessTokenCookie;
use App\Tests\Functional\Identity\IdentityWebTestCase;
use Symfony\Component\HttpFoundation\Cookie;

final class LogOutWebTest extends IdentityWebTestCase
{
    private const array SAME_ORIGIN = ['HTTP_ORIGIN' => 'http://localhost'];

    public function testLogoutClearsTheCookieAndRedirectsToLogin(): void
    {
        $this->logIn();

        $this->submitLogout(self::SAME_ORIGIN);

        self::assertResponseRedirects('/login');
        $cookie = $this->bearerCookieFromResponse();
        self::assertNotNull($cookie);
        self::assertTrue($cookie->isCleared());

        $this->client->request('GET', '/');
        self::assertResponseRedirects('/login');
    }

    public function testTheTokenIsRevokedAndCannotBeReplayed(): void
    {
        $token = $this->logIn();

        $this->submitLogout(self::SAME_ORIGIN);

        $this->client->getCookieJar()->clear();
        $this->client->request('GET', '/api/v1/me', server: ['HTTP_AUTHORIZATION' => 'Bearer '.$token]);
        self::assertResponseStatusCodeSame(401);
    }

    public function testCrossSiteLogoutIsRejected(): void
    {
        $token = $this->logIn();

        $this->submitLogout(['HTTP_ORIGIN' => 'https://evil.example', 'HTTP_REFERER' => 'https://evil.example/']);

        self::assertNull($this->bearerCookieFromResponse());
        $this->client->request('GET', '/api/v1/me', server: ['HTTP_AUTHORIZATION' => 'Bearer '.$token]);
        self::assertResponseIsSuccessful();
    }

    private function logIn(): string
    {
        $this->createUser('john@example.com', 's3cret-Passw0rd');

        $crawler = $this->client->request('GET', '/login');
        $this->client->submit($crawler->selectButton('Entrar')->form([
            'login_form[email]' => 'john@example.com',
            'login_form[password]' => 's3cret-Passw0rd',
        ]), serverParameters: self::SAME_ORIGIN);

        $token = $this->bearerCookieFromResponse()?->getValue();
        self::assertNotNull($token);

        return $token;
    }

    /**
     * @param array<string, string> $server
     */
    private function submitLogout(array $server): void
    {
        $crawler = $this->client->request('GET', '/');
        $this->client->submit($crawler->selectButton('Cerrar sesión')->form(), serverParameters: $server);
    }

    private function bearerCookieFromResponse(): ?Cookie
    {
        foreach ($this->client->getResponse()->headers->getCookies() as $cookie) {
            if (AccessTokenCookie::NAME === $cookie->getName()) {
                return $cookie;
            }
        }

        return null;
    }
}
