<?php

declare(strict_types=1);

namespace App\Tests\Unit\Identity\Domain;

use App\Identity\Domain\UserName;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(UserName::class)]
final class UserNameTest extends TestCase
{
    public function testItCollapsesWhitespaceButKeepsTheCase(): void
    {
        $name = UserName::fromString("  John \t  Doe ");

        self::assertSame('John Doe', $name->value);
        self::assertSame('John Doe', (string) $name);
    }

    public function testItAcceptsTheMaximumLength(): void
    {
        self::assertSame(UserName::MAX_LENGTH, mb_strlen(UserName::fromString(str_repeat('ñ', UserName::MAX_LENGTH))->value));
    }

    #[DataProvider('invalidNames')]
    public function testItRejectsInvalidNames(string $name): void
    {
        $this->expectException(\InvalidArgumentException::class);

        UserName::fromString($name);
    }

    public static function invalidNames(): iterable
    {
        yield 'empty' => [''];
        yield 'only whitespace' => ["  \t "];
        yield 'too long' => [str_repeat('a', UserName::MAX_LENGTH + 1)];
    }
}
