<?php

declare(strict_types=1);

namespace App\Tests\Functional\Identity\RegisterUser\Infrastructure\Http;

use App\Identity\Domain\Email;
use App\Identity\Domain\UserRepository;
use App\Tests\Functional\Identity\IdentityWebTestCase;
use Doctrine\ORM\EntityManagerInterface;

final class RegisterUserApiControllerTest extends IdentityWebTestCase
{
    private const array VALID = ['name' => 'John Doe', 'email' => 'john@example.com', 'password' => 's3cret-Passw0rd'];

    public function testItCreatesTheUserAndReturnsAUsableToken(): void
    {
        $this->client->jsonRequest('POST', '/api/v1/register', ['name' => ' John  Doe ', 'email' => 'John@Example.com', 'password' => 's3cret-Passw0rd']);

        self::assertResponseStatusCodeSame(201);
        $body = $this->body();
        $user = static::getContainer()->get(UserRepository::class)->ofEmail(Email::fromString('john@example.com'));
        self::assertNotNull($user);
        self::assertSame($user->id()->value, $body['id']);
        self::assertSame('john@example.com', $body['email']);
        self::assertSame('John Doe', $body['name']);
        self::assertGreaterThan(time(), (new \DateTimeImmutable($body['expires_at']))->getTimestamp());
        self::assertSame([], $this->client->getResponse()->headers->getCookies(), 'The API never sets the BEARER cookie.');

        $this->client->request('GET', '/api/v1/me', server: ['HTTP_AUTHORIZATION' => 'Bearer '.$body['token']]);

        self::assertResponseIsSuccessful();
        self::assertSame($user->id()->value, $this->body()['id']);
    }

    public function testItNeedsNoToken(): void
    {
        $this->client->jsonRequest('POST', '/api/v1/register', self::VALID);

        self::assertResponseStatusCodeSame(201);
    }

    public function testAnEmailAlreadyInUseIsAConflict(): void
    {
        $this->createUser('john@example.com');

        $this->client->jsonRequest('POST', '/api/v1/register', [...self::VALID, 'email' => 'JOHN@example.com']);

        self::assertResponseStatusCodeSame(409);
        self::assertSame('user_already_exists', $this->body()['code']);
    }

    public function testAnInvalidPayloadIsRejectedWithTheFieldsInError(): void
    {
        $this->client->jsonRequest('POST', '/api/v1/register', ['name' => '', 'email' => 'not-an-email', 'password' => 'short']);

        self::assertResponseStatusCodeSame(422);
        $fields = array_column($this->body()['violations'], 'propertyPath');
        self::assertSame(['name', 'email', 'password'], array_values(array_unique($fields)));
        self::assertSame(0, (int) static::getContainer()->get(EntityManagerInterface::class)->getConnection()->fetchOne('SELECT COUNT(*) FROM identity_user'));
    }

    public function testAnEmptyBodyIsRejected(): void
    {
        $this->client->jsonRequest('POST', '/api/v1/register', []);

        self::assertResponseStatusCodeSame(422);
    }

    public function testAnEmailTheDomainRejectsIsA422(): void
    {
        $this->client->jsonRequest('POST', '/api/v1/register', [...self::VALID, 'email' => str_repeat('a', 65).'@example.com']);

        self::assertResponseStatusCodeSame(422);
        self::assertSame('invalid_user_data', $this->body()['code']);
    }

    public function testOnlyPostIsAllowed(): void
    {
        $this->client->request('GET', '/api/v1/register');

        self::assertResponseStatusCodeSame(405);
    }

    public function testSignUpsFromOneIpAreThrottledTogetherWithTheWebForm(): void
    {
        for ($i = 0; $i < 10; ++$i) {
            $this->client->jsonRequest('POST', '/api/v1/register', [...self::VALID, 'email' => \sprintf('user%d@example.com', $i)]);
            self::assertResponseStatusCodeSame(201);
        }

        $this->client->jsonRequest('POST', '/api/v1/register', [...self::VALID, 'email' => 'one-more@example.com']);

        self::assertResponseStatusCodeSame(429);
        self::assertResponseHasHeader('Retry-After');
        self::assertSame('too_many_registrations', $this->body()['code']);
    }

    public function testItNeverStartsASession(): void
    {
        $this->client->jsonRequest('POST', '/api/v1/register', self::VALID);

        self::assertFalse($this->client->getRequest()->hasSession());
    }

    /**
     * @return array<string, mixed>
     */
    private function body(): array
    {
        return json_decode($this->client->getResponse()->getContent(), true, flags: \JSON_THROW_ON_ERROR);
    }
}
