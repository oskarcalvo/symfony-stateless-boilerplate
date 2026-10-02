<?php

declare(strict_types=1);

namespace App\Tests\Functional\Identity;

use App\Identity\Domain\AccessTokenIssuer;
use App\Identity\Domain\User;
use App\Identity\RegisterUser\Application\RegisterUser;
use Doctrine\ORM\EntityManagerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Encoder\JWTEncoderInterface;
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

    protected function createUser(string $email = 'john@example.com', string $password = 's3cret-Passw0rd', string $name = 'John Doe'): User
    {
        return static::getContainer()->get(RegisterUser::class)($email, $name, $password);
    }

    protected function issueTokenFor(User $user): string
    {
        return static::getContainer()->get(AccessTokenIssuer::class)->issueFor($user)->value;
    }

    /**
     * A token signed with the portal key whose "iss" is another application (null: no "iss" at all),
     * as a different app sharing the key pair would issue it.
     */
    protected function issueTokenWithIssuerFor(User $user, ?string $issuer): string
    {
        return static::getContainer()->get(JWTEncoderInterface::class)->encode(array_filter(
            ['sub' => $user->id()->value, 'iss' => $issuer, 'iat' => time(), 'exp' => time() + 3600],
            static fn (mixed $claim): bool => null !== $claim,
        ));
    }

    /**
     * A well-formed RS256 token with every portal claim, but signed with a key that is not the portal's.
     */
    protected function issueTokenSignedWithAnotherKeyFor(User $user): string
    {
        $base64Url = static fn (string $data): string => rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
        $unsigned = $base64Url(json_encode(['typ' => 'JWT', 'alg' => 'RS256'], \JSON_THROW_ON_ERROR))
            .'.'.$base64Url(json_encode([
                'sub' => $user->id()->value,
                'iss' => $_SERVER['JWT_ISSUER'] ?? $_ENV['JWT_ISSUER'],
                'iat' => time(),
                'exp' => time() + 3600,
            ], \JSON_THROW_ON_ERROR));

        $foreignKey = openssl_pkey_new(['private_key_type' => \OPENSSL_KEYTYPE_RSA, 'private_key_bits' => 2048]);
        openssl_sign($unsigned, $signature, $foreignKey, \OPENSSL_ALGO_SHA256);

        return $unsigned.'.'.$base64Url($signature);
    }
}
