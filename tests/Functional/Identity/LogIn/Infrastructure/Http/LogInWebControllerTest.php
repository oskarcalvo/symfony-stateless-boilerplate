<?php

declare(strict_types=1);

namespace App\Tests\Functional\Identity\LogIn\Infrastructure\Http;

use App\Identity\Infrastructure\Security\AccessTokenCookie;
use App\Tests\Functional\Identity\IdentityWebTestCase;
use Symfony\Component\BrowserKit\Cookie;
use Symfony\Component\HttpFoundation\Cookie as ResponseCookie;

final class LogInWebControllerTest extends IdentityWebTestCase
{
    private const array SAME_ORIGIN = ['HTTP_ORIGIN' => 'http://localhost'];

    public function testItRendersTheLoginFormWithACsrfField(): void
    {
        $this->client->request('GET', '/login');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('form input[name="login_form[email]"]');
        self::assertSelectorExists('form input[type="password"][name="login_form[password]"]');
        self::assertSelectorExists('form input[type="hidden"][name="login_form[_token]"]');
    }

    public function testValidCredentialsSetTheJwtCookieAndRedirectToTheDashboard(): void
    {
        $this->createUser('john@example.com', 's3cret-Passw0rd');

        $this->submitLogin('John@Example.com', 's3cret-Passw0rd');

        self::assertResponseStatusCodeSame(303);
        self::assertResponseRedirects('/');
        $cookie = $this->bearerCookieFromResponse();
        self::assertNotNull($cookie);
        self::assertTrue($cookie->isHttpOnly());
        self::assertSame(ResponseCookie::SAMESITE_LAX, $cookie->getSameSite());
        self::assertGreaterThan(time(), $cookie->getExpiresTime());

        $this->client->followRedirect();

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'john@example.com');
    }

    public function testWrongPasswordIsRejected(): void
    {
        $this->createUser('john@example.com', 's3cret-Passw0rd');

        $this->submitLogin('john@example.com', 'wrong');

        $this->assertLoginRejectedWith('Email o contraseña incorrectos.');
    }

    public function testUnknownEmailIsRejectedWithTheSameMessage(): void
    {
        $this->submitLogin('nobody@example.com', 's3cret-Passw0rd');

        $this->assertLoginRejectedWith('Email o contraseña incorrectos.');
    }

    public function testCrossSiteSubmissionIsRejectedByCsrfProtection(): void
    {
        $this->createUser('john@example.com', 's3cret-Passw0rd');

        // A real browser posting from another site sends that site as both Origin and Referer.
        $this->submitLogin('john@example.com', 's3cret-Passw0rd', [
            'HTTP_ORIGIN' => 'https://evil.example',
            'HTTP_REFERER' => 'https://evil.example/phishing',
        ]);

        $this->assertLoginRejectedWith('CSRF');
    }

    public function testSubmissionWithoutAnyCsrfProofIsRejected(): void
    {
        $this->createUser('john@example.com', 's3cret-Passw0rd');

        // Raw POST: no Origin, no Referer, no Sec-Fetch-Site and no double-submit cookie.
        $this->client->request('POST', '/login', ['login_form' => [
            'email' => 'john@example.com',
            'password' => 's3cret-Passw0rd',
            '_token' => 'csrf-token',
        ]]);

        $this->assertLoginRejectedWith('CSRF');
    }

    public function testAnonymousVisitorsOfProtectedPagesAreSentToLogin(): void
    {
        $this->client->request('GET', '/');

        self::assertResponseRedirects('/login');
    }

    public function testAnInvalidTokenCookieIsClearedAndAccessIsDenied(): void
    {
        $this->client->getCookieJar()->set(new Cookie(AccessTokenCookie::NAME, 'not-a-jwt', domain: 'localhost'));

        $this->client->request('GET', '/');

        self::assertResponseStatusCodeSame(401);
        self::assertSelectorTextContains('h1', 'Acceso no permitido');
        self::assertSame(1, $this->bearerCookieFromResponse()?->getExpiresTime());

        $this->client->clickLink('Iniciar sesión');
        self::assertResponseIsSuccessful();
        self::assertRouteSame('identity_login');
    }

    public function testAnAuthenticatedUserVisitingLoginIsSentToTheDashboard(): void
    {
        $user = $this->createUser();
        $this->client->getCookieJar()->set(new Cookie(AccessTokenCookie::NAME, $this->issueTokenFor($user)));

        $this->client->request('GET', '/login');

        self::assertResponseRedirects('/');
    }

    public function testLoggingInNeverStartsASession(): void
    {
        $this->createUser('john@example.com', 's3cret-Passw0rd');

        $this->submitLogin('john@example.com', 's3cret-Passw0rd');

        self::assertFalse($this->client->getRequest()->hasSession());
        $cookieNames = array_map(static fn (ResponseCookie $c) => $c->getName(), $this->client->getResponse()->headers->getCookies());
        self::assertSame([AccessTokenCookie::NAME], $cookieNames);
    }

    public function testRepeatedFailedAttemptsOnAnAccountAreThrottled(): void
    {
        $this->createUser('john@example.com', 's3cret-Passw0rd');

        for ($i = 0; $i < 5; ++$i) {
            $this->submitLogin('john@example.com', 'wrong');
            self::assertResponseStatusCodeSame(422);
        }

        // Even the right password is refused while throttled.
        $this->submitLogin('john@example.com', 's3cret-Passw0rd');

        self::assertResponseStatusCodeSame(429);
        self::assertResponseHasHeader('Retry-After');
        self::assertAnySelectorTextContains('form ul li', 'Demasiados intentos');
        self::assertNull($this->bearerCookieFromResponse());
    }

    public function testASuccessfulLoginResetsTheAccountCounter(): void
    {
        $this->createUser('john@example.com', 's3cret-Passw0rd');

        for ($i = 0; $i < 4; ++$i) {
            $this->submitLogin('john@example.com', 'wrong');
        }
        $this->submitLogin('john@example.com', 's3cret-Passw0rd');
        self::assertResponseRedirects('/');

        $this->client->getCookieJar()->clear();
        for ($i = 0; $i < 4; ++$i) {
            $this->submitLogin('john@example.com', 'wrong');
            self::assertResponseStatusCodeSame(422);
        }
    }

    public function testTheCookieIsSecureWhenATrustedProxyTerminatesHttps(): void
    {
        $this->createUser('john@example.com', 's3cret-Passw0rd');

        $this->submitLogin('john@example.com', 's3cret-Passw0rd', [
            'HTTP_ORIGIN' => 'https://localhost',
            'HTTP_X_FORWARDED_PROTO' => 'https',
            'HTTP_X_FORWARDED_PORT' => '443',
        ]);

        self::assertResponseRedirects();
        self::assertTrue($this->bearerCookieFromResponse()?->isSecure());
    }

    public function testTheCookieIsNotSecureOverPlainHttp(): void
    {
        $this->createUser('john@example.com', 's3cret-Passw0rd');

        $this->submitLogin('john@example.com', 's3cret-Passw0rd');

        self::assertFalse($this->bearerCookieFromResponse()?->isSecure());
    }

    /**
     * @param array<string, string> $server
     */
    private function submitLogin(string $email, string $password, array $server = self::SAME_ORIGIN): void
    {
        $crawler = $this->client->request('GET', '/login');
        $form = $crawler->selectButton('Entrar')->form([
            'login_form[email]' => $email,
            'login_form[password]' => $password,
        ]);

        $this->client->submit($form, serverParameters: $server);
    }

    private function assertLoginRejectedWith(string $message): void
    {
        self::assertResponseStatusCodeSame(422);
        self::assertAnySelectorTextContains('form ul li', $message);
        self::assertNull($this->bearerCookieFromResponse());
    }

    private function bearerCookieFromResponse(): ?ResponseCookie
    {
        foreach ($this->client->getResponse()->headers->getCookies() as $cookie) {
            if (AccessTokenCookie::NAME === $cookie->getName()) {
                return $cookie;
            }
        }

        return null;
    }
}
