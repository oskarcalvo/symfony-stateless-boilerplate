<?php

declare(strict_types=1);

namespace App\Tests\Double\Identity;

use App\Identity\Domain\AccessToken;
use App\Identity\Domain\AccessTokenIssuer;
use App\Identity\Domain\User;

final class FakeAccessTokenIssuer implements AccessTokenIssuer
{
    public function issueFor(User $user): AccessToken
    {
        return new AccessToken('token-for-'.$user->id()->value, new \DateTimeImmutable('2030-01-01 00:00:00'));
    }
}
