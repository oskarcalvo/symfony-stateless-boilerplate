<?php

declare(strict_types=1);

namespace App\Tests\Integration\Identity\LogIn\Application;

use App\Identity\Domain\InvalidCredentials;
use App\Identity\LogIn\Application\LogIn;
use App\Identity\RegisterUser\Application\RegisterUser;
use Doctrine\ORM\EntityManagerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class LogInTest extends KernelTestCase
{
    protected function setUp(): void
    {
        static::getContainer()->get(EntityManagerInterface::class)->getConnection()->executeStatement('DELETE FROM identity_user');
    }

    public function testItIssuesATokenForTheUserOwningTheCredentials(): void
    {
        $id = static::getContainer()->get(RegisterUser::class)('john@example.com', 's3cret-Passw0rd');

        $token = static::getContainer()->get(LogIn::class)('john@example.com', 's3cret-Passw0rd');

        self::assertSame($id->value, static::getContainer()->get(JWTTokenManagerInterface::class)->parse($token->value)['sub']);
    }

    public function testItRejectsAMalformedEmailAsInvalidCredentials(): void
    {
        $this->expectException(InvalidCredentials::class);

        static::getContainer()->get(LogIn::class)('not-an-email', 'whatever');
    }
}
