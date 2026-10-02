<?php

declare(strict_types=1);

namespace App\Tests\Functional\Identity\RegisterUser\Infrastructure\Http;

use App\Identity\Domain\Email;
use App\Identity\Domain\UserRepository;
use App\Identity\Infrastructure\Security\AccessTokenCookie;
use App\Tests\Functional\Identity\IdentityWebTestCase;
use Symfony\Component\BrowserKit\Cookie;
use Symfony\Component\HttpFoundation\Cookie as ResponseCookie;

final class RegisterUserWebControllerTest extends IdentityWebTestCase
{
    private const array SAME_ORIGIN = ['HTTP_ORIGIN' => 'http://localhost'];

    public function testItRendersTheRegistrationFormWithACsrfField(): void
    {
        $this->client->request('GET', '/register');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('form input[name="registration_form[name]"]');
        self::assertSelectorExists('form input[type="email"][name="registration_form[email]"]');
        self::assertSelectorExists('form input[type="password"][name="registration_form[password][first]"]');
        self::assertSelectorExists('form input[type="password"][name="registration_form[password][second]"]');
        self::assertSelectorExists('form input[type="hidden"][name="registration_form[_token]"]');
    }

    public function testTheLoginPageLinksToTheRegistrationForm(): void
    {
        $this->client->request('GET', '/login');
        $this->client->clickLink('Crea una');

        self::assertResponseIsSuccessful();
        self::assertRouteSame('identity_register');
    }

    public function testAValidSignUpCreatesTheUserLogsItInAndRedirectsToTheDashboard(): void
    {
        $this->submitRegistration('John@Example.com', ' John  Doe ', 's3cret-Passw0rd');

        self::assertResponseStatusCodeSame(303);
        self::assertResponseRedirects('/');
        $cookie = $this->bearerCookieFromResponse();
        self::assertNotNull($cookie);
        self::assertTrue($cookie->isHttpOnly());

        $user = static::getContainer()->get(UserRepository::class)->ofEmail(Email::fromString('john@example.com'));
        self::assertNotNull($user);
        self::assertSame('John Doe', $user->name()->value);
        self::assertNotSame('s3cret-Passw0rd', $user->passwordHash());

        $this->client->followRedirect();

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'john@example.com');
    }

    public function testTheNewUserCanLogInWithThePasswordItChose(): void
    {
        $this->submitRegistration('john@example.com', 'John Doe', 's3cret-Passw0rd');
        $this->client->getCookieJar()->clear();

        $crawler = $this->client->request('GET', '/login');
        $this->client->submit($crawler->selectButton('Entrar')->form([
            'login_form[email]' => 'john@example.com',
            'login_form[password]' => 's3cret-Passw0rd',
        ]), serverParameters: self::SAME_ORIGIN);

        self::assertResponseRedirects('/');
    }

    public function testAnEmailAlreadyInUseIsRejectedOnTheEmailField(): void
    {
        $this->createUser('john@example.com');

        $this->submitRegistration('JOHN@example.com', 'Other', 's3cret-Passw0rd');

        $this->assertRegistrationRejectedWith('Ya existe una cuenta con este email.');
    }

    public function testInvalidDataIsRejectedWithA422(): void
    {
        $this->submitRegistration('not-an-email', 'John Doe', 'short');

        self::assertResponseStatusCodeSame(422);
        self::assertNull($this->bearerCookieFromResponse());
        self::assertNull(static::getContainer()->get(UserRepository::class)->ofEmail(Email::fromString('john@example.com')));
    }

    public function testBothPasswordsMustMatch(): void
    {
        $this->submitRegistration('john@example.com', 'John Doe', 's3cret-Passw0rd', 'other-Passw0rd');

        $this->assertRegistrationRejectedWith('Las contraseñas no coinciden.');
    }

    public function testAnEmailTheDomainRejectsIsReportedInsteadOfFailing(): void
    {
        // Valid for the HTML5 email constraint, but its local part is longer than the 64 characters PHP accepts.
        $this->submitRegistration(str_repeat('a', 65).'@example.com', 'John Doe', 's3cret-Passw0rd');

        $this->assertRegistrationRejectedWith('Revisa el nombre y el email');
    }

    public function testCrossSiteSubmissionIsRejectedByCsrfProtection(): void
    {
        $this->submitRegistration('john@example.com', 'John Doe', 's3cret-Passw0rd', server: [
            'HTTP_ORIGIN' => 'https://evil.example',
            'HTTP_REFERER' => 'https://evil.example/phishing',
        ]);

        $this->assertRegistrationRejectedWith('CSRF');
    }

    public function testAnAuthenticatedUserVisitingRegisterIsSentToTheDashboard(): void
    {
        $user = $this->createUser();
        $this->client->getCookieJar()->set(new Cookie(AccessTokenCookie::NAME, $this->issueTokenFor($user)));

        $this->client->request('GET', '/register');

        self::assertResponseRedirects('/');
    }

    public function testSignUpsFromOneIpAreThrottled(): void
    {
        for ($i = 0; $i < 10; ++$i) {
            $this->submitRegistration(\sprintf('user%d@example.com', $i), 'John Doe', 's3cret-Passw0rd');
            self::assertResponseRedirects('/');
            $this->client->getCookieJar()->clear();
        }

        $this->submitRegistration('one-more@example.com', 'John Doe', 's3cret-Passw0rd');

        self::assertResponseStatusCodeSame(429);
        self::assertResponseHasHeader('Retry-After');
        self::assertAnySelectorTextContains('form ul li', 'Demasiados registros');
        self::assertNull(static::getContainer()->get(UserRepository::class)->ofEmail(Email::fromString('one-more@example.com')));
    }

    public function testSigningUpNeverStartsASession(): void
    {
        $this->submitRegistration('john@example.com', 'John Doe', 's3cret-Passw0rd');

        self::assertFalse($this->client->getRequest()->hasSession());
        $cookieNames = array_map(static fn (ResponseCookie $c) => $c->getName(), $this->client->getResponse()->headers->getCookies());
        self::assertSame([AccessTokenCookie::NAME], $cookieNames);
    }

    /**
     * @param array<string, string> $server
     */
    private function submitRegistration(string $email, string $name, string $password, ?string $repeatedPassword = null, array $server = self::SAME_ORIGIN): void
    {
        $crawler = $this->client->request('GET', '/register');
        $form = $crawler->selectButton('Crear cuenta')->form([
            'registration_form[name]' => $name,
            'registration_form[email]' => $email,
            'registration_form[password][first]' => $password,
            'registration_form[password][second]' => $repeatedPassword ?? $password,
        ]);

        $this->client->submit($form, serverParameters: $server);
    }

    private function assertRegistrationRejectedWith(string $message): void
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
