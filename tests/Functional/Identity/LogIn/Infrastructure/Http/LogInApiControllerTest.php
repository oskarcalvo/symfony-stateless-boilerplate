<?php

declare(strict_types=1);

namespace App\Tests\Functional\Identity\LogIn\Infrastructure\Http;

use App\Tests\Functional\Identity\IdentityWebTestCase;

final class LogInApiControllerTest extends IdentityWebTestCase
{
    public function testValidCredentialsReturnAUsableToken(): void
    {
        $user = $this->createUser('john@example.com', 's3cret-Passw0rd');

        $this->logIn('John@Example.com', 's3cret-Passw0rd');

        self::assertResponseIsSuccessful();
        $body = $this->body();
        self::assertSame(['token', 'expires_at'], array_keys($body));
        self::assertGreaterThan(time(), (new \DateTimeImmutable($body['expires_at']))->getTimestamp());
        self::assertSame([], $this->client->getResponse()->headers->getCookies(), 'The API never sets the BEARER cookie.');

        $this->client->request('GET', '/api/v1/me', server: ['HTTP_AUTHORIZATION' => 'Bearer '.$body['token']]);

        self::assertResponseIsSuccessful();
        self::assertSame($user->id()->value, $this->body()['id']);
    }

    public function testAUserRegisteredThroughTheApiCanLogInLater(): void
    {
        $this->client->jsonRequest('POST', '/api/v1/register', ['name' => 'John Doe', 'email' => 'john@example.com', 'password' => 's3cret-Passw0rd']);
        self::assertResponseStatusCodeSame(201);

        $this->logIn('john@example.com', 's3cret-Passw0rd');

        self::assertResponseIsSuccessful();
    }

    public function testAWrongPasswordIsRejected(): void
    {
        $this->createUser('john@example.com', 's3cret-Passw0rd');

        $this->logIn('john@example.com', 'wrong');

        $this->assertRejectedAsInvalidCredentials();
    }

    public function testAnUnknownEmailIsRejectedWithTheSameAnswer(): void
    {
        $this->logIn('nobody@example.com', 's3cret-Passw0rd');

        $this->assertRejectedAsInvalidCredentials();
    }

    public function testAnInvalidPayloadIsRejected(): void
    {
        $this->client->jsonRequest('POST', '/api/v1/login', ['email' => 'not-an-email']);

        self::assertResponseStatusCodeSame(422);
        $fields = array_column($this->body()['violations'], 'propertyPath');
        self::assertSame(['email', 'password'], array_values(array_unique($fields)));
    }

    public function testOnlyPostIsAllowed(): void
    {
        $this->client->request('GET', '/api/v1/login');

        self::assertResponseStatusCodeSame(405);
    }

    public function testRepeatedFailedAttemptsAreThrottled(): void
    {
        $this->createUser('john@example.com', 's3cret-Passw0rd');

        for ($i = 0; $i < 5; ++$i) {
            $this->logIn('john@example.com', 'wrong');
            self::assertResponseStatusCodeSame(401);
        }

        // Even the right password is refused while throttled.
        $this->logIn('john@example.com', 's3cret-Passw0rd');

        self::assertResponseStatusCodeSame(429);
        self::assertResponseHasHeader('Retry-After');
        self::assertSame('too_many_login_attempts', $this->body()['code']);
    }

    public function testTheApiAndTheWebFormShareTheLimits(): void
    {
        $this->createUser('john@example.com', 's3cret-Passw0rd');

        for ($i = 0; $i < 5; ++$i) {
            $crawler = $this->client->request('GET', '/login');
            $this->client->submit($crawler->selectButton('Entrar')->form([
                'login_form[email]' => 'john@example.com',
                'login_form[password]' => 'wrong',
            ]), serverParameters: ['HTTP_ORIGIN' => 'http://localhost']);
        }

        $this->logIn('john@example.com', 's3cret-Passw0rd');

        self::assertResponseStatusCodeSame(429);
    }

    public function testASuccessfulLoginResetsTheAccountCounter(): void
    {
        $this->createUser('john@example.com', 's3cret-Passw0rd');

        for ($i = 0; $i < 4; ++$i) {
            $this->logIn('john@example.com', 'wrong');
        }
        $this->logIn('john@example.com', 's3cret-Passw0rd');
        self::assertResponseIsSuccessful();

        for ($i = 0; $i < 4; ++$i) {
            $this->logIn('john@example.com', 'wrong');
            self::assertResponseStatusCodeSame(401);
        }
    }

    public function testItNeverStartsASession(): void
    {
        $this->createUser('john@example.com', 's3cret-Passw0rd');

        $this->logIn('john@example.com', 's3cret-Passw0rd');

        self::assertFalse($this->client->getRequest()->hasSession());
    }

    private function logIn(string $email, string $password): void
    {
        $this->client->jsonRequest('POST', '/api/v1/login', ['email' => $email, 'password' => $password]);
    }

    private function assertRejectedAsInvalidCredentials(): void
    {
        self::assertResponseStatusCodeSame(401);
        self::assertSame(['code' => 'invalid_credentials', 'message' => 'Invalid email or password.'], $this->body());
    }

    /**
     * @return array<string, mixed>
     */
    private function body(): array
    {
        return json_decode($this->client->getResponse()->getContent(), true, flags: \JSON_THROW_ON_ERROR);
    }
}
