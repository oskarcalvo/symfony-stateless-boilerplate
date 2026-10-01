<?php

declare(strict_types=1);

namespace App\Identity\Domain;

/**
 * Port: issues the signed, self-contained token a client must send back on every request.
 */
interface AccessTokenIssuer
{
    public function issueFor(User $user): AccessToken;
}
