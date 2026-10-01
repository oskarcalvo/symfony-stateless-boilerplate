<?php

declare(strict_types=1);

namespace App\Tests\Functional\Dashboard\ShowDashboard\Infrastructure\Http;

use App\Identity\Infrastructure\Security\AccessTokenCookie;
use App\Tests\Functional\Identity\IdentityWebTestCase;
use Symfony\Component\BrowserKit\Cookie;

final class ShowDashboardControllerTest extends IdentityWebTestCase
{
    public function testAnonymousVisitorsAreSentToLogin(): void
    {
        $this->client->request('GET', '/');

        self::assertResponseRedirects('/login');
    }

    public function testItGreetsTheAuthenticatedUserAndOffersLogout(): void
    {
        $user = $this->createUser('john@example.com');
        $this->client->getCookieJar()->set(new Cookie(AccessTokenCookie::NAME, $this->issueTokenFor($user), domain: 'localhost'));

        $this->client->request('GET', '/');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'john@example.com');
        self::assertSelectorExists('form[method="post"][action^="/logout"] button');
    }
}
