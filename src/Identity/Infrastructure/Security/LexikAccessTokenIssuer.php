<?php

declare(strict_types=1);

namespace App\Identity\Infrastructure\Security;

use App\Identity\Domain\AccessToken;
use App\Identity\Domain\AccessTokenIssuer;
use App\Identity\Domain\User;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;

#[AsAlias(AccessTokenIssuer::class)]
final class LexikAccessTokenIssuer implements AccessTokenIssuer
{
    public function __construct(
        private readonly JWTTokenManagerInterface $jwtManager,
    ) {
    }

    public function issueFor(User $user): AccessToken
    {
        $token = $this->jwtManager->create(SecurityUser::fromUser($user));
        $payload = $this->jwtManager->parse($token);

        return new AccessToken($token, new \DateTimeImmutable('@'.$payload['exp']));
    }
}
