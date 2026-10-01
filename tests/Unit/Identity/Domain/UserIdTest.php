<?php

declare(strict_types=1);

namespace App\Tests\Unit\Identity\Domain;

use App\Identity\Domain\UserId;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(UserId::class)]
final class UserIdTest extends TestCase
{
    public function testItRoundTripsThroughString(): void
    {
        $id = UserId::generate();

        self::assertTrue($id->equals(UserId::fromString($id->value)));
    }

    public function testItRejectsInvalidIds(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        UserId::fromString('not-a-uuid');
    }
}
