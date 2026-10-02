<?php

declare(strict_types=1);

namespace App\Tests\Functional\Identity\GetAuthenticatedUser\Infrastructure\Http;

use App\Identity\Domain\UserRepository;
use App\Identity\Infrastructure\Security\AccessTokenCookie;
use App\Tests\Functional\Identity\IdentityWebTestCase;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\BrowserKit\Cookie;

final class GetAuthenticatedUserWebControllerTest extends IdentityWebTestCase
{
    public function testItShowsTheNameAndEmailOfTheUserIdentifiedByTheTokenCookie(): void
    {
        $this->useTokenCookie($this->issueTokenFor($this->createUser('john@example.com', name: 'John Doe')));

        $this->client->request('GET', '/user');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextSame('[data-test="name"]', 'John Doe');
        self::assertSelectorTextSame('[data-test="email"]', 'john@example.com');
    }

    public function testTheDataIsReadFromTheDatabaseOnEveryRequestNotFromTheToken(): void
    {
        $user = $this->createUser(name: 'John Doe');
        $this->useTokenCookie($this->issueTokenFor($user));
        $this->client->request('GET', '/user');
        self::assertSelectorTextSame('[data-test="name"]', 'John Doe');

        // Same token, changed row: the next request shows the new name.
        static::getContainer()->get(EntityManagerInterface::class)->getConnection()
            ->executeStatement('UPDATE identity_user SET name = ? WHERE id = ?', ['Johnny Doe', $user->id()->value]);
        $this->client->request('GET', '/user');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextSame('[data-test="name"]', 'Johnny Doe');
    }

    public function testItNeverStartsASession(): void
    {
        $this->useTokenCookie($this->issueTokenFor($this->createUser()));

        $this->client->request('GET', '/user');

        self::assertResponseIsSuccessful();
        self::assertFalse($this->client->getRequest()->hasSession());
        self::assertSame([], $this->client->getResponse()->headers->getCookies());
    }

    public function testAnonymousVisitorsAreDeniedWithoutBeingRedirected(): void
    {
        $this->client->request('GET', '/user');

        self::assertResponseStatusCodeSame(401);
        self::assertSelectorTextContains('h1', 'Acceso no permitido');
        self::assertSelectorExists('a[href="/login"]');
    }

    public function testATokenSignedWithAnotherKeyIsDeniedAndItsCookieCleared(): void
    {
        $this->useTokenCookie($this->issueTokenSignedWithAnotherKeyFor($this->createUser()));

        $this->client->request('GET', '/user');

        $this->assertDeniedAndCookieCleared();
    }

    public function testATokenSignedWithThePortalKeyButIssuedBySomeoneElseIsDenied(): void
    {
        $this->useTokenCookie($this->issueTokenWithIssuerFor($this->createUser(), 'another-app'));

        $this->client->request('GET', '/user');

        $this->assertDeniedAndCookieCleared();
    }

    public function testATokenWhoseUserIsNoLongerRegisteredIsDenied(): void
    {
        $user = $this->createUser();
        $this->useTokenCookie($this->issueTokenFor($user));
        static::getContainer()->get(UserRepository::class)->remove($user);

        $this->client->request('GET', '/user');

        $this->assertDeniedAndCookieCleared();
    }

    private function useTokenCookie(string $token): void
    {
        $this->client->getCookieJar()->set(new Cookie(AccessTokenCookie::NAME, $token, domain: 'localhost'));
    }

    private function assertDeniedAndCookieCleared(): void
    {
        self::assertResponseStatusCodeSame(401);
        self::assertSelectorTextContains('h1', 'Acceso no permitido');
        self::assertNull($this->client->getCookieJar()->get(AccessTokenCookie::NAME), 'The rejected token cookie must be deleted.');
    }
}
