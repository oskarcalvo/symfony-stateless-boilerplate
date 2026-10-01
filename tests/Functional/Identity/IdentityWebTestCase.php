<?php

declare(strict_types=1);

namespace App\Tests\Functional\Identity;

use App\Identity\Domain\AccessTokenIssuer;
use App\Identity\Domain\User;
use App\Identity\Domain\UserRepository;
use App\Identity\RegisterUser\Application\RegisterUser;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

abstract class IdentityWebTestCase extends WebTestCase
{
    protected KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = static::createClient();

        static::getContainer()->get(EntityManagerInterface::class)
            ->getConnection()
            ->executeStatement('DELETE FROM identity_user');

        // Rate limiter counters and the JWT blocklist live in cache pools that survive between tests.
        static::getContainer()->get('cache.rate_limiter')->clear();
        static::getContainer()->get('cache.app')->clear();
    }

    protected function createUser(string $email = 'john@example.com', string $password = 's3cret-Passw0rd'): User
    {
        $id = static::getContainer()->get(RegisterUser::class)($email, $password);

        return static::getContainer()->get(UserRepository::class)->ofId($id);
    }

    protected function issueTokenFor(User $user): string
    {
        return static::getContainer()->get(AccessTokenIssuer::class)->issueFor($user)->value;
    }
}
