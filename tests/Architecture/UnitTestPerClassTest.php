<?php

declare(strict_types=1);

namespace App\Tests\Architecture;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Railgun: every class has its own test, at the mirrored path of src/:
 *
 *   src/<path>/<Class>.php → tests/Unit/<path>/<Class>Test.php
 *
 * Two kinds are tested where unit tests make no sense, and nowhere else:
 *   - Controllers (extend AbstractController) → tests/Functional/<path>/<Class>Test.php (real HTTP request)
 *   - Doctrine repositories (Infrastructure/Persistence/Doctrine/*Repository) → tests/Integration/<path>/<Class>Test.php (real DB)
 *
 * Interfaces (ports) have no behaviour and are tested through their adapters. Kernel.php is framework bootstrap.
 */
final class UnitTestPerClassTest extends TestCase
{
    private const string TESTS = __DIR__.'/..';

    #[DataProvider('classes')]
    public function testClassHasItsOwnTest(string $relativePath, string $expectedTest): void
    {
        self::assertFileExists(self::TESTS.'/'.$expectedTest, \sprintf('src/%s needs its test at tests/%s.', $relativePath, $expectedTest));
    }

    public static function classes(): iterable
    {
        foreach (SourceCode::files() as $relativePath => $file) {
            if ('Kernel.php' === $relativePath || \in_array($file['kind'], [null, 'interface'], true)) {
                continue;
            }

            $suite = match (true) {
                SourceCode::isController($file) => 'Functional',
                str_contains($relativePath, '/Infrastructure/Persistence/Doctrine/') && str_ends_with($relativePath, 'Repository.php') => 'Integration',
                default => 'Unit',
            };

            yield $relativePath => [$relativePath, $suite.'/'.substr($relativePath, 0, -4).'Test.php'];
        }
    }
}
