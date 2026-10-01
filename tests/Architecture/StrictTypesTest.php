<?php

declare(strict_types=1);

namespace App\Tests\Architecture;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Finder\Finder;

/**
 * Railgun: every PHP file of the project starts with declare(strict_types=1).
 */
final class StrictTypesTest extends TestCase
{
    #[DataProvider('projectFiles')]
    public function testFileDeclaresStrictTypes(string $path): void
    {
        self::assertMatchesRegularExpression('/^<\?php\s+declare\(strict_types=1\);/', (string) file_get_contents($path), \sprintf('%s must start with "declare(strict_types=1);".', $path));
    }

    public static function projectFiles(): iterable
    {
        $root = \dirname(__DIR__, 2);
        foreach ((new Finder())->files()->in([$root.'/src', $root.'/tests', $root.'/migrations'])->name('*.php') as $file) {
            yield $file->getRelativePathname() => [$file->getRealPath()];
        }
    }
}
